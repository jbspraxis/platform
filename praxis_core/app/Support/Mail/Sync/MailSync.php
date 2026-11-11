<?php

namespace App\Support\Mail\Sync;

use App\Events\Mail\MailReceived;
use App\Models\Mail\MailAccount;
use App\Models\Mail\MailFolder;
use App\Models\Mail\MailMessage;
use App\Support\Mail\Imap\ImapException;
use App\Support\Mail\Imap\ImapSession;
use App\Support\Mail\MailAccess;
use App\Support\Mail\MailNotices;
use App\Support\Mail\MailServer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Copies a mailbox into HQ (docs/MAIL.md, "Sync"). `mail:sync` runs it every minute for every active mailbox;
 * Refresh in Mail runs it for one mailbox at once.
 *
 * Per folder (Inbox first): SEARCH SINCE <12 months ago> gives the UIDs that belong in HQ. New ones are fetched
 * newest first in batches (headers, flags, structure, then bodies); ones no longer there (deleted, moved, or now
 * older than 12 months) are removed; flags are brought up to date (CONDSTORE CHANGEDSINCE when the server has it,
 * else all flags). A first copy that runs out of time carries on at the next run. A changed UIDVALIDITY means the
 * server renumbered the folder: HQ's copy of it is dropped and read again.
 *
 * One run at a time per mailbox (cache lock). A refused password marks the mailbox needs_reconnect and tells its
 * managers once; it is not tried again until someone enters the password.
 */
final class MailSync
{
    public function __construct(private readonly MailServer $server)
    {
    }

    public static function make(): self
    {
        return app(self::class);
    }

    /**
     * @param  float|null  $budget  seconds this run may take (default config mail_client.sync.budget)
     * @param  bool  $allFolders  check every folder now, not only those due
     * @return array{status: string, folders: int, added: int, removed: int, updated: int, received: int, error?: string}
     */
    public function account(MailAccount $account, ?float $budget = null, bool $allFolders = false): array
    {
        $result = ['status' => 'ok', 'folders' => 0, 'added' => 0, 'removed' => 0, 'updated' => 0, 'received' => 0];

        if ($account->status !== 'active') {
            return ['status' => 'skipped'] + $result;
        }

        $lock = Cache::lock('mail-sync:' . $account->id, 900);
        if (! $lock->get()) {
            return ['status' => 'busy'] + $result;
        }

        $deadline = microtime(true) + ($budget ?? (float) config('mail_client.sync.budget', 50));
        $session = null;

        try {
            $session = $this->server->open($account);
            $account->forceFill(['capabilities' => $session->capabilities()])->save();

            $foldersEvery = (int) config('mail_client.sync.folders_every', 30);
            if (! $account->folders_synced_at || $account->folders_synced_at->lt(now()->subMinutes($foldersEvery)) || $allFolders) {
                $this->folderList($account, $session);
            }

            $otherEvery = (int) config('mail_client.sync.other_folders_every', 5);
            $folders = $account->folders()->where('selectable', true)->get()
                ->sortBy(fn (MailFolder $f) => sprintf('%d-%s', MailFolder::ROLE_ORDER[$f->role] ?? 9, $f->synced_at?->timestamp ?? 0));

            foreach ($folders as $folder) {
                if (microtime(true) >= $deadline) {
                    break;
                }
                $due = $allFolders || $folder->role === 'inbox' || ! $folder->initial_synced_at
                    || ! $folder->synced_at || $folder->synced_at->lt(now()->subMinutes($otherEvery));
                if (! $due) {
                    continue;
                }
                $counts = $this->folder($account, $session, $folder, $deadline);
                $result['folders']++;
                foreach (['added', 'removed', 'updated', 'received'] as $k) {
                    $result[$k] += $counts[$k];
                }
            }

            $account->forceFill(['last_synced_at' => now(), 'last_error' => null])->save();
        } catch (ImapException $e) {
            $this->failed($account, $e);
            $result['status'] = 'error';
            $result['error'] = $e->getMessage();
        } catch (Throwable $e) {
            Log::error('Mail sync failed', ['account' => $account->id, 'error' => $e->getMessage(), 'at' => $e->getFile() . ':' . $e->getLine()]);
            $account->forceFill(['last_error' => mb_substr('Sync stopped: ' . $e->getMessage(), 0, 500)])->save();
            $result['status'] = 'error';
            $result['error'] = $e->getMessage();
        } finally {
            try {
                $session?->logout();
            } catch (Throwable) {
                // closing anyway
            }
            $lock->release();
        }

        return $result;
    }

