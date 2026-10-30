<?php

namespace App\Support\Mail;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailFolder;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailThread;
use App\Models\User;
use App\Support\Mail\Imap\ImapException;
use App\Support\Mail\Imap\ImapSession;
use App\Support\Mail\Sync\ThreadCounters;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Changes to mail (docs/MAIL.md, "Actions"): the server first, then HQ's copy, so HQ never shows something the
 * server doesn't have. Each call checks access, works on messages of one mailbox, and refreshes the counts of
 * the threads and folders it touched. Server problems throw ImapException (show $e->getMessage() kindly).
 *
 *   read / unread   any level (opening a message marks it read for everyone, owner decision 6)
 *   flag, move, archive, spam, trash, restore, delete for good, empty   send level (or the person's own mailbox)
 */
final class MailActions
{
    public function __construct(private readonly MailServer $server)
    {
    }

    public static function make(): self
    {
        return app(self::class);
    }

    /**
     * The person opened this message in HQ: mark it read (if it isn't) and remember who opened it first.
     */
    public function opened(User $user, MailMessage $message): void
    {
        $this->authorize($user, collect([$message]), 'read');
        if (! $message->seen_by) {
            MailMessage::where('id', $message->id)->whereNull('seen_by')->update(['seen_by' => $user->id, 'seen_at' => now()]);
        }
        if (! $message->is_seen) {
            $this->markRead($user, [$message], true);
        }
    }

    /** @param  iterable<MailMessage>|MailThread  $messages */
    public function markRead(User $user, iterable|MailThread $messages, bool $read = true): void
    {
        $messages = $this->resolve($messages);
        $this->authorize($user, $messages, 'read');
        $targets = $messages->filter(fn (MailMessage $m) => (bool) $m->is_seen !== $read);
        $this->flagChange($targets, '\\Seen', $read, 'is_seen');
    }

    public function flag(User $user, iterable|MailThread $messages, bool $flagged = true): void
    {
        $messages = $this->resolve($messages);
        $this->authorize($user, $messages, 'organise');
        $targets = $messages->filter(fn (MailMessage $m) => (bool) $m->is_flagged !== $flagged);
        $this->flagChange($targets, '\\Flagged', $flagged, 'is_flagged');
    }

    /** Move to the mailbox's folder with this role (archive, spam, trash, inbox); Archive is created when missing */
    public function moveToRole(User $user, iterable|MailThread $messages, string $role): void
    {
        $messages = $this->resolve($messages);
        $this->authorize($user, $messages, 'organise');
        if ($messages->isEmpty()) {
            return;
        }
        $account = $messages->first()->account;
        $folder = $account->folder($role) ?? ($role === 'archive' ? $this->createArchive($account) : null);
        if (! $folder) {
            throw ValidationException::withMessages(['folder' => 'This mailbox has no ' . (MailFolder::ROLE_LABELS[$role] ?? $role) . ' folder on the server.']);
        }
        $this->move($user, $messages, $folder);
    }

    public function move(User $user, iterable|MailThread $messages, MailFolder $to): void
    {
        $messages = $this->resolve($messages);
        $this->authorize($user, $messages, 'organise');
        $messages = $messages->filter(fn (MailMessage $m) => $m->folder_id !== $to->id);
        if ($messages->isEmpty()) {
            return;
        }
        abort_unless($to->account_id === $messages->first()->account_id && $to->selectable, 422);

        $threads = $messages->pluck('thread_id')->all();
        $folders = MailFolder::whereIn('id', $messages->pluck('folder_id')->unique())->get()->keyBy('id');

        $this->withSession($messages->first()->account, function (ImapSession $session) use ($messages, $to, $folders) {
            foreach ($messages->groupBy('folder_id') as $folderId => $group) {
                $session->select($folders[$folderId]->path);
                $map = $session->move($group->pluck('uid')->map(fn ($u) => (int) $u)->all(), $to->path);

                DB::transaction(function () use ($group, $map, $to) {
                    foreach ($group as $m) {
                        $newUid = $map[(int) $m->uid] ?? null;
                        $clash = $newUid !== null && MailMessage::where('folder_id', $to->id)->where('uid', $newUid)->exists();
                        if ($newUid !== null && ! $clash) {
                            MailMessage::where('id', $m->id)->update(['folder_id' => $to->id, 'uid' => $newUid]);
                        } else {
                            // The server didn't say the new UID (or sync got there first): sync brings it back in the new folder
                            MailMessage::where('id', $m->id)->delete();
                        }
                    }
                });
            }
        });

        $this->refresh($threads, array_merge($folders->all(), [$to]));
    }

    /** Delete for good: only from Trash or Spam (elsewhere, move to Trash) */
    public function deleteForever(User $user, iterable|MailThread $messages): void
    {
        $messages = $this->resolve($messages);
        $this->authorize($user, $messages, 'organise');
        if ($messages->isEmpty()) {
            return;
        }
        $folders = MailFolder::whereIn('id', $messages->pluck('folder_id')->unique())->get()->keyBy('id');
        foreach ($folders as $f) {
            abort_unless(in_array($f->role, ['trash', 'spam'], true), 422, 'Move it to Trash first.');
        }
        $threads = $messages->pluck('thread_id')->all();

        $this->withSession($messages->first()->account, function (ImapSession $session) use ($messages, $folders) {
            foreach ($messages->groupBy('folder_id') as $folderId => $group) {
                $session->select($folders[$folderId]->path);
                $session->expunge($group->pluck('uid')->map(fn ($u) => (int) $u)->all());
                MailMessage::whereIn('id', $group->pluck('id'))->delete();
            }
        });

        $this->refresh($threads, $folders->all());
    }

    /** Empty Trash or Spam (all its messages on the server, not only the last 12 months) */
    public function emptyFolder(User $user, MailFolder $folder): void
    {
        $account = $folder->account;
        MailAccess::authorize($user, $account, 'organise');
        abort_unless(in_array($folder->role, ['trash', 'spam'], true), 422);

        $threads = MailMessage::where('folder_id', $folder->id)->distinct()->pluck('thread_id')->all();
        $this->withSession($account, function (ImapSession $session) use ($folder) {
            $box = $session->select($folder->path);
            if ($box['exists'] > 0) {
                $session->expunge($session->search('ALL'));
            }
            MailMessage::where('folder_id', $folder->id)->delete();
        });

        $this->refresh($threads, [$folder]);
    }

    /** @param  Collection<int, MailMessage>  $targets */
    private function flagChange(Collection $targets, string $flag, bool $on, string $column): void
    {
        if ($targets->isEmpty()) {
            return;
        }
        $folders = MailFolder::whereIn('id', $targets->pluck('folder_id')->unique())->get()->keyBy('id');

        $this->withSession($targets->first()->account, function (ImapSession $session) use ($targets, $folders, $flag, $on, $column) {
            foreach ($targets->groupBy('folder_id') as $folderId => $group) {
                $session->select($folders[$folderId]->path);
                $uids = $group->pluck('uid')->map(fn ($u) => (int) $u)->all();
                $on ? $session->addFlags($uids, [$flag]) : $session->removeFlags($uids, [$flag]);
                MailMessage::whereIn('id', $group->pluck('id'))->update([$column => $on]);
            }
        });

        $this->refresh($targets->pluck('thread_id')->all(), $folders->all());
    }

    private function createArchive(MailAccount $account): MailFolder
    {
        $inbox = $account->folder('inbox');
        $delimiter = $inbox?->delimiter ?: '.';
        // cPanel's Dovecot keeps folders under INBOX (INBOX.Archive); others at the top
        $under = MailFolder::where('account_id', $account->id)->where('path', 'like', 'INBOX' . $delimiter . '%')->exists();
        $path = ($under ? 'INBOX' . $delimiter : '') . 'Archive';

        $this->withSession($account, fn (ImapSession $s) => $s->createFolder($path));

        return MailFolder::create([
            'account_id' => $account->id, 'path' => $path, 'name' => 'Archive', 'delimiter' => $delimiter,
            'role' => 'archive', 'attributes' => [], 'selectable' => true,
        ]);
    }

    private function withSession(MailAccount $account, callable $work): void
    {
        $session = $this->server->open($account);
        try {
            $work($session);
        } finally {
            try {
                $session->logout();
            } catch (ImapException) {
                // closing anyway
            }
        }
    }

    /** @return Collection<int, MailMessage> one mailbox's messages, fresh from the database */
    private function resolve(iterable|MailThread $messages): Collection
    {
        if ($messages instanceof MailThread) {
            return $messages->messages()->with('account')->get();
        }
        $ids = collect($messages)->map(fn ($m) => $m instanceof MailMessage ? $m->id : (int) $m)->all();
        $rows = MailMessage::with('account')->whereIn('id', $ids)->get();
        abort_if($rows->pluck('account_id')->unique()->count() > 1, 422, 'One mailbox at a time.');

        return $rows;
    }

    private function authorize(User $user, Collection $messages, string $ability): void
    {
        if ($messages->isEmpty()) {
            return;
        }
        MailAccess::authorize($user, $messages->first()->account, $ability);
    }

    private function refresh(array $threadIds, array $folders): void
    {
        ThreadCounters::refresh($threadIds);
        foreach ($folders as $f) {
            ThreadCounters::folder($f->fresh() ?? $f);
        }
    }
}
