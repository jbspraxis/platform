<?php

namespace App\Support\Pwa;

use App\Models\ClientUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pushes the Client Portal's inbox to clients' devices (docs/PWA.md, "Client Portal"). A row is claimed
 * by setting its pushed_at, so whichever run gets there first (after the HQ request that wrote it, or
 * pwa:push-pending every minute) pushes it, and only once. Each device gets it in its own language.
 * Sending and device bookkeeping are HQ's (PushDispatcher).
 */
class ClientPush
{
    public static function dispatchPending(?int $limit = null): array
    {
        $stats = ['checked' => 0, 'pushed' => 0, 'devices' => 0, 'removed' => 0];
        if (! ClientNotify::ready()) {
            return $stats;
        }

        $candidates = DB::table('client_notifications')
            ->whereNull('pushed_at')
            ->where('created_at', '>=', now()->subHours(max(1, (int) config('pwa.push.window_hours'))))
            ->orderBy('created_at')->orderBy('id')
            ->limit(max(1, $limit ?? (int) config('pwa.push.batch')))
            ->get();
        $stats['checked'] = $candidates->count();

        // Claim first: a row another run has already claimed is left to that run
        $claimed = $candidates->filter(fn ($n) => DB::table('client_notifications')->where('id', $n->id)->whereNull('pushed_at')->update(['pushed_at' => now()]) === 1)->values();
        if ($claimed->isEmpty()) {
            return $stats;
        }

        $clients = ClientUser::whereIn('id', $claimed->pluck('client_user_id')->unique()->all())->where('status', 'Active')->pluck('id')->flip();
        $devices = \App\Models\PushSubscription::whereIn('client_user_id', $clients->keys()->all())->orderBy('id')->get()->groupBy('client_user_id');
        $unread = [];
        $messages = [];
        $targets = [];
        foreach ($claimed as $n) {
            if (! isset($clients[$n->client_user_id])) {
                continue;
            }
            $unread[$n->client_user_id] ??= ClientNotify::unread($n->client_user_id);
            foreach ($devices->get($n->client_user_id, collect()) as $device) {
                [$title, $body] = ClientNotify::text($n->kind, $n->params, $device->locale ?: 'en');
                $payload = PushDispatcher::payload([
                    'id' => $n->id,
                    'title' => PushDispatcher::clean($title, 150),
                    'body' => PushDispatcher::clean($body, (int) config('pwa.push.body_limit')),
                    'url' => route('portal.pwa.open', ['notification' => $n->id], false),
                    'category' => $n->kind,
                    'badge' => $unread[$n->client_user_id],
                    'ts' => $n->created_at ? Carbon::parse($n->created_at)->getTimestampMs() : now()->getTimestampMs(),
                ]);
                $urgency = in_array($n->kind, ['invoice_overdue', 'payment_rejected', 'signoff_ready', 'proposal_ready'], true) ? 'high' : 'normal';
                $targets[count($messages)] = [$n->id, $device];
                $messages[] = PushDispatcher::message($device, $payload, $urgency);
            }
        }

        $results = PushDispatcher::send($messages);
        $stats['pushed'] = $claimed->count();
        $stats['devices'] = collect($results)->where('ok', true)->count();
        $stats['removed'] = PushDispatcher::updateDevices($targets, $results);

        return $stats;
    }

    /** dispatchPending() after a response or command: reports problems, never throws. */
    public static function dispatchPendingQuietly(): void
    {
        try {
            self::dispatchPending();
        } catch (\Throwable $e) {
            try {
                report($e);
            } catch (\Throwable $ignored) {
            }
        }
    }

    /** A test notification to one of the client's devices (by endpoint), or to all of them, in each device's language. */
    public static function sendTest(ClientUser $client, ?string $endpoint = null): array
    {
        $devices = ClientNotify::devices($client->id)
            ->when($endpoint, fn ($all) => $all->where('endpoint_hash', \App\Models\PushSubscription::hash($endpoint)));
        if ($devices->isEmpty()) {
            return ['ok' => false, 'sent' => 0, 'failed' => 0, 'devices' => []];
        }

        $id = 'test-' . Str::lower(Str::random(16));
        $targets = [];
        $messages = [];
        foreach ($devices->values() as $device) {
            $locale = $device->locale === 'fr' ? 'fr' : 'en';
            $payload = PushDispatcher::payload([
                'id' => $id,
                'title' => __('portal.pwa.test_title', [], $locale),
                'body' => __('portal.pwa.test_body', [], $locale),
                'url' => '/',
                'category' => 'test',
                'badge' => ClientNotify::unread($client->id),
                'ts' => now()->getTimestampMs(),
            ]);
            $targets[count($messages)] = [$id, $device];
            $messages[] = PushDispatcher::message($device, $payload, 'normal');
        }

        $results = PushDispatcher::send($messages);
        PushDispatcher::updateDevices($targets, $results);
        $sent = collect($results)->where('ok', true)->count();

        return [
            'ok' => $sent > 0,
            'sent' => $sent,
            'failed' => count($messages) - $sent,
            'devices' => array_map(fn ($key) => ['device' => $targets[$key][1]->device ?: 'Unnamed device', 'status' => (int) ($results[$key]['status'] ?? 0)], array_keys($targets)),
        ];
    }
}
