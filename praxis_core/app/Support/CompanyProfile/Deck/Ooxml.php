<?php

namespace App\Support\CompanyProfile\Deck;

/** The small conversions every part of the deck's XML needs. */
final class Ooxml
{
    /** English Metric Units: 12,700 to the point, which is how OOXML states every position and size. */
    public const EMU = 12700;

    public static function emu(float $points): int
    {
        return (int) round($points * self::EMU);
    }

    /** OOXML states a type size and a leading in hundredths of a point. */
    public static function hundredths(float $points): int
    {
        return (int) round($points * 100);
    }

    public static function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * XML carries no control characters below the space except tab, newline and return, and a stray one
     * from imported copy makes the whole part unreadable rather than showing as a wrong glyph.
     */
    public static function clean(string $text): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? $text;
    }

    public static function declaration(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\r\n";
    }
}
