<?php

namespace App\Console\Commands;

use App\Support\Pwa\PushDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Daily: forget old push delivery rows and devices not seen for months (docs/PWA.md). Owner: the "push" part. */
class PwaPushPrune extends Command
{
    // Delivery rows only stop a notification being pushed twice, and only recent ones are ever pushed
    private const DELIVERY_DAYS = 60;

    // A browser that hasn't opened HQ for half a year is almost certainly gone
    private const DEVICE_DAYS = 180;

    protected $signature = 'pwa:push-prune';

    protected $description = 'Remove old push delivery records and long-unused push devices';

    public function handle(): int
    {
        if (! PushDispatcher::ready()) {
            $this->line('push-prune: the HQ app tables are not there yet (run the migration)');

            return self::SUCCESS;
        }

        $deliveries = DB::table('push_deliveries')->where('created_at', '<', now()->subDays(self::DELIVERY_DAYS))->delete();

        $before = now()->subDays(self::DEVICE_DAYS);
        $devices = DB::table('push_subscriptions')
            ->where(fn ($q) => $q->where('last_seen_at', '<', $before)
                ->orWhere(fn ($q) => $q->whereNull('last_seen_at')->where('created_at', '<', $before)))
            ->delete();

        $this->line("push-prune: {$deliveries} delivery record(s) and {$devices} device(s) removed");

        return self::SUCCESS;
    }
}
