<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gzip for what PHP answers with text: pages, every Livewire update (the Mail page's are tens to hundreds of KB of
 * HTML in JSON), the site API, and the scripts PHP serves itself (Livewire's own, 226 KB raw and 75 KB gzipped).
 *
 * Two doors. Answers with their content in memory are compressed on Laravel's RequestHandled event, registered once
 * every provider has booted (AppServiceProvider): after Livewire's own listener, which writes @assets and its scripts
 * into the page there, after every middleware. Files PHP serves from disk (BinaryFileResponse) are compressed here, as
 * the global middleware, since they need a new response object.
 * The live web root compresses nothing (no mod_deflate rules in its .htaccess, which the deployer can't safely change),
 * so on a phone this was most of the wait for each update. Text compresses 4 to 8 times.
 *
 * Left alone: browsers that don't ask for gzip, HEAD, empty or small answers (under 1 KB), streamed downloads,
 * anything already encoded (so Apache's or nginx's own compression, if one is ever switched on, is never doubled:
 * both pass an encoded answer through), answers carrying an ETag, and any type that isn't text. When PHP's own
 * zlib.output_compression is on, PHP compresses already and this does nothing.
 */
final class CompressResponses
{
    private const MIN_BYTES = 1024;

    /** Files PHP serves (BinaryFileResponse) are compressed up to this size; larger ones go as they are */
    private const MAX_FILE_BYTES = 2 * 1024 * 1024;

    private const LEVEL = 5;

    private const TYPES = [
        'text/html', 'text/plain', 'text/css', 'text/javascript', 'text/markdown', 'text/xml', 'text/csv',
        'application/json', 'application/javascript', 'application/xml', 'application/manifest+json', 'image/svg+xml',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (! $response instanceof BinaryFileResponse) {
            return $response; // RequestHandled does these (see above)
        }

        try {
            return self::compress($request, $response);
        } catch (\Throwable) {
            return $response; // never fail an answer over its compression
        }
    }

    /** RequestHandled: answers with their content in memory, compressed in place */
    public static function handled(\Illuminate\Foundation\Http\Events\RequestHandled $event): void
    {
        if ($event->response instanceof BinaryFileResponse) {
            return;
        }
        try {
            self::compress($event->request, $event->response);
        } catch (\Throwable) {
            // never fail an answer over its compression
        }
    }

    public static function compress(Request $request, Response $response): Response
    {
        if (! self::wanted($request, $response)) {
            return $response;
        }

        if ($response instanceof BinaryFileResponse) {
            $file = $response->getFile();
            $size = $file->getSize();
            if ($request->headers->has('Range') || $size < self::MIN_BYTES || $size > self::MAX_FILE_BYTES) {
                return $response;
            }
            $plain = new Response('', $response->getStatusCode());
            $plain->headers = clone $response->headers;
            $plain->headers->remove('Accept-Ranges');
            $content = (string) file_get_contents($file->getPathname());
            $response = $plain;
        } else {
            $content = (string) $response->getContent();
            if (strlen($content) < self::MIN_BYTES) {
                return $response;
            }
        }

        $gzip = gzencode($content, self::LEVEL);
        if ($gzip === false) {
            return $response;
        }
        $response->setContent($gzip);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($gzip));
        $response->setVary('Accept-Encoding', false);

        return $response;
    }

    private static function wanted(Request $request, Response $response): bool
    {
        if ($response instanceof StreamedResponse || $request->isMethod('HEAD')) {
            return false;
        }
        $status = $response->getStatusCode();
        if ($status < 200 || in_array($status, [204, 206, 304], true)) {
            return false;
        }
        if (! function_exists('gzencode') || filter_var(ini_get('zlib.output_compression'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }
        $headers = $response->headers;
        if ($headers->has('Content-Encoding') || $headers->has('ETag')) {
            return false;
        }
        $type = strtolower(trim(explode(';', (string) $headers->get('Content-Type', ''))[0]));
        if (! in_array($type, self::TYPES, true)) {
            return false;
        }
        $accept = AcceptHeader::fromString((string) $request->headers->get('Accept-Encoding', ''));
        $gzip = $accept->get('gzip') ?? $accept->get('*');

        return $gzip !== null && $gzip->getQuality() > 0;
    }
}
