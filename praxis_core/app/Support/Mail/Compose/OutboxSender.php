<?php

namespace App\Support\Mail\Compose;

use App\Models\Mail\MailMessage;
use App\Models\Mail\MailOutbox;
use App\Models\User;
use App\Support\Mail\MailAccess;
use App\Support\Mail\MailNotices;
use App\Support\Mail\MailSendException;
use App\Support\Mail\MailServer;
use App\Support\Mail\MailTransport;
use App\Support\Mail\Sync\MailSync;
use App\Support\Mail\Sync\ThreadCounters;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Sends what is due in mail_outbox (mail:send-due every minute, and the composer right after the undo window).
 * A row is taken by one atomic update (queued → sending), so it never goes twice. Then:
 *
 *   sent      MailTransport sent it and put the copy in Sent; the original is marked \Answered, the server
 *             draft removed, uploads deleted, and Sent and Drafts are read again so the conversation shows it
 *   queued    a temporary failure: tried again after 1, 2, 5, 15, 30 and 60 minutes
 *   failed    a permanent failure (or retries used up): the sender gets a bell notification
 */
final class OutboxSender
{
    public function __construct(private readonly MailServer $server)
    {
    }

    public static function make(): self
    {
        return app(self::class);
    }

    /** Send everything due. @return array{sent: int, retry: int, failed: int, stuck: int} */
    public function due(?int $limit = null): array
    {
        $counts = ['sent' => 0, 'retry' => 0, 'failed' => 0, 'stuck' => 0];
        $counts['stuck'] = $this->releaseStuck();

        $ids = MailOutbox::where('status', 'queued')->where('send_at', '<=', now())
            ->orderBy('send_at')->limit($limit ?? (int) config('mail_compose.batch', 25))->pluck('id');
        foreach ($ids as $id) {
            $row = MailOutbox::find($id);
            if (! $row) {
                continue;
            }
            $key = ['sent' => 'sent', 'queued' => 'retry', 'failed' => 'failed'][$this->send($row)->status] ?? null;
            if ($key) {
                $counts[$key]++;
            }
        }

        return $counts;
    }

    /**
     * Send one queued message if it is due ($early: up to that many seconds before, for the composer's own
     * call at the end of the undo window). Anything else returns the row unchanged.
     */
    public function send(MailOutbox $outbox, int $early = 0): MailOutbox
    {
        $claimed = MailOutbox::where('id', $outbox->id)->where('status', 'queued')->where('send_at', '<=', now()->addSeconds($early))
            ->update(['status' => 'sending', 'attempts' => DB::raw('attempts + 1'), 'updated_at' => now()]);
        $outbox->refresh();
        if (! $claimed) {
            return $outbox;
        }

        $account = $outbox->account;
        $user = User::find($outbox->user_id);
        if (! $user || ! $account || ! MailAccess::can($user, $account, 'send')) {
            return $this->failed($outbox, 'You can no longer send from ' . ($account->email ?? 'this mailbox') . '.', $user);
        }
        if ($account->status !== 'active') {
            return $this->failed($outbox, "{$account->email} needs its password again on Mailboxes. Send the message again once it is back.", $user);
        }

        try {
            $email = MessageBuilder::build($outbox, $user);
            $result = MailTransport::make()->send($user, $account, $email);
        } catch (MailSendException $e) {
            return $e->permanent ? $this->failed($outbox, $e->getMessage(), $user) : $this->retry($outbox, $e->getMessage(), $user);
        } catch (HttpExceptionInterface) {
            return $this->failed($outbox, 'You can no longer send from ' . $account->email . '.', $user);
        } catch (Throwable $e) {
            Log::error('Mail: sending failed', ['outbox' => $outbox->id, 'error' => $e->getMessage(), 'at' => $e->getFile() . ':' . $e->getLine()]);

            return $this->retry($outbox, 'Something went wrong while sending.', $user);
        }

        $outbox->forceFill([
            'status' => 'sent',
            'sent_at' => now(),
            'message_id' => $result['message_id'],
            'message_id_hash' => MailMessage::hashId($result['message_id']),
            'last_error' => null,
        ])->save();

        try {
            $this->afterSent($outbox, $user);
        } catch (Throwable $e) {
            Log::warning('Mail: sent, tidying up afterwards failed', ['outbox' => $outbox->id, 'error' => $e->getMessage()]);
        }

        return $outbox;
    }

