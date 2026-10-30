<?php

namespace App\Support;

use App\Exceptions\Hq\HqConflict;
use App\Exceptions\Hq\HqNotFound;
use App\Exceptions\Hq\HqRateLimited;
use App\Exceptions\Hq\HqUnavailable;
use App\Exceptions\Hq\HqValidationException;
use GuzzleHttp\Psr7\MultipartStream;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The only client to HQ: the private Site API (docs/SITE_API.md; section 6 overrides sections 1 to 5).
 *
 * - Every request is signed (6.1): HMAC-SHA256 over 9 lines: METHOD, path with query, timestamp, nonce,
 *   visitor IP, locale, key id, media type and the body hash. Each attempt gets a new nonce.
 * - Timeouts (6.6): reads 8 s with one retry, writes 20 s, chat 35 s, the email pixel 2 s; writes and chat
 *   are never retried. Redirects are never followed: a 3xx is "HQ unavailable", logged.
 * - SITE_API_BASE must be https in production; otherwise nothing is sent.
 * - Reads (settings 5 min, portfolio and Kaizen 10 min, calculator lists 10 min) are cached, and the last
 *   good copy is kept for 24 h and served while HQ is down. Only a failed read pauses HQ for 30 s; a POST
 *   that times out never does (HQ may still be storing it).
 * - Failures become typed exceptions (App\Exceptions\Hq\*); a 422 becomes a validation error whose field
 *   names are the form's input names. bootstrap/app.php turns them into calm messages.
 *
 * The secret is never logged, and nothing here logs request bodies.
 */
final class HqApi
{
    /** Set for a few seconds after a read failed, so cached reads stop waiting on HQ. */
    private const DOWN_KEY = 'hq_api:down';

    private bool $lastReadWasStale = false;

    public function __construct(
        private readonly array $config,
        private readonly Cache $cache,
    ) {
    }

    // ------------------------------------------------------------------ signing (section 6.1)

    /** The string that is signed, for a JSON or empty body: the 9 lines of section 6.1 joined by "\n". */
    public static function canonical(string $method, string $pathWithQuery, string $timestamp, string $nonce, string $visitorIp, string $locale, string $keyId, string $mediaType, string $body): string
    {
        return self::canonicalWithHash($method, $pathWithQuery, $timestamp, $nonce, $visitorIp, $locale, $keyId, $mediaType, hash('sha256', $body));
    }

    public static function canonicalWithHash(string $method, string $pathWithQuery, string $timestamp, string $nonce, string $visitorIp, string $locale, string $keyId, string $mediaType, string $bodyHash): string
    {
        return implode("\n", [
            strtoupper($method), $pathWithQuery, $timestamp, $nonce, $visitorIp, $locale, $keyId, self::mediaType($mediaType), $bodyHash,
        ]);
    }

    /** The Content-Type without parameters, lower case ("" when there is no body). */
    public static function mediaType(?string $contentType): string
    {
        return strtolower(trim(explode(';', (string) $contentType, 2)[0]));
    }

    /**
     * The body hash for multipart/form-data (6.1): PHP never sees a multipart request's raw body, so the
     * hash covers the form. One line per field, sorted by field name (byte order), joined by "\n":
     * "t:<name>=<base64 of the value>" for text, "f:<name>=<sha256 hex of the file bytes>" for a file.
     *
     * @param  array<string, string>  $fields
     * @param  array<string, string>  $fileSha256  field name => sha256 hex of the file's bytes
     */
    public static function multipartHash(array $fields, array $fileSha256): string
    {
        $lines = [];
        foreach ($fields as $name => $value) {
            $lines[(string) $name] = 't:' . $name . '=' . base64_encode((string) $value);
        }
        foreach ($fileSha256 as $name => $hex) {
            $lines[(string) $name] = 'f:' . $name . '=' . strtolower($hex);
        }
        ksort($lines, SORT_STRING);

        return hash('sha256', implode("\n", $lines));
    }

    /** Lowercase hex HMAC-SHA256 of the canonical string. */
    public static function signature(string $secret, string $canonical): string
    {
        return hash_hmac('sha256', $canonical, $secret);
    }

