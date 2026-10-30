<?php

namespace App\Listeners\Mail;

use App\Events\Mail\MailThreadsMerged;
use App\Support\Mail\Team\MailTeam;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Conversations joined by sync: notes, activity and presence already moved (Threader::merge). The joined
 * conversation gets a line in its timeline, and its summary goes, since it no longer covers all the mail.
 */
class OnMailThreadsMerged
{
    public function handle(MailThreadsMerged $event): void
    {
        try {
            if (! DB::table('mail_threads')->where('id', $event->intoId)->exists()) {
                return;
            }
            DB::table('mail_threads')->where('id', $event->intoId)->update(['ai_summary' => null, 'ai_summary_at' => null]);
            MailTeam::log($event->intoId, null, 'merged', ['from' => $event->fromIds]);
        } catch (Throwable $e) {
            Log::warning('Mail: following a thread merge failed', ['into' => $event->intoId, 'error' => $e->getMessage()]);
        }
    }
}
