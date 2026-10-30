<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\IntelligenceBroadcast;
use App\Support\MailLocale;

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
                // Recipients are client contacts (leads and clients)
                $user = DB::table('client_users')->where('id', $log->user_id)->first();
                $campaign = DB::table('broadcast_campaigns')->where('id', $log->campaign_id)->first();

                if (!$user || !$campaign) {
                    throw new \Exception("The recipient or the campaign no longer exists.");
                }
                // They may have unsubscribed or been removed after the campaign was queued
                if (!$user->is_subscribed || $user->deleted_at) {
                    throw new \Exception("Recipient unsubscribed or removed.");
                }
                $org = DB::table('organizations')->where('id', $user->organization_id)->first();

                // The frame (sign-off, footer) follows the campaign's language; campaigns have no language field yet, so English
                $locale = MailLocale::forRecipient($campaign->language ?? null);

                // 2. Parse Dynamic Variables (Regex makes it immune to AI spacing errors)
                // Contact data comes from public forms: escape it, and never treat it as a regex replacement pattern.
                // "Hello {{ first_name }}," becomes "Hello Ada," or "Hello,"; French never greets by first name alone.
                $first = trim((string) ($user->first_name ?? ''));
                $body = preg_replace('/\s*\{\{\s*first_name\s*\}\}/i', ($locale === 'fr' || $first === '') ? '' : ' ' . e($first), $campaign->body_html);
                $vars = [
                    'company_name' => filled($org->name ?? null) ? $org->name : __('emails.broadcast.fallback_company', [], $locale),
                    'industry' => filled($org->industry ?? null) ? $org->industry : __('emails.broadcast.fallback_industry', [], $locale),
                    'pipeline_stage' => '', // an internal CRM stage never reaches a client
                ];
                $body = preg_replace_callback('/\{\{\s*(company_name|industry|pipeline_stage)\s*\}\}/i', fn ($m) => e($vars[strtolower($m[1])]), $body);

                // 3. The 1x1 tracking pixel and the unsubscribe link: the mailable puts the pixel at the end of the body
                //    and the link in the footer and the List-Unsubscribe header
                $pixelUrl = route('tracking.open', ['campaign_id' => $campaign->id, 'user_id' => $user->id]);
                $unsubUrl = route('tracking.unsubscribe', ['user_id' => $user->id]);

                // 4. Send the Email, in the recipient's language
                Mail::to($user->email)->locale($locale)->send(new IntelligenceBroadcast($campaign->subject, $body, $unsubUrl, $pixelUrl));

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
                if (isset($campaign) && $campaign) {
                    DB::table('broadcast_campaigns')->where('id', $campaign->id)->increment('failed_count');
                }
            }
        }
    }
}