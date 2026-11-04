<?php

namespace Tests\Unit\Letters;

use App\Support\Letters\Qr;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The QR code (docs/DOCUMENTS.md section 7), written in PHP because the live server has neither a QR library
 * nor the gd extension.
 *
 * The strongest check here reads a code back: the format information, the mask, the zigzag the data is laid
 * out in and the bytes themselves, all the way to the text that went in. It is done for version 1, whose data
 * is one block, so the test needs nothing of the error-correction block structure.
 *
 * The encoder was also checked against the python "qrcode" library while it was written: the interleaved
 * codewords were identical for every version from 1 to 10, and the whole matrix for versions 5 and above.
 */
class QrTest extends TestCase
{
    public function test_a_code_reads_back_as_the_text_that_went_into_it(): void
    {
        // Version 1 at level M holds 14 bytes, which is what this reader knows
        foreach (['A', 'JBS/RES/2026', 'jbspraxis.com'] as $text) {
            $this->assertSame($text, $this->readVersionOne(Qr::matrix($text)), "the code for '{$text}' reads back");
        }
    }

    public function test_the_version_grows_with_the_text_and_stops_at_ten(): void
    {
        $url = fn (int $length) => 'https://jbspraxis.com/verify/' . str_repeat('a', $length);

        // 17 + 4 × version modules a side
        $this->assertSame(21, count(Qr::matrix('A')));                 // version 1
        $this->assertSame(33, count(Qr::matrix($url(32))));            // version 4
        $this->assertSame(53, count(Qr::matrix($url(130))));           // version 9
        $this->assertSame(57, count(Qr::matrix($url(180))));           // version 10

        // The verify address is 77 characters at most, far inside this; past version 10 it says so
        $this->expectException(RuntimeException::class);
        Qr::matrix($url(400));
    }

    public function test_the_finder_patterns_timing_and_dark_module_are_where_they_belong(): void
    {
        $matrix = Qr::matrix('https://jbspraxis.com/verify/' . str_repeat('b', 40));
        $size = count($matrix);

        foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$y, $x]) {
            // The ring is dark, the gap inside it light, the square in the middle dark
            $this->assertTrue($matrix[$y][$x] && $matrix[$y][$x + 6] && $matrix[$y + 6][$x]);
            $this->assertFalse($matrix[$y + 1][$x + 1]);
            $this->assertTrue($matrix[$y + 3][$x + 3]);
        }

        // The timing patterns alternate along row 6 and column 6
        for ($i = 8; $i < $size - 8; $i++) {
            $this->assertSame($i % 2 === 0, $matrix[6][$i], "the timing pattern at column {$i}");
            $this->assertSame($i % 2 === 0, $matrix[$i][6], "the timing pattern at row {$i}");
        }

        $this->assertTrue($matrix[$size - 8][8], 'the module that is always dark');
    }

    public function test_the_svg_is_squares_only_with_the_quiet_zone_around_it(): void
    {
        $svg = Qr::svg('https://jbspraxis.com/verify/7f3a9c2e1b4d6a8f0c5e2d7b9a1f3c6e', 22.0);

        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg"', $svg);
        $this->assertStringContainsString('width="22mm" height="22mm"', $svg);
        // 33 modules plus the 4 of quiet zone the standard asks for on each side
        $this->assertStringContainsString('viewBox="0 0 41 41"', $svg);
        $this->assertStringContainsString('fill="#0C142B"', $svg);
        // Nothing that needs a font, an image or a script: php-svg-lib draws rectangles and nothing else
        $this->assertStringNotContainsString('<text', $svg);
        $this->assertStringNotContainsString('<image', $svg);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', Qr::dataUri('https://jbspraxis.com/verify/x'));
    }

    public function test_the_same_text_always_gives_the_same_code(): void
    {
        $text = 'https://jbspraxis.com/verify/' . str_repeat('c', 44);
        $this->assertSame(Qr::svg($text), Qr::svg($text));
    }

    // ------------------------------------------------------------------ a reader, for the test only

    /**
     * Reads a version 1 code the way a scanner does: the format information says which mask was used, the
     * mask comes off, the data is read along the zigzag, and the byte-mode header says how much of it is text.
     *
     * @param  array<int, array<int, bool>>  $matrix
     */
    private function readVersionOne(array $matrix): string
    {
        $size = count($matrix);
        $this->assertSame(21, $size, 'this reader only knows version 1');

        $mask = $this->maskFrom($matrix, $size);
        $reserved = $this->functionModules($size);

        $bits = '';
        $up = true;
        for ($right = $size - 1; $right > 0; $right -= 2) {
            if ($right === 6) {
                $right--; // the vertical timing pattern is never a data column
            }
            for ($step = 0; $step < $size; $step++) {
                $y = $up ? $size - 1 - $step : $step;
                foreach ([$right, $right - 1] as $x) {
                    if ($reserved[$y][$x]) {
                        continue;
                    }
                    $bit = $matrix[$y][$x];
                    if ($this->maskBit($mask, $x, $y)) {
                        $bit = ! $bit;
                    }
                    $bits .= $bit ? '1' : '0';
                }
            }
            $up = ! $up;
        }

        $this->assertSame('0100', substr($bits, 0, 4), 'byte mode');
        $length = bindec(substr($bits, 4, 8));
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= chr(bindec(substr($bits, 12 + $i * 8, 8)));
        }

        return $out;
    }

    /** The mask number, from the copy of the format information beside the top-left finder pattern */
    private function maskFrom(array $matrix, int $size): int
    {
        $bits = 0;
        for ($i = 0; $i < 15; $i++) {
            $value = match (true) {
                $i < 6 => $matrix[$i][8],
                $i === 6 => $matrix[7][8],
                $i === 7 => $matrix[8][8],
                $i === 8 => $matrix[8][7],
                default => $matrix[8][14 - $i],
            };
            $bits |= ($value ? 1 : 0) << $i;
        }
        $bits ^= 0b101010000010010;

        $this->assertSame(0b00, $bits >> 13, 'error correction level M');

        return ($bits >> 10) & 0b111;
    }

    /** Which modules carry no data: the finder patterns, their separators, the timing and the format area */
    private function functionModules(int $size): array
    {
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        foreach ([[0, 0], [$size - 8, 0], [0, $size - 8]] as [$y, $x]) {
            for ($dy = 0; $dy < 8; $dy++) {
                for ($dx = 0; $dx < 8; $dx++) {
                    $reserved[min($size - 1, $y + $dy)][min($size - 1, $x + $dx)] = true;
                }
            }
        }
        for ($i = 0; $i < $size; $i++) {
            $reserved[6][$i] = true;
            $reserved[$i][6] = true;
            $reserved[8][$i] = $reserved[8][$i] || $i < 9 || $i >= $size - 8;
            $reserved[$i][8] = $reserved[$i][8] || $i < 9 || $i >= $size - 8;
        }

        return $reserved;
    }

    private function maskBit(int $mask, int $x, int $y): bool
    {
        return match ($mask) {
            0 => ($y + $x) % 2 === 0,
            1 => $y % 2 === 0,
            2 => $x % 3 === 0,
            3 => ($y + $x) % 3 === 0,
            4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
            5 => (($y * $x) % 2) + (($y * $x) % 3) === 0,
            6 => ((($y * $x) % 2) + (($y * $x) % 3)) % 2 === 0,
            default => ((($y + $x) % 2) + (($y * $x) % 3)) % 2 === 0,
        };
    }
}
