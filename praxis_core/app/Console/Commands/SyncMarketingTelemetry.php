<?php

namespace App\Console\Commands;

use App\Support\Access;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncMarketingTelemetry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'marketing:sync-telemetry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pulls Meta/Google Ad Spend, Calculates Liquid ROAS, and Triggers Auto-Shutdowns.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Initiating Marketing Telemetry Sync...');

        $activeCampaigns = DB::table('marketing_campaigns')->where('status', 'ACTIVE')->get();

        if ($activeCampaigns->isEmpty()) {
            $this->info('Zero ACTIVE campaigns detected. System standing down.');
            return;
        }

        foreach ($activeCampaigns as $campaign) {
            $this->info("Syncing Campaign: {$campaign->name} [{$campaign->platform}]");

            // 1. API FETCH SIMULATION (Replace with actual Meta/Google Graph API endpoints when API keys are secured)
            /* Real Meta API Call Example:
               $response = Http::get("https://graph.facebook.com/v19.0/{$campaign->external_campaign_id}/insights", [
                   'fields' => 'spend,impressions,clicks', 'access_token' => env('META_ACCESS_TOKEN')
               ])->json();
            */
            
            // For now, we simulate API spend data incrementing slightly every cron run
            $newSpend = $campaign->actual_spend + rand(10, 50); // Simulating spend increment
            $newImpressions = $campaign->impressions + rand(500, 2000);
            $newClicks = $campaign->clicks + rand(10, 50);

            // 2. CRM ATTRIBUTION (Count Leads with matching UTMs)
            // Extract the pure UTM string generated during Phase 3
            $utmString = $campaign->utm_campaign_tag;
            
            // Get all Organizations linked to this specific Campaign UTM
            $orgIds = DB::table('organizations')
                        ->where('utm_campaign', $utmString)
                        ->pluck('id')->toArray();
            
            $leadsCount = count($orgIds);
            
            // Selection 10-B: CAC based on "Qualified" Opportunities
            $oppsCount = DB::table('organizations')
                           ->whereIn('id', $orgIds)
                           ->whereIn('pipeline_stage', ['Qualified', 'Negotiation', 'Won / Lost'])
                           ->count();
                           
            $wonCount = DB::table('organizations')
                          ->whereIn('id', $orgIds)
                          ->where('status', 'Active Client')
                          ->count();

            // 3. FINANCIAL ATTRIBUTION: LIQUID ROAS (Selection 11-C)
            // Find all projects linked to these won organizations, then sum their PAID invoices
            $projectIds = DB::table('projects')->whereIn('organization_id', $orgIds)->pluck('id')->toArray();
            
            $liquidRoas = DB::table('invoices')
                            ->whereIn('project_id', $projectIds)
                            ->where('status', 'Paid')
                            ->sum('total_amount');

            // 4. MATHEMATICS
            $cac = $oppsCount > 0 ? ($newSpend / $oppsCount) : $newSpend;

            // 5. SECURITY FLAG: BLEEDING CAPITAL ALERT (Selection 13-B)
            // If spending over $500 with ZERO opportunities, alert Level 5 immediately
            if ($newSpend > 500 && $oppsCount == 0) {
                $this->triggerRedAlert($campaign, $newSpend);
            }

            // 6. AUTO-DEPLETION SHUTDOWN (Selection 15-A)
            $status = 'ACTIVE';
            if ($newSpend >= $campaign->budget_amount) {
                $status = 'COMPLETED';
                $this->notifyCompletion($campaign);
                $this->info(">>> BUDGET EXHAUSTED: Shutting down campaign {$campaign->name}.");
            }

            // 7. COMMIT TO DATABASE
            DB::table('marketing_campaigns')->where('id', $campaign->id)->update([
                'actual_spend' => $newSpend,
                'impressions' => $newImpressions,
                'clicks' => $newClicks,
                'leads' => $leadsCount,
                'opportunities' => $oppsCount,
                'won' => $wonCount,
                'calculated_cac' => $cac,
                'liquid_roas' => $liquidRoas,
                'status' => $status,
                'updated_at' => now()
            ]);
            
            $this->info(">>> Sync Complete: Spend \${$newSpend} | CAC \${$cac} | Liquid ROAS \${$liquidRoas}");
        }

        $this->info('Marketing Telemetry Sync Complete.');
    }

    private function triggerRedAlert($campaign, $spend)
    {
        foreach(Access::staffWith('marketing.approve') as $adminId) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $adminId,
                'title' => 'Campaign spending with no qualified leads',
                'message' => "‘{$campaign->name}’ has spent $" . number_format($spend, 2) . " without a single qualified lead. Review the campaign.",
                'type' => 'System',
                'link_url' => route('hq.marketing.campaigns', [], false),
                'created_at' => now()
            ]);
        }
    }

    private function notifyCompletion($campaign)
    {
        // Notify the Agency/Creator that the budget is tapped out
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $campaign->created_by,
            'title' => 'Campaign budget used up',
            'message' => "‘{$campaign->name}’ has used its whole budget and is now marked as completed.",
            'type' => 'System',
            'link_url' => route('hq.marketing.campaigns', [], false),
            'created_at' => now()
        ]);
    }
}