<?php

namespace App\Console\Commands;

use App\Models\Mail\MailThread;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * HQ Mail housekeeping, daily: expired "viewing / replying" marks, conversations emptied more than 30 days
 * ago that nobody worked on, and finished outbox rows older than 90 days (their mail is in Sent).
 * Mail older than 12 months leaves HQ's copy during sync itself.
 */
class MailPrune extends Command
{
    protected $signature = 'mail:prune';

    protected $description = 'HQ Mail: remove expired presence, empty conversations and old outbox rows';

    public function handle(): int
    {
        $presence = DB::table('mail_presence')->where('expires_at', '<', now())->delete();

        $threads = MailThread::where('message_count', 0)->where('updated_at', '<', now()->subDays(30))
            ->whereNull('assigned_to')->where('status', 'open')
            ->whereNotIn('id', DB::table('mail_thread_notes')->whereNull('deleted_at')->select('thread_id'))
            ->whereNotIn('id', DB::table('mail_messages')->whereNotNull('thread_id')->select('thread_id'))
            ->delete();

        $outbox = DB::table('mail_outbox')->whereIn('status', ['sent', 'cancelled'])->where('updated_at', '<', now()->subDays(90))->delete();

        $this->info("Presence {$presence}, empty conversations {$threads}, outbox rows {$outbox}.");

        return self::SUCCESS;
    }
}
