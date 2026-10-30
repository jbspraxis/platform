<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Support\Hosts;
use App\Support\Pwa\ClientPush;
use App\Support\Pwa\PushDispatcher;
use App\Support\Pwa\VapidKeys;
use App\Support\Pwa\WebPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Devices that receive push notifications (docs/PWA.md, "Subscription API"). A person only ever
 * registers, removes or tests their own devices. Answers are always JSON.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => ['required', 'string', 'max:2000'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:200'],
            'keys.auth' => ['required', 'string', 'max:100'],
            'contentEncoding' => ['nullable', 'string', 'in:aes128gcm'],
            'standalone' => ['nullable', 'boolean'],
            'device' => ['nullable', 'string', 'max:500'],
        ], [
            'contentEncoding.in' => 'This browser’s push encoding isn’t supported. Update the browser and try again.',
        ]);
        $validator->after(function ($validator) use ($request) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if (! self::allowedEndpoint((string) $request->input('endpoint'))) {
                $validator->errors()->add('endpoint', 'The push address must be an https address on a known push service.');
            }
            // On the curve too: a key no browser could have made would be stored, then fail on the first push
            if (! WebPush::isDeviceKey(VapidKeys::b64uDecode((string) $request->input('keys.p256dh')))) {
                $validator->errors()->add('keys.p256dh', 'The device key must be an uncompressed P-256 public key (65 bytes).');
            }
            if (strlen(VapidKeys::b64uDecode((string) $request->input('keys.auth'))) !== 16) {
                $validator->errors()->add('keys.auth', 'The device auth secret must be 16 bytes.');
            }
        });
        if ($validator->fails()) {
            return self::invalid($validator->errors()->toArray());
        }

        $endpoint = (string) $request->input('endpoint');
        $hash = PushSubscription::hash($endpoint);
        [$column, $userId] = self::owner($request);
        $now = now();
        $device = self::label($request->input('device'));

        $row = [
            // A device belongs to one account at a time: a staff member on HQ or a client on the portal
            'user_id' => $column === 'user_id' ? $userId : null,
            'client_user_id' => $column === 'client_user_id' ? $userId : null,
            // Portal pushes are written in the language the client uses on this device
            'locale' => $column === 'client_user_id' ? (in_array($request->input('locale'), ['en', 'fr'], true) ? $request->input('locale') : 'en') : null,
            'endpoint' => $endpoint,
            'endpoint_hash' => $hash,
            // Stored in one form (base64url, no padding), whatever the browser sent
            'public_key' => VapidKeys::b64u(VapidKeys::b64uDecode((string) $request->input('keys.p256dh'))),
            'auth_token' => VapidKeys::b64u(VapidKeys::b64uDecode((string) $request->input('keys.auth'))),
            'content_encoding' => 'aes128gcm',
            'standalone' => $request->boolean('standalone'),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
            'last_seen_at' => $now,
            'failures' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $update = ['user_id', 'client_user_id', 'locale', 'endpoint', 'public_key', 'auth_token', 'content_encoding', 'standalone', 'user_agent', 'last_seen_at', 'failures', 'updated_at'];
        // A refresh without a label keeps the one the device already has
        if ($device !== null) {
            $row['device'] = $device;
            $update[] = 'device';
        }

        // The same browser on a shared computer moves to whoever is signed in now
        DB::table('push_subscriptions')->upsert([$row], ['endpoint_hash'], $update);
        $id = (int) DB::table('push_subscriptions')->where('endpoint_hash', $hash)->value('id');

        // At most max_devices per person: the least recently seen go first
        $keep = max(1, (int) config('pwa.push.max_devices')) - 1;
        $others = DB::table('push_subscriptions')->where($column, $userId)->where('id', '!=', $id)
            ->orderByDesc('last_seen_at')->orderByDesc('id')->pluck('id');
        if ($others->count() > $keep) {
            DB::table('push_subscriptions')->whereIn('id', $others->slice($keep)->all())->delete();
        }

        return response()->json(['ok' => true, 'id' => $id]);
    }

    /** JSON or a form body (navigator.sendBeacon at sign-out sends FormData with _token). */
    public function destroy(Request $request)
    {
        $endpoint = $request->input('endpoint');
        if (! is_string($endpoint) || $endpoint === '' || strlen($endpoint) > 2000) {
            return self::invalid(['endpoint' => ['Send the push address of the device to remove.']]);
        }

        [$column, $userId] = self::owner($request);
        DB::table('push_subscriptions')
            ->where('endpoint_hash', PushSubscription::hash($endpoint))
            ->where($column, $userId)
            ->delete();

        return response()->noContent();
    }

    public function test(Request $request): JsonResponse
    {
        $endpoint = $request->input('endpoint');
        if ($endpoint !== null && (! is_string($endpoint) || strlen($endpoint) > 2000)) {
            return self::invalid(['endpoint' => ['The push address is not valid.']]);
        }

        if (Hosts::isPortal($request)) {
            return response()->json(ClientPush::sendTest(Auth::guard('client')->user(), $endpoint ?: null));
        }

        return response()->json(PushDispatcher::sendTest($request->user(), $endpoint ?: null));
    }

    /** Whose devices these are: the client signed in on the portal, or the staff member on HQ. */
    private static function owner(Request $request): array
    {
        return Hosts::isPortal($request)
            ? ['client_user_id', (string) Auth::guard('client')->id()]
            : ['user_id', (string) Auth::guard('web')->id()];
    }

    /**
     * https, on one of the push services in config('pwa.push.services'): the server sends requests there.
     * Only the shape browsers use: a plain host name, port 443 at most, then printable characters. Looser
     * addresses ("host:+443", user info, "*" or "%" in the host, braces that the HTTP client expands as a
     * URI template) are read differently by parse_url() and by the client that sends the push.
     */
    public static function allowedEndpoint(string $endpoint): bool
    {
        $label = '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';
        if (! preg_match('~^https://(' . $label . '(?:\.' . $label . ')+)(?::443)?(?:[/?#][\x21-\x5b\x5d-\x7a\x7c\x7e]*)?\z~i', $endpoint, $m)) {
            return false;
        }
        $host = strtolower($m[1]);
        // The HTTP client (and parse_url) must read the same host, on port 443
        if (WebPush::audience($endpoint) !== 'https://' . $host || strtolower((string) parse_url($endpoint, PHP_URL_HOST)) !== $host) {
            return false;
        }

        foreach ((array) config('pwa.push.services') as $service) {
            $service = strtolower((string) $service);
            if (str_starts_with($service, '*.') ? str_ends_with($host, substr($service, 1)) : $host === $service) {
                return true;
            }
        }

        return false;
    }

    /** The device name the person sees in their settings: plain text, one line, at most 120 characters. */
    private static function label($device): ?string
    {
        if (! is_string($device)) {
            return null;
        }
        $device = trim(preg_replace('/[\s\x00-\x1F\x7F]+/u', ' ', strip_tags($device)) ?? '');

        return $device === '' ? null : mb_substr($device, 0, 120);
    }

    private static function invalid(array $errors): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => collect($errors)->flatten()->first(), 'errors' => $errors], 422);
    }
}