    /** The original marked \Answered, the server draft removed, files deleted, Sent and Drafts read again */
    private function afterSent(MailOutbox $outbox, User $user): void
    {
        $account = $outbox->account;
        Outbox::make()->removeServerDraft($outbox);
        Outbox::make()->removeFiles($outbox);
        $p = $outbox->payload;
        foreach ($p['attachments'] ?? [] as $i => $a) {
            unset($p['attachments'][$i]['path']);
        }
        $outbox->forceFill(['payload' => $p])->save();

        $original = in_array($outbox->kind, ['reply', 'reply_all'], true) && $outbox->reply_to_message_id
            ? MailMessage::with('folder')->find($outbox->reply_to_message_id) : null;

        $lock = Cache::lock('mail-sync:' . $account->id, 120);
        $locked = $lock->get();
        $session = null;
        try {
            $session = $this->server->open($account);
            if ($original && ! $original->is_answered && $original->folder) {
                $session->select($original->folder->path);
                $session->addFlags([(int) $original->uid], ['\\Answered']);
                MailMessage::where('id', $original->id)->update(['is_answered' => true]);
            }
            if ($locked) {
                $deadline = microtime(true) + 8;
                foreach (['sent', 'drafts'] as $role) {
                    if ($folder = $account->folder($role)) {
                        MailSync::make()->folder($account, $session, $folder, $deadline);
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning('Mail: sent; the server could not be updated afterwards', ['outbox' => $outbox->id, 'error' => $e->getMessage()]);
        } finally {
            rescue(fn () => $session?->logout(), null, false);
            if ($locked) {
                $lock->release();
            }
        }

        $threadId = MailMessage::where('account_id', $account->id)->where('message_id_hash', $outbox->message_id_hash)->value('thread_id') ?? $outbox->thread_id;
        if ($threadId && $threadId !== $outbox->thread_id) {
            $outbox->forceFill(['thread_id' => $threadId])->save();
        }
        if ($threadId) {
            rescue(fn () => ThreadCounters::refresh([$threadId]), null, false);
        }
        // The conversation's timeline (PR 4 shows it) on shared mailboxes
        if ($threadId && $account->isShared()) {
            DB::table('mail_thread_activity')->insert([
                'thread_id' => $threadId, 'user_id' => $user->id, 'type' => 'sent',
                'data' => json_encode(['outbox_id' => $outbox->id, 'kind' => $outbox->kind, 'subject' => $p['subject'] ?? null]),
                'created_at' => now(),
            ]);
        }
    }

    private function retry(MailOutbox $outbox, string $error, ?User $user): MailOutbox
    {
        $waits = (array) config('mail_compose.retry_minutes', [1, 2, 5, 15, 30, 60]);
        $attempt = (int) $outbox->attempts;
        if ($attempt > count($waits)) {
            return $this->failed($outbox, $error, $user);
        }
        $outbox->forceFill([
            'status' => 'queued',
            'send_at' => now()->addMinutes((int) $waits[$attempt - 1]),
            'last_error' => mb_substr($error, 0, 500),
        ])->save();

        return $outbox;
    }

    private function failed(MailOutbox $outbox, string $error, ?User $user): MailOutbox
    {
        $outbox->forceFill(['status' => 'failed', 'last_error' => mb_substr($error, 0, 500)])->save();

        if ($user) {
            $p = $outbox->payload;
            $first = $p['to'][0] ?? $p['cc'][0] ?? $p['bcc'][0] ?? null;
            $subject = trim((string) ($p['subject'] ?? '')) ?: '(no subject)';
            MailNotices::send(
                [$user->id],
                'Mail not sent',
                "“{$subject}”" . ($first ? ' to ' . ($first['name'] ?: $first['email']) : '') . " didn’t go: {$error} Open it to change it or send it again.",
                route('hq.mail.compose.outbox', [], false),
            );
        }

        return $outbox;
    }

    /** Rows left in "sending" by a run that stopped: marked not sent (it may have gone; never send twice) */
    private function releaseStuck(): int
    {
        $stuck = MailOutbox::where('status', 'sending')->where('updated_at', '<', now()->subMinutes((int) config('mail_compose.stuck_minutes', 15)))->get();
        foreach ($stuck as $row) {
            $this->failed($row, 'HQ could not confirm that this message went. Check Sent before you send it again.', User::find($row->user_id));
        }

        return $stuck->count();
    }
}
