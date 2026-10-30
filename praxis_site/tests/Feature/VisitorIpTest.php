<?php

namespace Tests\Feature;

use App\Support\VisitorIp;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * The visitor IP sent to HQ and counted by the limits (docs/SITE_API.md 6.2, 6.6): TRUSTED_PROXIES rules,
 * Cloudflare headers believed only from Cloudflare's ranges, IPv6 counted per /64.
 */
class VisitorIpTest extends TestCase
{
    private const FORM = ['website' => 'filled']; // a honeypot post: the limit counts it, HQ never sees it

    private function visitorIpSentFor(array $server, array $headers = []): string
    {
        $this->fakeHq(['reports/*' => Http::response(['data' => ['token' => 'PRX-1']])]);
        $this->withServerVariables($server)->get('/dossier/PRX-ABCDEF', $headers);

        $sent = Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), '/reports/'))->last()[0];

        return $sent->header('X-Site-Visitor-Ip')[0];
    }

    public function test_ipv6_is_counted_per_64_and_ipv4_per_address(): void
    {
        $this->assertSame('203.0.113.5', VisitorIp::bucket('203.0.113.5'));
        $this->assertSame('2001:db8:1:2::/64', VisitorIp::bucket('2001:db8:1:2::1'));
        $this->assertSame('2001:db8:1:2::/64', VisitorIp::bucket('2001:0db8:0001:0002:ffff:ffff:ffff:ffff'));
        $this->assertNotSame(VisitorIp::bucket('2001:db8:1:2::1'), VisitorIp::bucket('2001:db8:1:3::1'));
        $this->assertSame('192.0.2.7', VisitorIp::bucket('::ffff:192.0.2.7'), 'an IPv4-mapped address counts as IPv4');
    }

    public function test_trusted_proxies_never_accepts_a_wildcard(): void
    {
        Log::spy();

        $this->assertSame([], VisitorIp::trustedProxies(''));
        $this->assertSame([], VisitorIp::trustedProxies('*'));
        $this->assertSame([], VisitorIp::trustedProxies('**'));
        $this->assertSame(['10.0.0.5', '10.0.1.0/24', '2001:db8::/48'], VisitorIp::trustedProxies('10.0.0.5, 10.0.1.0/24, *, not-an-ip, 0.0.0.0/0, 10.0.0.0/33, 2001:db8::/48'));
        $this->assertSame(config('site.cloudflare_ranges'), VisitorIp::trustedProxies('cloudflare'));
        $this->assertContains('104.16.0.0/13', VisitorIp::trustedProxies('Cloudflare'));
        $this->assertContains('2606:4700::/32', VisitorIp::trustedProxies('cloudflare'));

        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, '"*" is not accepted'))->atLeast()->once();
    }

    public function test_without_trusted_proxies_forwarded_headers_are_ignored(): void
    {
        $ip = $this->visitorIpSentFor(['REMOTE_ADDR' => '10.9.8.7'], ['X-Forwarded-For' => '1.2.3.4', 'CF-Connecting-IP' => '1.2.3.4']);

        $this->assertSame('10.9.8.7', $ip);
    }

    public function test_cf_connecting_ip_is_believed_only_from_cloudflares_ranges(): void
    {
        $this->assertSame('203.0.113.9', $this->visitorIpSentFor(['REMOTE_ADDR' => '172.64.10.10'], ['CF-Connecting-IP' => '203.0.113.9']));
        $this->assertSame('2001:db8::42', $this->visitorIpSentFor(['REMOTE_ADDR' => '2606:4700::1'], ['CF-Connecting-IP' => '2001:db8::42']));
        $this->assertSame('172.64.10.10', $this->visitorIpSentFor(['REMOTE_ADDR' => '172.64.10.10'], ['CF-Connecting-IP' => 'not-an-ip']));
        $this->assertSame('192.0.2.50', $this->visitorIpSentFor(['REMOTE_ADDR' => '192.0.2.50'], ['CF-Connecting-IP' => '203.0.113.9']));
    }

    public function test_trusted_proxies_set_to_cloudflare_reads_x_forwarded_for_only_from_cloudflare(): void
    {
        TrustProxies::at(VisitorIp::trustedProxies('cloudflare'));

        $this->assertSame('1.2.3.4', $this->visitorIpSentFor(['REMOTE_ADDR' => '104.16.1.1'], ['X-Forwarded-For' => '1.2.3.4']));
        $this->assertSame('192.0.2.50', $this->visitorIpSentFor(['REMOTE_ADDR' => '192.0.2.50'], ['X-Forwarded-For' => '1.2.3.4']));
    }

    public function test_cf_ipcountry_is_believed_only_from_cloudflare(): void
    {
        $request = \Illuminate\Http\Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '104.16.1.1', 'HTTP_CF_IPCOUNTRY' => 'cm']);
        $this->assertSame('CM', VisitorIp::country($request));

        $request = \Illuminate\Http\Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '198.51.100.20', 'HTTP_CF_IPCOUNTRY' => 'CM']);
        $this->assertNull(VisitorIp::country($request));

        $request = \Illuminate\Http\Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '104.16.1.1', 'HTTP_CF_IPCOUNTRY' => 'XX']);
        $this->assertNull(VisitorIp::country($request), 'XX is Cloudflare for "unknown"');
    }

    public function test_the_sites_form_limit_counts_a_whole_ipv6_64(): void
    {
        $this->fakeHq();
        config(['site.limits.forms_per_minute' => 3]);

        foreach (['2001:db8:1:2::1', '2001:db8:1:2::2', '2001:db8:1:2::3'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->from('/feedback')->post('/feedback', self::FORM)->assertRedirect('/feedback');
        }

        // Another address in the same /64: still the same visitor
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:2:aaaa::9'])->from('/feedback')->post('/feedback', self::FORM)->assertStatus(429);
        // Another /64, or another IPv4 address: a different visitor
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:3::1'])->from('/feedback')->post('/feedback', self::FORM)->assertRedirect('/feedback');
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->from('/feedback')->post('/feedback', self::FORM)->assertRedirect('/feedback');
    }

    public function test_the_sites_form_limit_counts_cloudflare_visitors_separately(): void
    {
        $this->fakeHq();
        config(['site.limits.forms_per_minute' => 2]);
        $edge = ['REMOTE_ADDR' => '104.16.1.1'];

        $this->withServerVariables($edge)->from('/feedback')->post('/feedback', self::FORM, ['CF-Connecting-IP' => '203.0.113.1'])->assertRedirect();
        $this->withServerVariables($edge)->from('/feedback')->post('/feedback', self::FORM, ['CF-Connecting-IP' => '203.0.113.1'])->assertRedirect();
        $this->withServerVariables($edge)->from('/feedback')->post('/feedback', self::FORM, ['CF-Connecting-IP' => '203.0.113.1'])->assertStatus(429);
        // Another visitor behind the same Cloudflare edge is not blocked
        $this->withServerVariables($edge)->from('/feedback')->post('/feedback', self::FORM, ['CF-Connecting-IP' => '203.0.113.2'])->assertRedirect();
    }
}
