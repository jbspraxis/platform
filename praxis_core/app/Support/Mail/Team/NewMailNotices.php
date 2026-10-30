<?php

namespace App\Support\Mail\Team;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailThread;
use App\Support\Mail\MailAccess;
use App\Support\Mail\MailNotices;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * New mail in a mailbox's Inbox (the MailReceived event): a bell notification, and so a phone push in the
 * Mail group, to everyone who can read the mailbox (owner decision 9), one per message, linked to the
 * conversation; nothing to the person who sent it from HQ. A Done conversation goes back to Open.
 */
final class NewMailNotices
{
    /** @param  list<int>  $messageIds  oldest first */
    public static function received(string $accountId, array $messageIds): void
    {
        $account = MailAccount::find($accountId);
        if (! $account || ! $messageIds) {
            return;
        }
        $readers = MailAccess::readers($account);

        $messages = MailMessage::whereIn('id', $messageIds)->where('account_id', $account->id)
            ->orderBy('received_at')->orderBy('id')->get();
        $threads = MailThread::whereIn('id', $messages->pluck('thread_id')->filter()->unique())->get()->keyBy('id');

        // Mail written in HQ (from another mailbox) to this one: its writer needs no alert
        $writers = DB::table('mail_outbox')->whereIn('message_id_hash', $messages->pluck('message_id_hash')->filter())
            ->pluck('user_id', 'message_id_hash');

        foreach ($messages as $message) {
            if ($message->is_outbound) {
                continue;
            }
            $skip = array_filter([$message->sent_by, $writers[$message->message_id_hash] ?? null]);
            MailNotices::send(
                array_values(array_diff($readers, $skip)),
                'New mail: ' . ($message->from_name ?: $message->from_email ?: 'unknown sender'),
                self::summaryLine($message, $account),
                route('hq.mail.inbox', array_filter(['box' => $account->id, 'thread' => $message->thread_id]), false),
            );

            $thread = $message->thread_id ? ($threads[$message->thread_id] ?? null) : null;
            if ($thread) {
                MailTeam::reopenOnReply($thread, $message->id);
            }
        }
    }

    private static function summaryLine(MailMessage $message, MailAccount $account): string
    {
        $line = trim((string) $message->subject) !== ''
            ? $message->subject
            : Str::words(trim(preg_replace('/\s+/', ' ', (string) ($message->snippet ?: $message->body_text))), 12, '…');
        $line = mb_strimwidth($line !== '' ? $line : '(no subject)', 0, 200, '…');

        return $account->isShared() ? "{$line} · {$account->email}" : $line;
    }
}
