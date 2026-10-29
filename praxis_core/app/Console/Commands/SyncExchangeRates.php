<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncExchangeRates extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'finance:sync-exchange-rates';

    /**
     * The console command description.
     */
    protected $description = 'Synchronizes global exchange rates from the external API, ignoring manual overrides.';

    public function handle()
    {
        $this->info('[' . now() . '] INITIATING EXCHANGE RATE SYNC...');

        try {
            $apiKey = config('services.exchangerate.key'); 
            
            if (!$apiKey) {
                $this->error('CRITICAL: API Key missing in configuration.');
                Log::error('Scheduled Exchange Rate Sync Failed: Missing API Key.');
                return;
            }

            // Call the live API
            $response = Http::get("https://v6.exchangerate-api.com/v6/{$apiKey}/latest/USD");

            if ($response->successful()) {
                $data = $response->json();
                $rates = $data['conversion_rates'] ?? [];

                if (!empty($rates)) {
                    // Fetch only currencies we track that are NOT manually locked
                    $trackedCurrencies = DB::table('mdm_exchange_rates')
                        ->where('is_manual_override', 0)
                        ->pluck('currency_code');

                    $updatedCount = 0;
                    
                    foreach ($trackedCurrencies as $code) {
                        if (isset($rates[$code])) {
                            DB::table('mdm_exchange_rates')
                                ->where('currency_code', $code)
                                ->update([
                                    'rate_to_usd' => $rates[$code],
                                    'last_synced_at' => now(),
                                    'updated_at' => now()
                                ]);
                            $updatedCount++;
                            $this->line("   -> Synced: {$code} @ {$rates[$code]}");
                        }
                    }

                    $this->info('[' . now() . '] SYNC COMPLETE. Updated ' . $updatedCount . ' currencies.');
                    Log::info("Scheduled MDM Rate Sync completed successfully.");
                }
            } else {
                $this->error('API Request Failed: HTTP ' . $response->status());
                Log::warning('Scheduled MDM Rate Sync Failed: HTTP ' . $response->status());
            }
        } catch (\Exception $e) {
            $this->error('System Exception: ' . $e->getMessage());
            Log::error('Scheduled MDM Rate Sync Exception: ' . $e->getMessage());
        }
    }
}