    /** Read the server's folder list into mail_folders (roles, names); folders gone from the server are removed */
    public function folderList(MailAccount $account, ImapSession $session): void
    {
        $list = $session->folders();
        $roles = FolderRoles::assign($list);
        $seen = [];

        foreach ($list as $f) {
            $seen[] = $f['path'];
            $folder = MailFolder::firstOrNew(['account_id' => $account->id, 'path' => $f['path']]);
            $folder->fill([
                'name' => mb_substr($f['name'] !== '' ? $f['name'] : $f['path'], 0, 255),
                'delimiter' => $f['delimiter'],
                'role' => $roles[$f['path']] ?? 'other',
                'attributes' => $f['attributes'],
                'selectable' => FolderRoles::selectable($f),
            ])->save();
        }

        $gone = MailFolder::where('account_id', $account->id)->whereNotIn('path', $seen)->get();
        foreach ($gone as $folder) {
            $threadIds = MailMessage::where('folder_id', $folder->id)->distinct()->pluck('thread_id')->all();
            $folder->delete(); // messages cascade
            ThreadCounters::refresh($threadIds);
        }

        $account->forceFill(['folders_synced_at' => now()])->save();
    }

    /** @return array{added: int, removed: int, updated: int, received: int} */
    public function folder(MailAccount $account, ImapSession $session, MailFolder $folder, float $deadline): array
    {
        $counts = ['added' => 0, 'removed' => 0, 'updated' => 0, 'received' => 0];
        $touchedThreads = [];

        $status = rescue(fn () => $session->status($folder->path), null, false);
        $box = $session->examine($folder->path);

        // Renumbered on the server: start this folder again
        if ($folder->uidvalidity && $box['uidvalidity'] && (int) $folder->uidvalidity !== (int) $box['uidvalidity']) {
            $touchedThreads = MailMessage::where('folder_id', $folder->id)->distinct()->pluck('thread_id')->all();
            $counts['removed'] += MailMessage::where('folder_id', $folder->id)->delete();
            $folder->forceFill(['uidnext' => null, 'highestmodseq' => null, 'initial_synced_at' => null])->save();
        }

        // Nothing moved on the server since this folder's last complete pass (same next UID, same number of messages,
        // and with CONDSTORE the same highest change number, which every flag change raises): the window search, the
        // read of every local UID and the flag check would all find nothing. A full pass still runs at least hourly,
        // which is what removes mail that has grown older than the window.
        if ($this->unchanged($session, $folder, $box)) {
            $folder->forceFill(['synced_at' => now()])->save();

            return $counts;
        }

        $since = CarbonImmutable::now()->subMonths((int) config('mail_client.sync.months', 12))->startOfDay();
        $serverUids = $box['exists'] > 0 ? $session->search('SINCE ' . $since->format('j-M-Y')) : [];
        $serverSet = array_flip($serverUids);

        $local = MailMessage::where('folder_id', $folder->id)->pluck('uid', 'id')->map(fn ($u) => (int) $u)->all();
        $localSet = array_flip($local);

        // Gone from the server, or now older than the window
        $goneIds = array_keys(array_filter($local, fn ($uid) => ! isset($serverSet[$uid])));
        foreach (array_chunk($goneIds, 500) as $chunk) {
            $touchedThreads = array_merge($touchedThreads, MailMessage::whereIn('id', $chunk)->distinct()->pluck('thread_id')->all());
            $counts['removed'] += MailMessage::whereIn('id', $chunk)->delete();
        }

        // New: newest first so the latest mail shows first on a mailbox's first copy
        $new = array_values(array_filter($serverUids, fn ($uid) => ! isset($localSet[$uid])));
        rsort($new);
        $isFirstCopy = $folder->initial_synced_at === null;
        $arrivedFrom = $isFirstCopy ? PHP_INT_MAX : (int) ($folder->uidnext ?? PHP_INT_MAX);
        $notifyAfter = now()->subHours((int) config('mail_client.sync.notify_within_hours', 48));
        $received = [];
        $done = 0;

        foreach (array_chunk($new, max(1, (int) config('mail_client.sync.batch', 40))) as $chunk) {
            if (microtime(true) >= $deadline) {
                break;
            }
            $stored = MessageWriter::store($account, $folder, $session->fetchSummaries($chunk), $session);
            $done += count($chunk);
            foreach ($stored as $m) {
                $counts['added']++;
                $touchedThreads[] = $m->thread_id;
                if ($folder->role === 'inbox' && (int) $m->uid >= $arrivedFrom && ! $m->is_outbound
                    && $m->received_at && $m->received_at->gte($notifyAfter)) {
                    $received[] = $m;
                }
            }
        }
        $complete = $done >= count($new);

        // Flags of what was already here
        $flagsSynced = false;
        if (! $isFirstCopy || $local) {
            [$updated, $threads, $flagsSynced] = $this->flags($session, $folder, $box, $serverUids, $localSet);
            $counts['updated'] += $updated;
            $touchedThreads = array_merge($touchedThreads, $threads);
        }

        $fill = [
            'uidvalidity' => $box['uidvalidity'] ?: $folder->uidvalidity,
            'server_total' => $status['messages'] ?? $box['exists'],
            'server_unseen' => $status['unseen'] ?? null,
            'synced_at' => now(),
        ];
        if ($complete) {
            $fill['uidnext'] = $box['uidnext'] ?? ($serverUids ? max($serverUids) + 1 : $folder->uidnext);
            if ($isFirstCopy) {
                $fill['initial_synced_at'] = now();
            }
        }
        if ($complete && $flagsSynced) {
            $fill['highestmodseq'] = $box['highestmodseq'];
        }
        $folder->forceFill($fill)->save();
        if ($complete && $flagsSynced) {
            rescue(fn () => Cache::put(self::fullPassKey($folder), true, self::FULL_PASS_EVERY), null, false);
        }

        ThreadCounters::refresh($touchedThreads);
        // Recount only when this run changed something here: counting a large folder every minute for nothing
        // was most of a quiet run's work (MailActions recount after their own changes)
        if ($counts['added'] || $counts['removed'] || $counts['updated'] || $touchedThreads) {
            ThreadCounters::folder($folder);
        }

        if ($received) {
            usort($received, fn ($a, $b) => $a->received_at <=> $b->received_at);
            $counts['received'] = count($received);
            event(new MailReceived(
                $account->id,
                array_map(fn ($m) => (int) $m->id, $received),
                array_values(array_unique(array_map(fn ($m) => (int) $m->thread_id, $received))),
            ));
        }

        return $counts;
    }

