<?php

namespace App\Support\CompanyProfile;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMProcessingInstruction;
use RuntimeException;

/**
 * The bytes of an uploaded logo (docs/COMPANY_PROFILE.md section 2). Nothing here converts an image: no PHP on
 * this machine or on the live server has gd or imagick, so the browser makes the three renditions and this only
 * checks that what arrived really is what it says it is, and cleans an SVG before it is kept.
 */
final class LogoFiles
{
    /** A logo file is small; anything larger is a mistake or an attack */
    public const MAX_BYTES = 1500000;

    /** The elements php-svg-lib draws and a logo needs. Everything else is dropped. */
    private const SVG_ELEMENTS = [
        'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'defs', 'lineargradient', 'radialgradient', 'stop', 'title', 'desc', 'use', 'symbol', 'clippath',
    ];

    /** Presentation attributes only: no event handlers, and no reference to anywhere but a local id */
    private const SVG_ATTRIBUTES = [
        'id', 'class', 'd', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'width', 'height',
        'points', 'viewbox', 'transform', 'fill', 'fill-rule', 'fill-opacity', 'stroke', 'stroke-width',
        'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-dasharray', 'stroke-opacity',
        'opacity', 'offset', 'stop-color', 'stop-opacity', 'gradientunits', 'gradienttransform',
        'spreadmethod', 'fr', 'fx', 'fy', 'clip-path', 'clip-rule', 'xmlns', 'version', 'preserveaspectratio',
        'style', 'href', 'xlink:href',
    ];

    /** The references that may only point inside the file */
    private const SVG_REFERENCES = ['href', 'xlink:href', 'clip-path', 'fill', 'stroke'];

    /**
     * "data:image/png;base64,..." to the raw bytes, checked against what the media type promises.
     *
     * @param  list<string>  $allowed
     * @return array{bytes: string, media: string}
     */
    public static function decode(string $dataUri, array $allowed): array
    {
        $pattern = '#^data:(image/(?:png|jpeg|webp|svg\+xml));base64,([A-Za-z0-9+/=\s]+)$#';
        if (! preg_match($pattern, trim($dataUri), $m)) {
            throw new RuntimeException('That is not an image this screen can take.');
        }

        $media = $m[1];
        if (! in_array($media, $allowed, true)) {
            throw new RuntimeException('That image is the wrong kind for this slot.');
        }

        $bytes = base64_decode((string) preg_replace('/\s+/', '', $m[2]), true);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('That image could not be read.');
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new RuntimeException('That image is larger than 1.5 MB.');
        }
        if (! self::looksLike($media, $bytes)) {
            throw new RuntimeException('That file is not really ' . $media . '.');
        }

        return [
            'bytes' => $media === 'image/svg+xml' ? self::cleanSvg($bytes) : $bytes,
            'media' => $media,
        ];
    }

    /** The first bytes of the file itself, not what the upload claims */
    public static function looksLike(string $media, string $bytes): bool
    {
        return match ($media) {
            'image/png' => str_starts_with($bytes, "\x89PNG\r\n\x1a\n"),
            'image/jpeg' => str_starts_with($bytes, "\xFF\xD8\xFF"),
            'image/webp' => str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP',
            'image/svg+xml' => stripos($bytes, '<svg') !== false,
            default => false,
        };
    }

    /**
     * An SVG with nothing in it but shapes. Scripts, foreign objects, bitmaps, external references and every
     * event handler are dropped; what is left is what php-svg-lib draws and what a browser can show inline
     * without running anything.
     */
    public static function cleanSvg(string $svg): string
    {
        $svg = (string) preg_replace('/<!--.*?-->/s', '', $svg);
        $svg = (string) preg_replace('/<!DOCTYPE[^>]*>/i', '', $svg);

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $loaded = $document->loadXML($svg, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || ! $document->documentElement || strtolower($document->documentElement->localName) !== 'svg') {
            throw new RuntimeException('That SVG could not be read.');
        }

        self::scrub($document->documentElement);

        $out = $document->saveXML($document->documentElement);
        if (! is_string($out) || $out === '') {
            throw new RuntimeException('That SVG could not be read.');
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $out;
    }

    private static function scrub(DOMElement $element): void
    {
        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                if (! in_array(strtolower($child->localName), self::SVG_ELEMENTS, true)) {
                    $element->removeChild($child);

                    continue;
                }
                self::scrub($child);
            } elseif ($child instanceof DOMProcessingInstruction || $child instanceof DOMComment) {
                $element->removeChild($child);
            }
        }

        if ($element->attributes === null) {
            return;
        }

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = (string) $attribute->nodeValue;
            $keep = in_array($name, self::SVG_ATTRIBUTES, true) || str_starts_with($name, 'xmlns:');

            if ($keep && in_array($name, self::SVG_REFERENCES, true)) {
                $keep = ! preg_match('#^\s*(?:https?|data|javascript|file)\s*:#i', $value)
                    && ! preg_match('#url\(\s*["\']?\s*(?!\#)#i', $value);
            }
            if ($keep && $name === 'style') {
                $keep = ! preg_match('#url\(|expression|@import|javascript:#i', $value);
            }

            if (! $keep) {
                $element->removeAttribute($attribute->nodeName);
            }
        }
    }
}
