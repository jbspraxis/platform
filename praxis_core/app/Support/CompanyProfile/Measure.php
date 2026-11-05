<?php

namespace App\Support\CompanyProfile;

use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

/**
 * How many lines a piece of text takes in a given column, measured rather than guessed.
 *
 * The book decides where its own pages end (App\Support\CompanyProfile\Layout), which means it has to
 * know how tall a paragraph will be before dompdf lays it out. Estimating that from a character count is
 * close enough to look right in English and wrong in French, where the same paragraph runs 15 to 20 per
 * cent longer and a page quietly overflows onto a near-empty one. So the widths come from the same font
 * metrics dompdf itself will use, and the line breaking is the same greedy break on spaces.
 */
final class Measure
{
    private static ?self $instance = null;

    private FontMetrics $metrics;

    /** @var array<string, mixed> font handles, keyed by family and style */
    private array $fonts = [];

    public function __construct(?string $cache = null)
    {
        $cache ??= storage_path('framework/cache/dompdf');
        File::ensureDirectoryExists($cache);
        $cache = str_replace(DIRECTORY_SEPARATOR, '/', realpath($cache) ?: $cache);

        $dompdf = new Dompdf(new Options([
            'defaultFont' => 'Jakarta',
            'isRemoteEnabled' => false,
            'fontDir' => $cache,
            'fontCache' => $cache,
            'tempDir' => $cache,
            'chroot' => [$cache, Assets::root()],
        ]));

        $this->metrics = $dompdf->getFontMetrics();
        foreach (Assets::fonts() as $font) {
            $this->metrics->registerFont(
                ['family' => $font['family'], 'weight' => $font['weight'], 'style' => $font['style']],
                Assets::font($font['file'])
            );
        }
    }

    public static function instance(): self
    {
        return self::$instance ??= new self;
    }

    /** Only for tests, which build a profile more than once in a process. */
    public static function forget(): void
    {
        self::$instance = null;
    }

    /**
     * The number of lines $text takes, set in $family at $size across $width points. Words joined by a
     * no-break space are one word here, which is exactly what they are on the page.
     */
    public function lines(string $text, float $width, string $family, float $size, string $style = 'normal'): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        $font = $this->font($family, $style);
        if ($font === null) {
            // No metrics: fall back to about half the point size per character, and round up.
            return max(1, (int) ceil(mb_strlen($text) / max(1.0, $width / ($size * 0.52))));
        }

        $space = $this->metrics->getTextWidth(' ', $font, $size);
        $lines = 1;
        $used = 0.0;

        foreach (preg_split('/ +/u', $text) ?: [] as $word) {
            $wordWidth = $this->metrics->getTextWidth($word, $font, $size);
            $needed = $used > 0.0 ? $space + $wordWidth : $wordWidth;

            if ($used > 0.0 && $used + $needed > $width) {
                $lines++;
                $used = $wordWidth;

                continue;
            }

            $used += $needed;
        }

        return $lines;
    }

    /** The height of a paragraph: its lines at the leading the stylesheet gives them. */
    public function height(string $text, float $width, string $family, float $size, float $leading, string $style = 'normal'): float
    {
        return $this->lines($text, $width, $family, $size, $style) * $leading;
    }

    public function width(string $text, string $family, float $size, string $style = 'normal'): float
    {
        $font = $this->font($family, $style);

        return $font === null ? mb_strlen($text) * $size * 0.52 : $this->metrics->getTextWidth($text, $font, $size);
    }

    private function font(string $family, string $style): mixed
    {
        return $this->fonts[$family.'|'.$style] ??= $this->metrics->getFont($family, $this->metrics->getType($style));
    }
}
