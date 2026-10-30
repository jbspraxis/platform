<?php

namespace App\Support\Pwa;

use App\Http\Middleware\HqStaff;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Pushes bell notifications to people's devices (docs/PWA.md). Every notification gets one
 * push_deliveries row, claimed before anything is sent, so whichever path gets there first (after
 * a request, after a command, or pwa:push-pending every minute) pushes it, and only once.
 *
 * Delivery statuses: sending (claimed), sent, partial (some devices), failed (none), muted (the
 * person muted that group), no-device, skipped (no active staff account), baseline (older than push).
 */
class PushDispatcher
{
    // A device that fails this many runs in a row (no answer, 429, 5xx) is forgotten
    private const MAX_FAILURES = 5;

    private static ?bool $ready = null;

    /** Push every recent notification that has no delivery row yet, oldest first. */
    public static function dispatchPending(?int $limit = null): array
    {
        $stats = ['checked' => 0, 'sent' => 0, 'partial' => 0, 'failed' => 0, 'muted' => 0, 'no-device' => 0, 'skipped' => 0, 'devices' => 0, 'removed' => 0];

        $candidates = DB::table('notifications as n')
            ->leftJoin('push_deliveries as d', 'd.notification_id', '=', 'n.id')
            ->whereNull('d.notification_id')
            ->where('n.created_at', '>=', now()->subHours(max(1, (int) config('pwa.push.window_hours'))))
            ->orderBy('n.created_at')->orderBy('n.id')
            ->limit(max(1, $limit ?? (int) config('pwa.push.batch')))
            ->get(['n.id', 'n.user_id', 'n.title', 'n.message', 'n.link_url', 'n.type', 'n.created_at']);
        $stats['checked'] = $candidates->count();

        // Claim first: a notification another run has already claimed is left to that run
        $claimed = $candidates->filter(fn ($n) => DB::table('push_deliveries')->insertOrIgnore([
            'notification_id' => $n->id, 'user_id' => $n->user_id, 'status' => 'sending', 'devices' => 0, 'created_at' => now(),
        ]) === 1)->values();
        if ($claimed->isEmpty()) {
            return $stats;
        }

        try {
            return self::push($claimed, $stats);
        } catch (\Throwable $e) {
            // Claimed but not pushed: say so, rather than leave them "sending" for ever
            try {
                DB::table('push_deliveries')->whereIn('notification_id', $claimed->pluck('id')->all())->where('status', 'sending')->update(['status' => 'failed']);
            } catch (\Throwable $ignored) {
            }
            throw $e;
        }
    }

    /** dispatchPending() after a response or command: reports problems, never throws. */
    public static function dispatchPendingQuietly(): void
    {
        try {
            if (self::ready()) {
                self::dispatchPending();
            }
        } catch (\Throwable $e) {
            try {
                report($e);
            } catch (\Throwable $ignored) {
                // Nothing more can be done: the request or command it follows must not fail
            }
        }
    }

