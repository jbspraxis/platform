<?php

namespace App\Support\Campaigns\Outreach;

use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads a prospect's website for Claude (owner's decision 7, 3 Nov 2026), safely: the address comes from a
 * spreadsheet, so it is treated as hostile.
 *
 *   - http or https only, ports 80 and 443 only, no user name or password in the address;
 *   - the name is resolved first and every address it gives must be public: private, loopback, link-local,
 *     reserved and shared addresses are refused, and the connection is pinned to the address checked (so DNS
 *     can't change between the check and the request);
 *   - redirects are followed by hand, at most 3, each checked the same way;
 *   - 4 seconds to connect, 8 in all, at most 512 KB read; only HTML or plain text;
 *   - only the words are kept (title, description, headings and text; never scripts, styles or links), at most
 *     6,000 characters, and they go to Claude as material, never as instructions.
 *
 *   ['text' => '…', 'note' => null]   or   ['text' => null, 'note' => 'The website didn’t answer in time.']
 */
class Website
{
    public const MAX_BYTES = 524288;

    public const MAX_CHARS = 6000;

    public const MAX_REDIRECTS = 3;

    public static function make(): self
    {
        return app(self::class);
    }

    /** https://kilo.test for "kilo.test" or "www.kilo.test/about"; null when it can't be a website address */
    public static function normalise(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (! preg_match('~^[a-z][a-z0-9+.-]*://~i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }
        $parts = parse_url($url);
        if (! $parts || ! isset($parts['host']) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || mb_strlen($url) > 500
            || ! preg_match('/^[a-z0-9.-]+$/i', $parts['host']) || ! str_contains($parts['host'], '.')) {
            return null;
        }

        return $url;
    }

    /** @return array{text: ?string, note: ?string} */
    public function read(?string $url): array
    {
        $url = self::normalise($url);
        if ($url === null) {
            return ['text' => null, 'note' => 'The website address isn’t one HQ can open (http or https only).'];
        }
        // The connection is pinned to the checked address with curl; without it, HQ doesn't read websites at all
        if (! extension_loaded('curl')) {
            return ['text' => null, 'note' => 'This server can’t read websites, so Claude used What we found only.'];
        }

        try {
            for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
                [$host, $port, $ip] = $this->check($url);
                $response = $this->fetch($url, $host, $port, $ip);
                $status = $response['status'];
                if ($status >= 300 && $status < 400 && $response['location'] !== null) {
                    $next = self::absolute($response['location'], $url);
                    if ($next === null) {
                        return ['text' => null, 'note' => 'The website sent HQ to an address it can’t open.'];
                    }
                    $url = $next;

                    continue;
                }
                if ($status < 200 || $status >= 300) {
                    return ['text' => null, 'note' => "The website answered with an error ({$status})."];
                }
                if (! preg_match('~^(text/html|application/xhtml\+xml|text/plain)\b~i', $response['type'])) {
                    return ['text' => null, 'note' => 'The website’s page isn’t text HQ can read.'];
                }
                $text = self::words($response['body'], $response['type']);

                return $text === ''
                    ? ['text' => null, 'note' => 'The website’s page had no words HQ could read (it may need a browser to show them).']
                    : ['text' => $text, 'note' => null];
            }

            return ['text' => null, 'note' => 'The website sent HQ round too many redirects.'];
        } catch (UnsafeAddress $e) {
            return ['text' => null, 'note' => $e->getMessage()];
        } catch (ConnectionException) {
            return ['text' => null, 'note' => 'The website didn’t answer in time.'];
        } catch (Throwable $e) {
            report($e);

            return ['text' => null, 'note' => 'HQ couldn’t read the website.'];
        }
    }

    /**
     * The host, port and the one public address the request goes to.
     *
     * @throws UnsafeAddress
     */
    private function check(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeAddress('The website address isn’t one HQ can open (http or https only).');
        }
        if (! in_array($port, [80, 443], true)) {
            throw new UnsafeAddress('HQ only opens websites on the usual ports.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) || $host === 'localhost' || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new UnsafeAddress('HQ only opens websites by their public name.');
        }

        $ips = $this->resolve($host);
        if (! $ips) {
            throw new UnsafeAddress('The website’s name doesn’t exist.');
        }
        foreach ($ips as $ip) {
            if (! self::isPublic($ip)) {
                throw new UnsafeAddress('The website points to a private address, so HQ didn’t open it.');
            }
        }

        return [$host, $port, $ips[0]];
    }

    /** The addresses a name resolves to (IPv4 and IPv6) */
    protected function resolve(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (! empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    /** A public address: not private, loopback, link-local, reserved, shared (100.64/10) or documentation */
    public static function isPublic(string $ip): bool
    {
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | (defined('FILTER_FLAG_GLOBAL_RANGE') ? FILTER_FLAG_GLOBAL_RANGE : 0);
        if (filter_var($ip, FILTER_VALIDATE_IP, $flags) === false) {
            return false;
        }
        // IPv4 written inside IPv6 (::ffff:10.0.0.1) and the ranges older PHP lets through
        if (preg_match('/^::ffff:/i', $ip)) {
            return self::isPublic(substr($ip, 7));
        }
        $long = ip2long($ip);
        if ($long !== false) {
            foreach (['100.64.0.0/10', '192.0.0.0/24', '198.18.0.0/15', '0.0.0.0/8'] as $cidr) {
                [$net, $bits] = explode('/', $cidr);
                $mask = -1 << (32 - (int) $bits);
                if (($long & $mask) === (ip2long($net) & $mask)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @return array{status: int, type: string, location: ?string, body: string} */
    protected function fetch(string $url, string $host, int $port, string $ip): array
    {
        $response = Http::withOptions([
            'allow_redirects' => false,
            'connect_timeout' => 4,
            'timeout' => 8,
            'stream' => true,
            'curl' => [
                CURLOPT_RESOLVE => [$host . ':' . $port . ':' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)],
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            ],
        ])->withHeaders([
            'User-Agent' => 'JBS Praxis HQ (reads one page for a salesperson; https://jbspraxis.com)',
            'Accept' => 'text/html,application/xhtml+xml,text/plain;q=0.8',
            'Accept-Language' => 'en, fr;q=0.8',
        ])->get($url);

        $body = '';
        $stream = $response->toPsrResponse()->getBody();
        while (! $stream->eof() && strlen($body) < self::MAX_BYTES) {
            $chunk = $stream->read(8192);
            if ($chunk === '') {
                break;
            }
            $body .= $chunk;
        }
        $stream->close();

        return [
            'status' => $response->status(),
            'type' => (string) $response->header('Content-Type'),
            'location' => $response->header('Location') ?: null,
            'body' => substr($body, 0, self::MAX_BYTES),
        ];
    }

    /** A redirect's address made whole, if it is one HQ may open */
    private static function absolute(string $location, string $base): ?string
    {
        $location = trim($location);
        if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $location)) {
            return self::normalise($location) !== null && preg_match('~^https?://~i', $location) ? $location : null;
        }
        $b = parse_url($base);
        $root = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($location, '//')) {
            return self::normalise($b['scheme'] . ':' . $location);
        }
        if (str_starts_with($location, '/')) {
            return $root . $location;
        }
        $dir = isset($b['path']) ? preg_replace('~/[^/]*$~', '/', $b['path']) : '/';

        return $root . $dir . $location;
    }

    /** The page's words: title, description, then the visible text, without scripts, styles, menus' links or markup */
    public static function words(string $body, string $type = 'text/html'): string
    {
        $charset = preg_match('/charset=([\w-]+)/i', $type, $m) ? $m[1] : null;
        if (! $charset && preg_match('/<meta[^>]+charset=["\']?([\w-]+)/i', $body, $m)) {
            $charset = $m[1];
        }
        if ($charset && strtolower($charset) !== 'utf-8') {
            $converted = @mb_convert_encoding($body, 'UTF-8', $charset);
            $body = is_string($converted) ? $converted : $body;
        }
        if (! mb_check_encoding($body, 'UTF-8')) {
            $body = mb_convert_encoding($body, 'UTF-8', 'Windows-1252');
        }

        if (stripos($type, 'text/plain') === 0) {
            $text = $body;
        } else {
            $doc = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $doc->loadHTML('<?xml encoding="UTF-8">' . $body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xpath = new DOMXPath($doc);
            foreach (iterator_to_array($xpath->query('//script|//style|//noscript|//template|//svg|//iframe|//form|//nav|//footer')) as $node) {
                $node->parentNode?->removeChild($node);
            }
            $title = trim((string) $xpath->evaluate('string(//title)'));
            $description = trim((string) $xpath->evaluate('string(//meta[@name="description"]/@content)'));
            $bodyNode = $xpath->query('//body')->item(0);
            $main = '';
            if ($bodyNode) {
                foreach ($xpath->query('.//h1|.//h2|.//h3|.//p|.//li|.//td', $bodyNode) as $node) {
                    $line = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
                    if (mb_strlen($line) >= 3) {
                        $main .= $line . "\n";
                    }
                }
                if (trim($main) === '') {
                    $main = $bodyNode->textContent;
                }
            }
            $text = ($title !== '' ? $title . "\n" : '') . ($description !== '' ? $description . "\n" : '') . $main;
        }

        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? '';
        $text = preg_replace("/\n\s*\n+/u", "\n", $text) ?? '';
        $lines = array_values(array_unique(array_filter(array_map('trim', explode("\n", $text)), fn ($l) => $l !== '')));

        return mb_substr(trim(implode("\n", $lines)), 0, self::MAX_CHARS);
    }
}
