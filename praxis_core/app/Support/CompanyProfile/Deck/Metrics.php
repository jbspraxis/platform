<?php

namespace App\Support\CompanyProfile\Deck;

/**
 * How wide a line of the deck's text is, and how many lines a paragraph takes in a given box.
 *
 * The deck decides for itself whether a slide fits, exactly as the book does (docs/company-profile/
 * DESIGN.md, "Measuring, not guessing"), and for the same reason: French runs 15 to 20 per cent longer
 * than English, and counting characters is close enough in one language and wrong in the other. A slide
 * that does not fit is not a slide that looks a little tight — PowerPoint is given `<a:noAutofit/>`, so
 * it would simply run off the bottom.
 *
 * The widths come from resources/company-profile/deck/deck-metrics.json, read once. Nothing here opens a
 * font file: the deck is set in two faces every machine already has, and the metrics are committed.
 *
 * PowerPoint kerns and tracks a little tighter than a sum of advance widths, so a measured line is a hair
 * wider than the drawn one. That is the safe direction to be wrong in, and SAFETY adds a little more.
 */
final class Metrics
{
    /** Measured lines are treated as this much wider than they are, so a near-miss breaks early. */
    public const SAFETY = 1.02;

    /** What an unknown character is assumed to cost, in thousandths of an em. */
    private const UNKNOWN = 500;

    private static ?self $instance = null;

    /** @var array<string, array<string, array<int, int>>> */
    private array $faces;

    public function __construct(?string $file = null)
    {
        $file ??= resource_path('company-profile/deck/deck-metrics.json');
        $json = json_decode((string) file_get_contents($file), true);
        $this->faces = $json['faces'] ?? [];
    }

    public static function instance(): self
    {
        return self::$instance ??= new self;
    }

    public static function forget(): void
    {
        self::$instance = null;
    }

    /** The width of a string in points, in the given face at the given size. */
    public function width(string $text, string $face = 'sans', float $size = 12.0, string $cut = 'regular'): float
    {
        $widths = $this->faces[$face][$cut] ?? $this->faces['sans']['regular'] ?? [];
        $thousandths = 0;

        foreach ($this->codepoints($text) as $c) {
            $thousandths += $widths[$c] ?? self::UNKNOWN;
        }

        return $thousandths / 1000 * $size * self::SAFETY;
    }

    /**
     * How many lines the text takes in a box this wide, broken on spaces the way a renderer breaks it.
     * A single word longer than the box takes a line of its own rather than looping for ever.
     */
    public function lines(string $text, float $boxWidth, string $face = 'sans', float $size = 12.0, string $cut = 'regular'): int
    {
        // Only real spaces break a line: a no-break space is a character here, which is the whole point of
        // the one Typography writes between the last two words of a paragraph.
        $text = trim(preg_replace('/[ 	
]+/u', ' ', $text) ?? '');
        if ($text === '' || $boxWidth <= 0) {
            return $text === '' ? 0 : 1;
        }

        $space = $this->width(' ', $face, $size, $cut);
        $lines = 1;
        $at = 0.0;

        foreach (explode(' ', $text) as $word) {
            $w = $this->width($word, $face, $size, $cut);
            if ($at === 0.0) {
                $at = $w;

                continue;
            }
            if ($at + $space + $w <= $boxWidth) {
                $at += $space + $w;
            } else {
                $lines++;
                $at = $w;
            }
        }

        return $lines;
    }

    /** The height a paragraph takes, in points: its lines at the leading it is set on. */
    public function height(string $text, float $boxWidth, string $face, float $size, float $leading, string $cut = 'regular'): float
    {
        return $this->lines($text, $boxWidth, $face, $size, $cut) * $leading;
    }

    /** @return list<int> */
    private function codepoints(string $text): array
    {
        $out = [];
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $code = mb_ord($char, 'UTF-8');
            if ($code !== false) {
                $out[] = $code;
            }
        }

        return $out;
    }
}