    private static function push(Collection $claimed, array $stats): array
    {
        $users = User::whereIn('id', $claimed->pluck('user_id')->unique()->all())->get()->keyBy('id');
        $devices = PushSubscription::whereIn('user_id', $users->keys()->all())->orderBy('id')->get()->groupBy('user_id');
        $muted = [];
        $unread = [];
        $outcome = [];   // notification id => status, for those not sent
        $messages = [];
        $targets = [];   // message key => [notification id, subscription]

        foreach ($claimed as $n) {
            $user = $users->get($n->user_id);
            if (! $user || ! HqStaff::allows($user)) {
                $outcome[$n->id] = 'skipped';
                continue;
            }
            $category = PushCategories::forNotification($n->link_url, $n->type);
            $muted[$user->id] ??= PushPreferences::muted($user->id);
            if (in_array($category, $muted[$user->id], true)) {
                $outcome[$n->id] = 'muted';
                continue;
            }
            $subscriptions = $devices->get($user->id, collect());
            if ($subscriptions->isEmpty()) {
                $outcome[$n->id] = 'no-device';
                continue;
            }

            $unread[$user->id] ??= self::unread($user->id);
            $payload = self::payload([
                'id' => $n->id,
                'title' => self::clean((string) $n->title, 150) ?: 'New notification in HQ',
                'body' => self::clean((string) $n->message, (int) config('pwa.push.body_limit')),
                'url' => route('pwa.open', ['notification' => $n->id], false),
                'category' => $category,
                'badge' => $unread[$user->id],
                'ts' => $n->created_at ? Carbon::parse($n->created_at)->getTimestampMs() : now()->getTimestampMs(),
            ]);
            $urgency = $category === 'system' || self::asksForApproval((string) $n->title) ? 'high' : 'normal';
            foreach ($subscriptions as $subscription) {
                $targets[count($messages)] = [$n->id, $subscription];
                $messages[] = self::message($subscription, $payload, $urgency);
            }
        }

        // One pool for the whole batch
        $results = self::send($messages);

        $reached = [];
        foreach ($targets as $key => [$id, $subscription]) {
            $reached[$id] ??= ['devices' => 0, 'ok' => 0];
            $reached[$id]['devices']++;
            $reached[$id]['ok'] += ($results[$key]['ok'] ?? false) ? 1 : 0;
        }
        foreach ($reached as $id => $r) {
            $status = $r['ok'] === $r['devices'] ? 'sent' : ($r['ok'] > 0 ? 'partial' : 'failed');
            DB::table('push_deliveries')->where('notification_id', $id)
                ->update(['status' => $status, 'devices' => $r['ok'], 'sent_at' => $r['ok'] > 0 ? now() : null]);
            $stats[$status]++;
            $stats['devices'] += $r['ok'];
        }
        foreach (collect($outcome)->groupBy(fn ($status) => $status, true) as $status => $ids) {
            DB::table('push_deliveries')->whereIn('notification_id', $ids->keys()->all())->update(['status' => $status]);
            $stats[$status] += $ids->count();
        }

        $stats['removed'] = self::updateDevices($targets, $results);

        return $stats;
    }

    /** A test notification to one of the person's devices (by endpoint), or to all of them. */
    public static function sendTest(User $user, ?string $endpoint = null): array
    {
        $query = PushSubscription::where('user_id', $user->id)->orderBy('id');
        if ($endpoint !== null && $endpoint !== '') {
            $query->where('endpoint_hash', PushSubscription::hash($endpoint));
        }
        $subscriptions = $query->get();
        if ($subscriptions->isEmpty()) {
            return ['ok' => false, 'sent' => 0, 'failed' => 0, 'devices' => []];
        }

        $id = 'test-' . Str::lower(Str::random(16));
        $payload = self::payload([
            'id' => $id,
            'title' => 'Test notification',
            'body' => 'HQ notifications are working on this device.',
            'url' => route('hq.dash.workspace', [], false),
            'category' => 'other',
            'badge' => self::unread($user->id),
            'ts' => now()->getTimestampMs(),
        ]);
        $targets = [];
        $messages = [];
        foreach ($subscriptions as $subscription) {
            $targets[count($messages)] = [$id, $subscription];
            $messages[] = self::message($subscription, $payload, 'normal');
        }

        $results = self::send($messages);
        self::updateDevices($targets, $results);

        $devices = [];
        foreach ($targets as $key => [, $subscription]) {
            $devices[] = ['device' => $subscription->device ?: 'Unnamed device', 'status' => (int) ($results[$key]['status'] ?? 0)];
        }
        $sent = collect($results)->where('ok', true)->count();

        return ['ok' => $sent > 0, 'sent' => $sent, 'failed' => count($messages) - $sent, 'devices' => $devices];
    }

    /**
     * Whether the PWA tables are there. On the server the code is uploaded a few minutes before the
     * migration runs; until then pushing does nothing. Checked once per process.
     */
    public static function ready(): bool
    {
        return self::$ready ??= Schema::hasTable('pwa_settings') && Schema::hasTable('push_deliveries')
            && Schema::hasTable('push_subscriptions') && Schema::hasTable('push_preferences');
    }

