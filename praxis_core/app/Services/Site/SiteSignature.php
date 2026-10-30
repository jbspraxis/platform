<?php

namespace App\Services\Site;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The request signature of the site API (docs/SITE_API.md section 6.1): lowercase hex of
 * hmac_sha256(secret, canonical), where canonical is these 9 lines joined by "\n":
 * METHOD, path with query exactly as sent, timestamp, nonce, visitor IP, locale, key id,
 * media type (Content-Type without parameters, lower case; empty when there is no body) and body hash.
 *
 * Body hash: sha256_hex of the raw body (JSON or empty). PHP never sees the raw body of a multipart
 * request, so for multipart/form-data it is sha256_hex of one line per field, sorted by field name
 * (byte order), joined by "\n": "t:<name>=<base64 of the value>" for text, "f:<name>=<sha256 hex of
 * the file bytes>" for a file. A form that can't be hashed that way (an array or non-text field, a
 * text field named like a file field, a file that didn't arrive) has no hash: the request is refused.
 */
final class SiteSignature
{
    public const MULTIPART = 'multipart/form-data';

    public static function canonical(string $method, string $pathWithQuery, string $timestamp, string $nonce, string $visitorIp, string $locale, string $keyId, string $mediaType, string $bodyHash): string
    {
        return implode("\n", [strtoupper($method), $pathWithQuery, $timestamp, $nonce, $visitorIp, $locale, $keyId, $mediaType, $bodyHash]);
    }

    public static function sign(string $secret, string $canonical): string
    {
        return hash_hmac('sha256', $canonical, $secret);
    }

    /** The Content-Type without parameters, lower case; empty when the request has no body */
    public static function mediaType(Request $request): string
    {
        $type = strtolower(trim(explode(';', (string) $request->headers->get('Content-Type', ''), 2)[0]));
        if ($type === self::MULTIPART) {
            return $type; // PHP has already parsed the form, so the raw body reads as empty
        }

        return (string) $request->getContent() === '' ? '' : $type;
    }

    /** sha256_hex of the raw body, or of the form for multipart requests; null when the form can't be signed */
    public static function bodyHash(Request $request): ?string
    {
        if (self::mediaType($request) === self::MULTIPART) {
            return self::multipartHash($request->request->all(), $request->files->all());
        }

        return hash('sha256', (string) $request->getContent());
    }

    /**
     * @param  array<string, mixed>  $fields  text fields: each must be text (a string or a number)
     * @param  array<string, mixed>  $files   file fields: each must be one uploaded file
     * @return string|null  null when the form is ambiguous (array or non-text field, a text field named like
     *                      a file field, a list of files or a file that didn't arrive)
     */
    public static function multipartHash(array $fields, array $files): ?string
    {
        $lines = [];
        foreach ($files as $name => $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                return null;
            }
            $lines[(string) $name] = 'f:' . $name . '=' . hash_file('sha256', $file->getRealPath());
        }
        foreach ($fields as $name => $value) {
            if (!(is_string($value) || is_int($value) || is_float($value)) || array_key_exists((string) $name, $lines)) {
                return null;
            }
            $lines[(string) $name] = 't:' . $name . '=' . base64_encode((string) $value);
        }
        ksort($lines, SORT_STRING);

        return hash('sha256', implode("\n", $lines));
    }
}