    /** Seconds between full passes of a folder that hasn't changed (the 12-month window moves once a day) */
    private const FULL_PASS_EVERY = 3600;

    private function unchanged(ImapSession $session, MailFolder $folder, array $box): bool
    {
        return $folder->initial_synced_at !== null
            && $folder->uidnext !== null && isset($box['uidnext']) && (int) $box['uidnext'] === (int) $folder->uidnext
            && $folder->server_total !== null && (int) $box['exists'] === (int) $folder->server_total
            && $session->hasCapability('CONDSTORE') && $folder->highestmodseq && ! empty($box['highestmodseq'])
            && (int) $box['highestmodseq'] === (int) $folder->highestmodseq
            && (bool) rescue(fn () => Cache::get(self::fullPassKey($folder)), false, false);
    }

    private static function fullPassKey(MailFolder $folder): string
    {
        return 'mail-sync-full-pass:' . $folder->id . ':' . $folder->uidvalidity;
    }

    /** @return array{0: int, 1: list<int>, 2: bool} rows updated, their threads, whether every flag was checked */
    private function flags(ImapSession $session, MailFolder $folder, array $box, array $serverUids, array $localSet): array
    {
        $known = array_values(array_filter($serverUids, fn ($uid) => isset($localSet[$uid])));
        if (! $known) {
            return [0, [], true];
        }

        $useModseq = $session->hasCapability('CONDSTORE') && $folder->highestmodseq && $box['highestmodseq'];
        if ($useModseq && (int) $box['highestmodseq'] === (int) $folder->highestmodseq) {
            return [0, [], true]; // nothing changed
        }

        $changes = [];
        if ($useModseq) {
            $changes = $session->fetchFlags([], (int) $folder->highestmodseq);
        } else {
            foreach (array_chunk($known, 1000) as $chunk) {
                $changes += $session->fetchFlags($chunk);
            }
        }

        $updated = 0;
        $threads = [];
        $rows = MailMessage::where('folder_id', $folder->id)->whereIn('uid', array_keys($changes))
            ->get(['id', 'uid', 'thread_id', 'is_seen', 'is_flagged', 'is_answered', 'is_draft']);
        foreach ($rows as $row) {
            $flags = array_map('strtolower', $changes[(int) $row->uid]['flags'] ?? []);
            $fill = [
                'is_seen' => in_array('\\seen', $flags, true),
                'is_flagged' => in_array('\\flagged', $flags, true),
                'is_answered' => in_array('\\answered', $flags, true),
                'is_draft' => in_array('\\draft', $flags, true),
            ];
            $dirty = false;
            foreach ($fill as $k => $v) {
                if ((bool) $row->{$k} !== $v) {
                    $dirty = true;
                }
            }
            if ($dirty) {
                MailMessage::where('id', $row->id)->update($fill);
                $updated++;
                $threads[] = (int) $row->thread_id;
            }
        }

        return [$updated, $threads, true];
    }

    private function failed(MailAccount $account, ImapException $e): void
    {
        if ($e->reason === 'auth') {
            $wasActive = $account->status === 'active';
            $account->forceFill(['status' => 'needs_reconnect', 'last_error' => mb_substr($e->getMessage(), 0, 500)])->save();
            if ($wasActive) {
                MailNotices::needsPassword($account);
            }

            return;
        }

        $account->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 500)])->save();
        Log::warning('Mail sync: server problem', ['account' => $account->id, 'reason' => $e->reason, 'error' => $e->getMessage()]);
    }

    /** Every mailbox that should be synced now, the least recently synced first */
    public static function due(): \Illuminate\Support\Collection
    {
        return MailAccount::where('status', 'active')
            ->orderByRaw('last_synced_at IS NULL DESC')->orderBy('last_synced_at')
            ->get()
            ->filter(function (MailAccount $a) {
                // A personal mailbox of someone who left (or can no longer use Mail) is not synced
                if ($a->isShared()) {
                    return true;
                }
                $owner = $a->owner;

                return $owner && $owner->status === 'Active';
            })
            ->values();
    }
}