    /** Forget the table check (tests). */
    public static function flush(): void
    {
        self::$ready = null;
    }

    /** WebPush::send(), except that a failure on this side (keys, OpenSSL) is reported and blames no device. */
    public static function send(array $messages): array
    {
        if (! $messages) {
            return [];
        }
        try {
            return WebPush::send($messages);
        } catch (\Throwable $e) {
            report($e);

            return array_map(fn () => ['status' => 0, 'ok' => false, 'gone' => false, 'error' => null, 'ours' => true], $messages);
        }
    }

    /**
     * After a pool: devices that took a message are fresh again, devices the push service has
     * forgotten are removed, and devices that didn't answer move one step closer to removal.
     * Returns how many were removed.
     */
    public static function updateDevices(array $targets, array $results): int
    {
        $byDevice = [];
        foreach ($targets as $key => [, $subscription]) {
            $result = $results[$key] ?? null;
            if ($result === null || ! empty($result['ours'])) {
                continue;
            }
            $device = $byDevice[$subscription->id] ??= ['ok' => false, 'gone' => false, 'failed' => false];
            $byDevice[$subscription->id] = [
                'ok' => $device['ok'] || $result['ok'],
                'gone' => $device['gone'] || $result['gone'],
                // 413 is the message's fault, not the device's
                'failed' => $device['failed'] || (! $result['ok'] && ! $result['gone'] && $result['status'] !== 413),
            ];
            if ($result['status'] === 413) {
                Log::warning('Push: a push service refused a message as too large', ['service' => parse_url((string) $subscription->endpoint, PHP_URL_HOST)]);
            }
        }

        $ok = array_keys(array_filter($byDevice, fn ($d) => $d['ok']));
        $gone = array_keys(array_filter($byDevice, fn ($d) => ! $d['ok'] && $d['gone']));
        $failed = array_keys(array_filter($byDevice, fn ($d) => ! $d['ok'] && ! $d['gone'] && $d['failed']));

        if ($ok) {
            DB::table('push_subscriptions')->whereIn('id', $ok)->update(['last_sent_at' => now(), 'failures' => 0, 'updated_at' => now()]);
        }
        $removed = $gone ? DB::table('push_subscriptions')->whereIn('id', $gone)->delete() : 0;
        if ($failed) {
            DB::table('push_subscriptions')->whereIn('id', $failed)->update(['failures' => DB::raw('failures + 1'), 'updated_at' => now()]);
            $removed += DB::table('push_subscriptions')->whereIn('id', $failed)->where('failures', '>=', self::MAX_FAILURES)->delete();
        }

        return $removed;
    }

    public static function message(PushSubscription $subscription, string $payload, string $urgency): array
    {
        return [
            'endpoint' => $subscription->endpoint,
            'p256dh' => $subscription->public_key,
            'auth' => $subscription->auth_token,
            'payload' => $payload,
            'urgency' => $urgency,
            'ttl' => (int) config('pwa.push.ttl'),
        ];
    }

    /** The JSON the service worker reads (docs/PWA.md, "Push payload"). */
    public static function payload(array $p): string
    {
        return json_encode([
            'v' => 1,
            'id' => $p['id'],
            'title' => $p['title'],
            'body' => $p['body'],
            'url' => $p['url'],
            'category' => $p['category'],
            'tag' => $p['id'],
            'badge' => $p['badge'],
            'ts' => $p['ts'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private static function unread(string $userId): ?int
    {
        try {
            return DB::table('notifications')->where('user_id', $userId)->where('is_read', 0)->count();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** One line of plain text, at most $limit characters including the ellipsis. */
    public static function clean(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        $limit = max(2, $limit);

        return mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, $limit - 1)) . '…' : $text;
    }

    /** "Cash request to approve", "Campaign budget to approve", "Approval needed": the person is waited on. */
    private static function asksForApproval(string $title): bool
    {
        return (bool) preg_match('/\bto approve\b|\bapproval\b/i', $title);
    }
}
