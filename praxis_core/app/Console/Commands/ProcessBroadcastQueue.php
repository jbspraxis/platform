<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\IntelligenceBroadcast;

class ProcessBroadcastQueue extends Command
{
    protected $signature = 'broadcast:process';
    protected $description = 'Processes the broadcast email queue safely in batches of 30 for cPanel environments.';

    public function handle()
    {
        // 1. Grab exactly 30 queued emails to stay under cPanel SMTP limits
        $queuedLogs = DB::table('broadcast_logs')
            ->where('status', 'Queued')
            ->limit(30)
            ->get();

        if ($queuedLogs->isEmpty()) {
            return;
        }

        foreach ($queuedLogs as $log) {
            try {
                $user = DB::table('users')->where('id', $log->user_id)->first();
                $org = DB::table('organizations')->where('id', $user->organization_id)->first();
                $campaign = DB::table('broadcast_campaigns')->where('id', $log->campaign_id)->first();

                if (!$user || !$campaign) {
                    throw new \Exception("Missing User or Campaign Data.");
                }

                // 2. Parse Dynamic Variables (Regex makes it immune to AI spacing errors)
                $body = $campaign->body_html;
                $body = preg_replace('/\{\{\s*first_name\s*\}\}/i', $user->first_name ?? 'Executive', $body);
                $body = preg_replace('/\{\{\s*company_name\s*\}\}/i', $org->name ?? 'your enterprise', $body);
                $body = preg_replace('/\{\{\s*industry\s*\}\}/i', $org->industry ?? 'your sector', $body);
                $body = preg_replace('/\{\{\s*pipeline_stage\s*\}\}/i', $org->pipeline_stage ?? '', $body);

                // 3. Inject the 1x1 Tracking Pixel & Unsubscribe Link
                $pixelUrl = url("/tracking/open/{$campaign->id}/{$user->id}");
                $unsubUrl = url("/tracking/unsubscribe/{$user->id}");
                
                $trackingHtml = '<img src="'.$pixelUrl.'" width="1" height="1" style="display:none;" alt="" />';
                $unsubHtml = '<br><br><div style="font-size: 11px; color: #888888; border-top: 1px solid #eeeeee; padding-top: 10px;">To stop receiving strategic updates from the Intelligence Desk, <a href="'.$unsubUrl.'" style="color: #00E5FF;">click here to unsubscribe</a>.</div>';

                $finalBody = $body . $trackingHtml . $unsubHtml;

                // 4. Send the Email
                Mail::to($user->email)->send(new IntelligenceBroadcast($campaign->subject, $finalBody));

                // 5. Mark as Sent
                DB::table('broadcast_logs')->where('id', $log->id)->update([
                    'status' => 'Sent',
                    'updated_at' => now()
                ]);

                // Increment Sent Count on Campaign
                DB::table('broadcast_campaigns')->where('id', $campaign->id)->increment('sent_count');

            } catch (\Exception $e) {
                // Handle Failures Gracefully
                DB::table('broadcast_logs')->where('id', $log->id)->update([
                    'status' => 'Failed',
                    'error_message' => substr($e->getMessage(), 0, 250),
                    'updated_at' => now()
                ]);
                DB::table('broadcast_campaigns')->where('id', $campaign->id)->increment('failed_count');
            }
        }
    }
}