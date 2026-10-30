<?php

namespace Tests;

use App\Support\FormStamp;
use App\Support\HqApi;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /** The Site API base used by the tests (phpunit.xml SITE_API_BASE). */
    protected const HQ = 'hq.test/api/site/v1';

    protected function setUp(): void
    {
        parent::setUp();

        // No real request may leave a test, and no retry waits
        Http::preventStrayRequests();
        $this->withoutVite();
        config(['hq_api.retry_pause_ms' => 0]);
        Cache::flush();
    }

    /**
     * Every form carries a signed page-load time (App\Support\FormStamp, 3 seconds minimum). Posts from the
     * tests get one from 30 seconds ago unless the test sets "form_ts" itself (FormGuardTest does).
     */
    public function post($uri, array $data = [], array $headers = [])
    {
        return parent::post($uri, $this->stamped($data), $headers);
    }

    public function postJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        return parent::postJson($uri, $this->stamped($data), $headers, $options);
    }

    protected function stamped(array $data): array
    {
        if (! array_key_exists(FormStamp::FIELD, $data)) {
            $data[FormStamp::FIELD] = FormStamp::make(now()->subSeconds(30));
        }

        return $data;
    }

    /** GET /settings exactly as docs/SITE_API.md section 4 shows it. */
    protected function settingsData(): array
    {
        return [
            'company' => [
                'legal_name' => 'JBS Praxis LLC', 'trading_name' => 'JBS Praxis', 'founded' => null,
                'registrations' => [
                    ['country' => 'NG', 'label' => 'CAC', 'number' => null, 'status' => 'pending'],
                    ['country' => 'CM', 'label' => 'RCCM', 'number' => null, 'status' => 'pending'],
                ],
                'offices' => [
                    ['city' => 'Lagos', 'country' => 'NG', 'address' => null],
                    ['city' => 'Douala', 'country' => 'CM', 'address' => null],
                ],
            ],
            'contact' => [
                'email' => 'info@jbspraxis.com',
                'phones' => [['label' => 'Nigeria', 'number' => null]],
                'whatsapp' => [['label' => 'Nigeria', 'number' => null]],
                'hours' => null,
            ],
            'social' => [
                'linkedin' => 'https://www.linkedin.com/company/jbspraxis',
                'x' => 'https://x.com/jbspraxis',
                'youtube' => 'https://www.youtube.com/@jbspraxis',
            ],
            'leadership' => ['show' => false, 'people' => []],
            'updated_at' => '2026-10-30T09:00:00+01:00',
        ];
    }

    /**
     * Fakes HQ: the given routes first (pattern => response), then GET /settings. Patterns are relative
     * to the API base, e.g. 'enquiries' or 'booking/slots*'.
     */
    protected function fakeHq(array $routes = []): void
    {
        $fakes = [];
        foreach ($routes as $pattern => $response) {
            $fakes[self::HQ . '/' . ltrim($pattern, '/')] = $response;
        }
        $fakes[self::HQ . '/settings'] = Http::response(['data' => $this->settingsData()]);

        Http::fake($fakes);
    }

    /** Forget earlier fakes (Http::fake() only adds stubs) and fake HQ afresh. */
    protected function refakeHttp(array $fakes): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory(app('events')));
        Http::preventStrayRequests();
        Http::fake($fakes);
    }

    /** A connection failure, as when HQ is down. */
    protected function down(): \Closure
    {
        return fn () => throw new ConnectionException('Connection refused');
    }

    /**
     * True when the request carries a valid signature for the test secret, checked the way HQ checks it
     * (docs/SITE_API.md 6.1: 9 lines with the key id and the media type; for multipart, the form hash HQ
     * rebuilds from the parsed form).
     */
    protected function signatureIsValid(HttpRequest $request, string $secret = 'test-secret'): bool
    {
        $uri = $request->toPsrRequest()->getUri();
        $path = $uri->getPath() . ($uri->getQuery() !== '' ? '?' . $uri->getQuery() : '');
        $contentType = $request->header('Content-Type')[0] ?? '';
        $mediaType = $request->body() === '' ? '' : HqApi::mediaType($contentType);
        $bodyHash = $mediaType === 'multipart/form-data'
            ? $this->formHash($request->body(), $contentType)
            : hash('sha256', $request->body());
        $canonical = HqApi::canonicalWithHash(
            $request->method(), $path, $request->header('X-Site-Timestamp')[0] ?? '', $request->header('X-Site-Nonce')[0] ?? '',
            $request->header('X-Site-Visitor-Ip')[0] ?? '', $request->header('X-Site-Locale')[0] ?? '',
            $request->header('X-Site-Key')[0] ?? '', $mediaType, $bodyHash
        );

        return hash_equals(HqApi::signature($secret, $canonical), $request->header('X-Site-Signature')[0] ?? '');
    }

    /** Parses a multipart body as PHP would and hashes the form like HQ (6.1: t:/f: lines sorted by field name). */
    private function formHash(string $body, string $contentType): string
    {
        preg_match('/boundary=("?)([^";]+)\1/', $contentType, $m);
        $lines = [];
        foreach (explode('--' . $m[2], $body) as $part) {
            if (! str_contains($part, "\r\n\r\n")) {
                continue;
            }
            [$head, $content] = explode("\r\n\r\n", ltrim($part, "\r\n"), 2);
            $content = substr($content, 0, -2); // the CRLF before the next boundary
            preg_match('/name="([^"]*)"/', $head, $name);
            $lines[$name[1]] = preg_match('/filename="/', $head)
                ? 'f:' . $name[1] . '=' . hash('sha256', $content)
                : 't:' . $name[1] . '=' . base64_encode($content);
        }
        ksort($lines, SORT_STRING);

        return hash('sha256', implode("\n", $lines));
    }

    /** The JSON body HQ received for the first request whose URL contains $needle. */
    protected function sentJson(string $needle): ?array
    {
        $found = Http::recorded(fn (HttpRequest $request) => str_contains($request->url(), $needle))->first();

        return $found ? json_decode($found[0]->body(), true) : null;
    }
}