    // ------------------------------------------------------------------ reads

    /** GET /settings (cached 5 min, stale copy for 24 h). */
    public function settings(): array
    {
        return $this->cachedGet('settings', $this->ttl('settings'), '/settings');
    }

    /** Settings, or null when HQ is down and no copy was ever saved. Never throws: the layout uses it. */
    public function settingsIfAvailable(): ?array
    {
        try {
            return $this->settings();
        } catch (Throwable) {
            return null;
        }
    }

    /** The contact email from the last good settings (even stale), for calm "HQ is down" messages. */
    public function contactEmail(): string
    {
        foreach ([$this->locale(), 'en', 'fr'] as $locale) {
            $entry = $this->cache->get($this->cacheKey('settings', $locale));
            $email = is_array($entry) ? ($entry['data']['contact']['email'] ?? null) : null;
            if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return (string) config('site.fallback_email', 'info@jbspraxis.com');
    }

    /** GET /calculator/reference: industries, current systems, bottlenecks, currencies (cached 10 min). */
    public function calculatorReference(): array
    {
        return $this->cachedGet('calculator.reference', $this->ttl('calculator_reference'), '/calculator/reference');
    }

    /** GET /portfolio (cached 10 min). */
    public function portfolio(): array
    {
        return $this->cachedGet('portfolio', $this->ttl('portfolio'), '/portfolio');
    }

    /**
     * GET /kaizen/articles?page=N&category= (cached 10 min per page and category).
     * Returns ['data' => ..., 'meta' => ...] so that pagination sent beside "data" is kept.
     */
    public function kaizenArticles(int $page = 1, ?string $category = null): array
    {
        $query = ['page' => max(1, $page)];
        if ($category !== null && $category !== '') {
            $query['category'] = $category;
        }

        return $this->cachedGet('kaizen.list.' . $query['page'] . '.' . md5((string) $category), $this->ttl('kaizen'), '/kaizen/articles', $query, withMeta: true);
    }

    /** GET /kaizen/articles/{slug} (cached 10 min). Throws HqNotFound for an unknown or unpublished slug. */
    public function kaizenArticle(string $slug): array
    {
        return $this->cachedGet('kaizen.article.' . md5($slug), $this->ttl('kaizen'), '/kaizen/articles/' . rawurlencode($slug));
    }

    /** GET /booking/slots?month=YYYY-MM (not cached: free times change). */
    public function bookingSlots(string $month): array
    {
        return $this->data($this->send('GET', '/booking/slots', ['month' => $month]), 'booking.slots');
    }

    /** GET /reports/{token}: the cost of poor quality report. Throws HqNotFound for an unknown token. */
    public function report(string $token): array
    {
        return $this->data($this->send('GET', '/reports/' . rawurlencode($token)), 'reports.show');
    }

    /** True when the last cached read served the saved copy because HQ did not answer. */
    public function lastReadWasStale(): bool
    {
        return $this->lastReadWasStale;
    }

    // ------------------------------------------------------------------ writes

    public function createEnquiry(array $fields): array
    {
        return $this->postJson('/enquiries', $fields, 'enquiries');
    }

    public function createBooking(array $fields): array
    {
        return $this->postJson('/bookings', $fields, 'bookings');
    }

    public function createAssessment(array $fields): array
    {
        return $this->postJson('/calculator/assessments', $fields, 'calculator.assessments');
    }

    public function submitKaizenArticle(array $fields): array
    {
        return $this->postJson('/kaizen/submissions', $fields, 'kaizen.submissions');
    }

    public function sendFeedback(array $fields): array
    {
        return $this->postJson('/feedback', $fields, 'feedback');
    }

    public function requestWhitepaper(string $email): array
    {
        return $this->postJson('/whitepaper', $email !== '' ? ['email' => $email] : [], 'whitepaper');
    }

    /**
     * POST /careers/applications as multipart/form-data with the CV in the "cv" part. The signature's
     * body line is the form hash (multipartHash), which HQ rebuilds from the parsed form.
     */
    public function applyForJob(array $fields, UploadedFile $cv): array
    {
        $fields = array_map('strval', $fields);
        unset($fields['cv']); // a text field may not share the file field's name (HQ refuses it)
        $bytes = (string) file_get_contents($cv->getRealPath());
        $boundary = bin2hex(random_bytes(16));
        $parts = [];
        foreach ($fields as $name => $value) {
            $parts[] = ['name' => (string) $name, 'contents' => $value];
        }
        $parts[] = [
            'name' => 'cv',
            'contents' => $bytes,
            'filename' => $this->safeFilename($cv),
            'headers' => ['Content-Type' => $this->mimeType($cv)],
        ];
        $body = (string) new MultipartStream($parts, $boundary);

        return $this->data(
            $this->send('POST', '/careers/applications', body: $body, contentType: 'multipart/form-data; boundary=' . $boundary,
                bodyHash: self::multipartHash($fields, ['cv' => hash('sha256', $bytes)])),
            'careers.applications'
        );
    }

    /**
     * POST /chat: one turn with Praxis AI. $history is a list of ['role' => 'user'|'assistant', 'text' => string];
     * $questionNumber is the site's per-session counter (HQ allows 20 per conversation).
     *
     * @return array{reply: string, links: list<array{label: string, path: string}>, limited: bool}
     */
    public function chat(array $history, string $message, int $questionNumber): array
    {
        $data = $this->postJson('/chat', [
            'history' => array_values($history),
            'message' => $message,
            'question_number' => max(1, $questionNumber),
        ], 'chat', 'chat');

        return [
            'reply' => trim((string) (is_string($data['reply'] ?? null) ? $data['reply'] : '')),
            'links' => ChatLinks::clean($data['links'] ?? []),
            'limited' => ($data['limited'] ?? false) === true,
        ];
    }

    /**
     * GET /tracking/open/{campaign}/{user}?country=NG&t=…: best effort, 2 s, one attempt, never throws.
     * $token is the email link's "t" (HQ requires it for campaigns sent after PR2b).
     */
    public function trackOpen(string $campaignId, string $userId, ?string $country = null, ?string $token = null): void
    {
        $query = [];
        if ($country !== null && $country !== '') {
            $query['country'] = $country;
        }
        if ($token !== null && $token !== '') {
            $query['t'] = $token;
        }

        try {
            $this->send('GET', '/tracking/open/' . rawurlencode($campaignId) . '/' . rawurlencode($userId), query: $query, kind: 'pixel');
        } catch (Throwable) {
            // The pixel is bookkeeping only: the email client always gets its image.
        }
    }

    /** POST /tracking/unsubscribe/{user} with the email link's campaign and token when it has them. */
    public function unsubscribe(string $userId, ?string $campaignId = null, ?string $token = null): array
    {
        $payload = [];
        if ($campaignId !== null && $campaignId !== '') {
            $payload['campaign'] = $campaignId;
        }
        if ($token !== null && $token !== '') {
            $payload['t'] = $token;
        }

        return $this->postJson('/tracking/unsubscribe/' . rawurlencode($userId), $payload, 'tracking.unsubscribe');
    }

    // ------------------------------------------------------------------ transport

    private function postJson(string $path, array $payload, string $endpoint, string $kind = 'write'): array
    {
        $body = json_encode($payload === [] ? new \stdClass : $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $this->data($this->send('POST', $path, body: $body, contentType: 'application/json', kind: $kind), $endpoint);
    }

    /**
     * Sends one signed request. Reads get two attempts; each attempt is signed afresh (a reused nonce
     * would be rejected as a replay). $kind picks the timeout: read, write, chat or pixel.
     */
    private function send(string $method, string $path, array $query = [], ?string $body = null, ?string $contentType = null, ?string $kind = null, ?string $bodyHash = null): Response
    {
        $method = strtoupper($method);
        $kind ??= $method === 'GET' ? 'read' : 'write';
        $attempts = $kind === 'read' ? 2 : 1;
        $timeout = $this->timeout($kind);
        $isRead = $method === 'GET' && $kind === 'read'; // only a failed read pauses HQ (never a POST or the pixel)

        $base = rtrim((string) ($this->config['base'] ?? ''), '/');
        $this->assertSecureBase($base);

        $queryString = $query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '';
        $signedPath = rtrim((string) parse_url($base, PHP_URL_PATH), '/') . $path . $queryString;
        $url = $base . $path . $queryString;
        $mediaType = $body !== null ? self::mediaType($contentType ?? 'application/json') : '';

        for ($attempt = 1; ; $attempt++) {
            $request = Http::timeout($timeout)
                ->connectTimeout(min($timeout, (int) ($this->config['connect_timeout'] ?? 5)))
                ->withoutRedirecting()
                ->withHeaders($this->headers($method, $signedPath, $mediaType, $bodyHash ?? hash('sha256', $body ?? '')));
            if ($body !== null) {
                $request = $request->withBody($body, $contentType ?? 'application/json');
            }

            try {
                $response = $request->send($method, $url);
            } catch (ConnectionException $e) {
                if ($attempt < $attempts) {
                    $this->pause();
                    continue;
                }
                Log::warning('Site API: HQ did not answer', ['method' => $method, 'path' => $path, 'timeout' => $timeout]);
                if ($isRead) {
                    $this->markDown();
                }
                throw new HqUnavailable('HQ did not answer', 0, $e);
            }

            if ($response->serverError() && $attempt < $attempts) {
                $this->pause();
                continue;
            }

            if ($response->redirect()) {
                // Never followed: the signed headers must not travel to another address
                Log::warning('Site API: HQ answered with a redirect, which is never followed', ['method' => $method, 'path' => $path, 'status' => $response->status()]);
                if ($isRead) {
                    $this->markDown();
                }
                throw new HqUnavailable('HQ answered with a redirect', $response->status());
            }

            if ($response->serverError() && $isRead) {
                $this->markDown();
            }

            return $response;
        }
    }

    /** SITE_API_BASE must be https in production: refuse to send anything otherwise. */
    private function assertSecureBase(string $base): void
    {
        if (app()->environment('production') && strtolower((string) parse_url($base, PHP_URL_SCHEME)) !== 'https') {
            Log::error('Site API: SITE_API_BASE must start with https:// in production. Nothing was sent.');

            throw new HqUnavailable('SITE_API_BASE is not https');
        }
    }

    /** @return array<string, string> */
    private function headers(string $method, string $pathWithQuery, string $mediaType, string $bodyHash): array
    {
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $visitorIp = $this->visitorIp();
        $locale = $this->locale();
        $keyId = (string) ($this->config['key_id'] ?? '');

        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'praxis-site',
            'X-Site-Key' => $keyId,
            'X-Site-Timestamp' => $timestamp,
            'X-Site-Nonce' => $nonce,
            'X-Site-Visitor-Ip' => $visitorIp,
            'X-Site-Locale' => $locale,
            'X-Site-Signature' => self::signature(
                (string) ($this->config['secret'] ?? ''),
                self::canonicalWithHash($method, $pathWithQuery, $timestamp, $nonce, $visitorIp, $locale, $keyId, $mediaType, $bodyHash)
            ),
        ];
        if (! empty($this->config['host'])) {
            $headers['Host'] = (string) $this->config['host'];
        }

        return $headers;
    }

    /** The envelope's "data", or a typed exception (section 2). */
    private function data(Response $response, string $endpoint, bool $withMeta = false): array
    {
        $status = $response->status();

        if ($status >= 200 && $status < 300) {
            $json = $response->json();
            if (! is_array($json) || ! array_key_exists('data', $json)) {
                Log::warning('Site API: answer without data', ['endpoint' => $endpoint, 'status' => $status]);
                throw new HqUnavailable('HQ answered without data');
            }

            if ($withMeta) {
                return ['data' => $json['data'], 'meta' => is_array($json['meta'] ?? null) ? $json['meta'] : null];
            }

            return is_array($json['data']) ? $json['data'] : ['value' => $json['data']];
        }

        $code = (string) ($response->json('error.code') ?? '');
        $message = $response->json('error.message');
        $message = is_string($message) && trim($message) !== '' ? trim($message) : null;

        switch ($status) {
            case 422:
                $fields = $response->json('error.fields');
                throw HqValidationException::fromApi(is_array($fields) ? $fields : [], $message);
            case 404:
                throw new HqNotFound('HQ: not found', 404);
            case 409:
                throw new HqConflict($code !== '' ? $code : 'conflict', $message);
            case 429:
                throw new HqRateLimited(max(1, (int) ($response->json('error.retry_after') ?? 60)), $message);
        }

        // 401 means the key or the clocks are wrong: worth an error in the log. Nothing else is logged.
        Log::log($status === 401 ? 'error' : 'warning', 'Site API: HQ refused the request', [
            'endpoint' => $endpoint, 'status' => $status, 'code' => $code,
        ]);

        throw new HqUnavailable('HQ answered ' . $status, $status);
    }

    /**
     * Cached read: fresh copy if younger than $ttl, otherwise ask HQ; when HQ is down or rate-limits us,
     * serve the last good copy (kept for stale_ttl). A 404 or 422 is never hidden by a stale copy.
     */
    private function cachedGet(string $name, int $ttl, string $path, array $query = [], bool $withMeta = false): array
    {
        $this->lastReadWasStale = false;
        $key = $this->cacheKey($name, $this->locale());
        $entry = $this->cache->get($key);
        $hasCopy = is_array($entry) && isset($entry['at'], $entry['data']) && is_array($entry['data']);

        if ($hasCopy && (now()->getTimestamp() - (int) $entry['at']) < $ttl) {
            return $entry['data'];
        }

        // A read failed moments ago: answer from the saved copy at once instead of waiting for another timeout
        if ($hasCopy && $this->cache->get(self::DOWN_KEY)) {
            $this->lastReadWasStale = true;

            return $entry['data'];
        }

        try {
            $data = $this->data($this->send('GET', $path, $query), $name, $withMeta);
        } catch (HqUnavailable|HqRateLimited $e) {
            if ($hasCopy) {
                $this->lastReadWasStale = true;

                return $entry['data'];
            }
            throw $e;
        }

        $this->cache->put($key, ['at' => now()->getTimestamp(), 'data' => $data], (int) ($this->config['stale_ttl'] ?? 86400));

        return $data;
    }

    private function timeout(string $kind): int
    {
        $defaults = ['read' => 8, 'write' => 20, 'chat' => 35, 'pixel' => 2];

        return max(1, (int) ($this->config['timeouts'][$kind] ?? $defaults[$kind] ?? 8));
    }

    private function markDown(): void
    {
        $this->cache->put(self::DOWN_KEY, true, (int) ($this->config['down_pause'] ?? 30));
    }

    private function cacheKey(string $name, string $locale): string
    {
        return 'hq_api:' . $locale . ':' . $name;
    }

    private function ttl(string $name): int
    {
        return (int) ($this->config['cache'][$name] ?? 300);
    }

    private function pause(): void
    {
        $ms = (int) ($this->config['retry_pause_ms'] ?? 200);
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }

    /** The visitor's address as App\Support\VisitorIp resolves it (Cloudflare-aware, never "*"-trusted). */
    private function visitorIp(): string
    {
        return app()->bound('request') ? VisitorIp::of(request()) : '0.0.0.0';
    }

    private function locale(): string
    {
        return app()->getLocale() === 'fr' ? 'fr' : 'en';
    }

    private function safeFilename(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', (string) $file->getClientOriginalName()));
        $name = preg_replace('/[^\pL\pN ._()-]+/u', '_', $name) ?? 'cv';
        $name = trim(mb_substr($name, 0, 150), ' .');

        return $name !== '' ? $name : 'cv.' . ($file->getClientOriginalExtension() ?: 'pdf');
    }

    private function mimeType(UploadedFile $file): string
    {
        $byExtension = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        return $byExtension[strtolower((string) $file->getClientOriginalExtension())] ?? 'application/octet-stream';
    }
}
