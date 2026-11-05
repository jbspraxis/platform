<?php

namespace App\Support\CompanyProfile\Deck;

/**
 * The deck's design in one file: the slide, the grid, the palette and the type scale.
 *
 * It is the sibling of the book's App\Support\CompanyProfile\Layout — same brand, same restraint, a
 * different format. 16:9 at 960 × 540pt, twelve columns on a 16pt gutter, the brand's colours from
 * docs/BRAND.md section 7 and the two faces every machine already has.
 *
 * Change a number here and every slide moves with it. Nothing else in the deck carries a measurement.
 */
final class Style
{
    /** 16:9, in points. PowerPoint's own widescreen slide is 13.333 × 7.5in, which is exactly this. */
    public const WIDTH = 960.0;

    public const HEIGHT = 540.0;

    public const MARGIN_X = 64.0;

    public const MARGIN_TOP = 54.0;

    public const MARGIN_BOTTOM = 52.0;

    public const COLUMNS = 12;

    public const GUTTER = 16.0;

    public const CONTENT_WIDTH = self::WIDTH - (2 * self::MARGIN_X);

    /** Where the Praxis rule turns teal on the problem slides: the column the answer starts in. */
    public const SPLIT_COLUMNS = 5;

    /**
     * The floor. Nothing on a slide is ever set smaller than this, in either language: French runs 15 to
     * 20 per cent longer than English and the deck is not allowed to buy the difference by shrinking. It
     * buys it with the layout — fewer items on a slide — and the tests hold the line in both languages.
     *
     * Three floors, because three kinds of thing are set: text a client reads, the labels that name a
     * block, and the running reference in the foot, which is chrome and is meant to be ignorable.
     */
    public const FLOOR = 11.5;

    public const LABEL_FLOOR = 10.0;

    public const CHROME_FLOOR = 8.5;

    /** The roles a client actually reads: none of them is ever set below FLOOR. */
    public const READING_ROLES = ['cover', 'statement', 'title', 'subhead', 'domain', 'lead', 'body', 'small'];

    /* ---- the palette (docs/BRAND.md section 7) ---- */

    public const INK = '0C142B';

    public const PETROL = '044B6A';

    public const DEEP_TEAL = '08707C';

    public const TEAL = '0C8B9B';

    public const BRIGHT_TEAL = '0BB8C4';

    public const PAPER = 'FFFFFF';

    public const MIST = 'F2F5F7';

    public const TEAL_TINT = 'E3F2F3';

    public const HAIRLINE = 'C5CCD6';

    public const MUTED = '5E6679';

    public const SLATE = '4A5468';

    /** On Ink: white, a solid secondary (never opacity) and a decorative rule. */
    public const ON_INK = 'FFFFFF';

    public const ON_INK_SECONDARY = 'BBBDC4';

    public const ON_INK_RULE = '2E3549';

    /* ---- the two faces (docs/company-profile/DECK.md, "Fonts") ---- */

    public const SERIF = 'Georgia';

    public const SANS = 'Arial';

    /**
     * The type scale. Each role gives its face, its size and its leading, all in points.
     *
     * @return array{face: string, font: string, size: float, leading: float, cut: string, track: int}
     */
    public static function type(string $role): array
    {
        $scale = [
            //                face      size  leading  cut        tracking (1/100 pt per em)
            'cover' => ['serif', 48.0, 56.0, 'regular', -48],
            'statement' => ['serif', 37.0, 45.0, 'regular', -37],
            'title' => ['serif', 29.0, 35.0, 'regular', 0],
            'subhead' => ['serif', 19.5, 24.5, 'regular', 0],
            'domain' => ['serif', 16.5, 21.0, 'regular', 0],
            'lead' => ['sans', 16.0, 24.0, 'regular', 0],
            'body' => ['sans', 12.5, 19.0, 'regular', 0],
            'small' => ['sans', 11.5, 17.0, 'regular', 0],
            'label' => ['sans', 11.5, 17.0, 'bold', 0],
            'caption' => ['sans', 10.0, 14.0, 'regular', 0],
            'meta' => ['sans', 8.5, 12.0, 'regular', 0],
        ];

        $row = $scale[$role] ?? $scale['body'];

        return [
            'face' => $row[0],
            'font' => $row[0] === 'serif' ? self::SERIF : self::SANS,
            'size' => $row[1],
            'leading' => $row[2],
            'cut' => $row[3],
            'track' => $row[4],
        ];
    }

    /** The width of n columns with their gutters, rounded down so a row never overflows by a hundredth. */
    public static function col(int $n): float
    {
        $column = (self::CONTENT_WIDTH - ((self::COLUMNS - 1) * self::GUTTER)) / self::COLUMNS;

        return floor((($n * $column) + (($n - 1) * self::GUTTER)) * 100) / 100;
    }

    /** The left edge of column n, counting from 1. */
    public static function x(int $n): float
    {
        return self::MARGIN_X + ($n - 1) * ((self::CONTENT_WIDTH - ((self::COLUMNS - 1) * self::GUTTER)) / self::COLUMNS + self::GUTTER);
    }

    /** The content width split into $count equal columns with a gutter between them. */
    public static function even(int $count): float
    {
        $count = max($count, 1);

        return floor(((self::CONTENT_WIDTH - (($count - 1) * self::GUTTER)) / $count) * 100) / 100;
    }

    /** The left edge of the $i-th (0-based) of $count equal columns. */
    public static function evenX(int $i, int $count): float
    {
        return self::MARGIN_X + $i * (self::even($count) + self::GUTTER);
    }

    /** The bottom of the live area: nothing a slide draws may cross it. */
    public static function floorY(): float
    {
        return self::HEIGHT - self::MARGIN_BOTTOM;
    }
}
