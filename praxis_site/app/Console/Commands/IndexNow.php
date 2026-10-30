<?php

namespace App\Console\Commands;

use App\Support\HqApi;
use App\Support\Sitemap;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * php artisan site:indexnow [addresses…]: tells the IndexNow engines (Bing, Yandex, Seznam, Naver…) which addresses on
 * jbspraxis.com are new or changed; by default every address in the sitemap (App\Support\Sitemap, built on
 * https://jbspraxis.com whatever APP_URL says). Run it after a deploy that adds or changes pages.
 *
 * Nothing is sent while the site is not indexed (SITE_NOINDEX). The engines check the key at
 * https://jbspraxis.com/{key}.txt (config site.indexnow; PageController::indexNowKey).
 */
class IndexNow extends Command
{
    protected $signature = 'site:indexnow
        {urls?* : Addresses or paths on jbspraxis.com, e.g. /careers (default: every address in the sitemap)}
        {--dry-run : List the addresses without sending them}';

    protected $description = 'Tell IndexNow (Bing, Yandex…) which addresses on the site are new or changed';

    /** IndexNow takes at most 10,000 addresses per request */
    private const PER_REQUEST = 10000;

    public function handle(HqApi $api): int
    {
        if (config('site.noindex')) {
            $this->warn('The site is not indexed (SITE_NOINDEX is on): nothing was sent to IndexNow.');

            return self::SUCCESS;
        }

        $key = (string) config('site.indexnow.key');
        if (! preg_match('/^[A-Za-z0-9-]{8,128}$/', $key)) {
            $this->error('The IndexNow key (INDEXNOW_KEY) must be 8 to 128 letters, digits or dashes: nothing was sent.');

            return self::FAILURE;
        }

        $host = strtolower((string) config('site.indexnow.host'));
        $root = 'https://' . $host;
        $given = (array) $this->argument('urls');
        $urls = $given === []
            ? array_column(Sitemap::entries($api, $root, fn () => $this->warn('The Insights could not be read from HQ: only the pages are listed.')), 'loc')
            : $this->onHost($given, $root, $host);
        if ($urls === null) {
            return self::FAILURE;
        }
        $urls = array_values(array_unique($urls));

        if ($this->option('dry-run')) {
            foreach ($urls as $url) {
                $this->line($url);
            }
            $this->info(count($urls) . ' addresses; nothing was sent (--dry-run).');

            return self::SUCCESS;
        }

        foreach (array_chunk($urls, self::PER_REQUEST) as $chunk) {
            try {
                $response = Http::timeout(20)
                    ->withHeaders(['Content-Type' => 'application/json; charset=utf-8'])
                    ->post((string) config('site.indexnow.endpoint'), [
                        'host' => $host,
                        'key' => $key,
                        'keyLocation' => $root . '/' . $key . '.txt',
                        'urlList' => $chunk,
                    ]);
            } catch (ConnectionException $e) {
                $this->error('IndexNow could not be reached: ' . $e->getMessage());

                return self::FAILURE;
            }

            // 200: received; 202: received, the key is still being checked
            if (! in_array($response->status(), [200, 202], true)) {
                $this->error(match ($response->status()) {
                    400 => 'IndexNow refused the request as malformed (400).',
                    403 => "IndexNow did not accept the key (403): {$root}/{$key}.txt must show it.",
                    422 => "IndexNow refused the addresses (422): each must be on {$host}, and the key must match its file.",
                    429 => 'IndexNow asks to slow down (429): try again later.',
                    default => 'IndexNow answered HTTP ' . $response->status() . '.',
                });

                return self::FAILURE;
            }

            $this->info('Sent ' . count($chunk) . ' addresses to IndexNow'
                . ($response->status() === 202 ? ' (202: received; IndexNow is still checking the key).' : ' (200 OK).'));
        }

        return self::SUCCESS;
    }

    /** The given addresses and paths as addresses on $host; null (with the reason) when one is elsewhere */
    private function onHost(array $given, string $root, string $host): ?array
    {
        $urls = [];
        foreach ($given as $value) {
            $value = trim((string) $value);
            if (! preg_match('~^[a-z][a-z0-9+.-]*://~i', $value)) {
                $urls[] = $root . '/' . ltrim($value, '/');
            } elseif (preg_match('~^https?://~i', $value) && strtolower((string) parse_url($value, PHP_URL_HOST)) === $host) {
                $urls[] = $value;
            } else {
                $this->error("Only addresses on {$host} can be sent, not {$value}: nothing was sent.");

                return null;
            }
        }

        return $urls;
    }
}
