<?php

namespace App\Support\Letters;

use RuntimeException;

/**
 * A QR code, drawn as SVG (docs/DOCUMENTS.md section 7). Written here because the live PHP has neither a QR
 * library nor the gd extension: an SVG of plain rectangles needs neither, and dompdf draws it with php-svg-lib.
 *
 * Byte mode, error correction level M (about 15% of the code can be damaged and still read), versions 1 to 10,
 * which is far more than the verify address needs. The smallest version that fits is chosen, then the mask with
 * the lowest penalty, as the standard (ISO/IEC 18004) says.
 */
final class Qr
{
    /** Per version (1-indexed): total codewords, EC codewords per block, [blocks, data each] for group 1 and 2, level M */
    private const VERSIONS = [
        1 => [26, 10, [1, 16], null],
        2 => [44, 16, [1, 28], null],
        3 => [70, 26, [1, 44], null],
        4 => [100, 18, [2, 32], null],
        5 => [134, 24, [2, 43], null],
        6 => [172, 16, [4, 27], null],
        7 => [196, 18, [4, 31], null],
        8 => [242, 22, [2, 38], [2, 39]],
        9 => [292, 22, [3, 36], [2, 37]],
        10 => [346, 26, [4, 43], [1, 44]],
    ];

    /** Where the alignment patterns sit, per version */
    private const ALIGNMENT = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    /** Level M as the format information encodes it */
    private const EC_LEVEL_BITS = 0b00;

    /**
     * The modules of the code: a square array of booleans, true where a module is dark.
     *
     * @return array<int, array<int, bool>>
     */
    public static function matrix(string $text): array
    {
        [$version, $bits] = self::encode($text);
        $codewords = self::interleave($version, $bits);
        $size = 17 + 4 * $version;

        [$matrix, $reserved] = self::skeleton($version, $size);
        self::placeData($matrix, $reserved, $codewords, $size);

        $best = null;
        $bestPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = self::applyMask($matrix, $reserved, $mask, $size);
            self::placeFormat($candidate, $mask, $size);
            $penalty = self::penalty($candidate, $size);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * The code as an SVG of one path, sized in millimetres for the PDF and scalable on screen.
     * $quiet is the empty margin in modules; the standard asks for 4.
     */
    public static function svg(string $text, float $sizeMm = 22.0, int $quiet = 4, string $colour = '#0C142B'): string
    {
        $matrix = self::matrix($text);
        $size = count($matrix);
        $total = $size + 2 * $quiet;

        $path = '';
        foreach ($matrix as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $path .= 'M' . ($x + $quiet) . ' ' . ($y + $quiet) . 'h1v1h-1z';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . self::mm($sizeMm) . 'mm" height="' . self::mm($sizeMm) . 'mm"'
            . ' viewBox="0 0 ' . $total . ' ' . $total . '" shape-rendering="crispEdges" role="img">'
            . '<rect width="' . $total . '" height="' . $total . '" fill="#FFFFFF"/>'
            . '<path d="' . $path . '" fill="' . $colour . '"/></svg>';
    }

    /** The same SVG as a data URI, which is how both the PDF and the screen show it */
    public static function dataUri(string $text, float $sizeMm = 22.0): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(self::svg($text, $sizeMm));
    }

    private static function mm(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    // ------------------------------------------------------------------ encoding

    /** @return array{int, array<int, int>} the version and the data bits */
    private static function encode(string $text): array
    {
        $length = strlen($text);
        $version = null;
        foreach (self::VERSIONS as $v => [$total, $ecPerBlock, $g1, $g2]) {
            $blocks = $g1[0] + ($g2[0] ?? 0);
            $dataCodewords = $total - $ecPerBlock * $blocks;
            $countBits = $v <= 9 ? 8 : 16;
            if ($dataCodewords * 8 >= 4 + $countBits + $length * 8) {
                $version = $v;
                break;
            }
        }
        if ($version === null) {
            throw new RuntimeException('The text is too long for a QR code of version 10 or smaller.');
        }

        [$total, $ecPerBlock, $g1, $g2] = self::VERSIONS[$version];
        $blocks = $g1[0] + ($g2[0] ?? 0);
        $dataCodewords = $total - $ecPerBlock * $blocks;

        $bits = [];
        self::push($bits, 0b0100, 4);                       // byte mode
        self::push($bits, $length, $version <= 9 ? 8 : 16);
        for ($i = 0; $i < $length; $i++) {
            self::push($bits, ord($text[$i]), 8);
        }

        // Terminator, then pad to a whole number of codewords, then the two padding bytes in turn
        $capacity = $dataCodewords * 8;
        for ($i = 0; $i < 4 && count($bits) < $capacity; $i++) {
            $bits[] = 0;
        }
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }
        $pad = [0xEC, 0x11];
        for ($i = 0; count($bits) < $capacity; $i++) {
            self::push($bits, $pad[$i % 2], 8);
        }

        return [$version, $bits];
    }

    private static function push(array &$bits, int $value, int $length): void
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    /** Split the data into blocks, add each block's error correction, then interleave as the standard says */
    private static function interleave(int $version, array $bits): array
    {
        [$total, $ecPerBlock, $g1, $g2] = self::VERSIONS[$version];

        $bytes = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $byte = 0;
            for ($b = 0; $b < 8; $b++) {
                $byte = ($byte << 1) | $bits[$i + $b];
            }
            $bytes[] = $byte;
        }

        $groups = [];
        $offset = 0;
        foreach ([$g1, $g2] as $group) {
            if ($group === null) {
                continue;
            }
            [$count, $size] = $group;
            for ($i = 0; $i < $count; $i++) {
                $groups[] = array_slice($bytes, $offset, $size);
                $offset += $size;
            }
        }

        $ecBlocks = array_map(fn ($block) => self::errorCorrection($block, $ecPerBlock), $groups);

        $out = [];
        $longest = max(array_map('count', $groups));
        for ($i = 0; $i < $longest; $i++) {
            foreach ($groups as $block) {
                if (isset($block[$i])) {
                    $out[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $ecPerBlock; $i++) {
            foreach ($ecBlocks as $block) {
                $out[] = $block[$i];
            }
        }

        // Remainder bits, as whole bytes of zero would be wrong: they are added when the data is placed
        return $out;
    }

    /** Reed-Solomon over GF(256) with the QR field polynomial */
    private static function errorCorrection(array $data, int $count): array
    {
        [$exp, $log] = self::tables();

        $generator = [1];
        for ($i = 0; $i < $count; $i++) {
            $next = array_fill(0, count($generator) + 1, 0);
            foreach ($generator as $j => $coefficient) {
                $next[$j] ^= $coefficient;
                $next[$j + 1] ^= $coefficient === 0 ? 0 : $exp[($log[$coefficient] + $i) % 255];
            }
            $generator = $next;
        }

        $remainder = array_merge($data, array_fill(0, $count, 0));
        for ($i = 0; $i < count($data); $i++) {
            $lead = $remainder[$i];
            if ($lead === 0) {
                continue;
            }
            $leadLog = $log[$lead];
            foreach ($generator as $j => $coefficient) {
                if ($coefficient !== 0) {
                    $remainder[$i + $j] ^= $exp[($log[$coefficient] + $leadLog) % 255];
                }
            }
        }

        return array_slice($remainder, count($data), $count);
    }

    /** @return array{array<int,int>, array<int,int>} the exponent and logarithm tables of GF(256) */
    private static function tables(): array
    {
        static $exp = null, $log = null;
        if ($exp !== null) {
            return [$exp, $log];
        }
        $exp = array_fill(0, 256, 0);
        $log = array_fill(0, 256, 0);
        $value = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $value;
            $log[$value] = $i;
            $value <<= 1;
            if ($value & 0x100) {
                $value ^= 0x11D;
            }
        }
        $exp[255] = $exp[0];

        return [$exp, $log];
    }

    // ------------------------------------------------------------------ the matrix

    /** @return array{array<int,array<int,bool>>, array<int,array<int,bool>>} the modules and which of them are fixed */
    private static function skeleton(int $version, int $size): array
    {
        $matrix = array_fill(0, $size, array_fill(0, $size, false));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        foreach ([[0, 0], [$size - 7, 0], [0, $size - 7]] as [$x, $y]) {
            for ($dy = -1; $dy <= 7; $dy++) {
                for ($dx = -1; $dx <= 7; $dx++) {
                    $px = $x + $dx;
                    $py = $y + $dy;
                    if ($px < 0 || $py < 0 || $px >= $size || $py >= $size) {
                        continue;
                    }
                    $inRing = $dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6
                        && ($dx === 0 || $dx === 6 || $dy === 0 || $dy === 6 || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4));
                    $matrix[$py][$px] = $inRing;
                    $reserved[$py][$px] = true;
                }
            }
        }

        $centres = self::ALIGNMENT[$version];
        $last = $centres ? end($centres) : 0;
        foreach ($centres as $cy) {
            foreach ($centres as $cx) {
                // The three corners already carry a finder pattern; every other centre gets one, including the
                // ones that sit on a timing pattern
                if (($cx === 6 && $cy === 6) || ($cx === 6 && $cy === $last) || ($cx === $last && $cy === 6)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $matrix[$cy + $dy][$cx + $dx] = abs($dx) === 2 || abs($dy) === 2 || ($dx === 0 && $dy === 0);
                        $reserved[$cy + $dy][$cx + $dx] = true;
                    }
                }
            }
        }

        for ($i = 8; $i < $size - 8; $i++) {
            $dark = $i % 2 === 0;
            if (! $reserved[6][$i]) {
                $matrix[6][$i] = $dark;
                $reserved[6][$i] = true;
            }
            if (! $reserved[$i][6]) {
                $matrix[$i][6] = $dark;
                $reserved[$i][6] = true;
            }
        }

        // The one module that is always dark, and the places the format information goes
        $matrix[$size - 8][8] = true;
        $reserved[$size - 8][8] = true;
        for ($i = 0; $i < 9; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
        }
        for ($i = 0; $i < 8; $i++) {
            $reserved[8][$size - 1 - $i] = true;
            $reserved[$size - 1 - $i][8] = true;
        }

        if ($version >= 7) {
            self::placeVersion($matrix, $reserved, $version, $size);
        }

        return [$matrix, $reserved];
    }

    private static function placeVersion(array &$matrix, array &$reserved, int $version, int $size): void
    {
        $value = $version << 12;
        $remainder = $value;
        for ($i = 0; $i < 6; $i++) {
            if ($remainder & (1 << (17 - $i))) {
                $remainder ^= 0b1111100100101 << (5 - $i);
            }
        }
        $bits = $value | $remainder;

        for ($i = 0; $i < 18; $i++) {
            $bit = (bool) (($bits >> $i) & 1);
            $row = intdiv($i, 3);
            $column = $i % 3;
            $matrix[$row][$size - 11 + $column] = $bit;
            $reserved[$row][$size - 11 + $column] = true;
            $matrix[$size - 11 + $column][$row] = $bit;
            $reserved[$size - 11 + $column][$row] = true;
        }
    }

    private static function placeData(array &$matrix, array $reserved, array $codewords, int $size): void
    {
        $bits = [];
        foreach ($codewords as $byte) {
            self::push($bits, $byte, 8);
        }

        $index = 0;
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
                    $matrix[$y][$x] = ($bits[$index] ?? 0) === 1;
                    $index++;
                }
            }
            $up = ! $up;
        }
    }

    private static function applyMask(array $matrix, array $reserved, int $mask, int $size): array
    {
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($reserved[$y][$x]) {
                    continue;
                }
                if (self::maskBit($mask, $x, $y)) {
                    $matrix[$y][$x] = ! $matrix[$y][$x];
                }
            }
        }

        return $matrix;
    }

    private static function maskBit(int $mask, int $x, int $y): bool
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

    private static function placeFormat(array &$matrix, int $mask, int $size): void
    {
        $value = (self::EC_LEVEL_BITS << 3) | $mask;
        $remainder = $value << 10;
        for ($i = 0; $i < 5; $i++) {
            if ($remainder & (1 << (14 - $i))) {
                $remainder ^= 0b10100110111 << (4 - $i);
            }
        }
        $bits = (($value << 10) | $remainder) ^ 0b101010000010010;

        // $matrix[row][column]. The first copy runs down column 8 beside the top-left finder pattern and then
        // left along row 8; the second is split between the other two finder patterns.
        for ($i = 0; $i < 15; $i++) {
            $bit = (bool) (($bits >> $i) & 1);

            if ($i < 6) {
                $matrix[$i][8] = $bit;
            } elseif ($i === 6) {
                $matrix[7][8] = $bit;
            } elseif ($i === 7) {
                $matrix[8][8] = $bit;
            } elseif ($i === 8) {
                $matrix[8][7] = $bit;
            } else {
                $matrix[8][14 - $i] = $bit;
            }

            if ($i < 8) {
                $matrix[8][$size - 1 - $i] = $bit;
            } else {
                $matrix[$size - 15 + $i][8] = $bit;
            }
        }
        $matrix[$size - 8][8] = true;
    }

    /** The four penalty rules: the mask with the lowest total is the one that is used */
    private static function penalty(array $matrix, int $size): int
    {
        $score = 0;

        // Rule 1: runs of five or more of the same colour, in rows and in columns
        for ($i = 0; $i < $size; $i++) {
            foreach ([true, false] as $byRow) {
                $run = 1;
                for ($j = 1; $j < $size; $j++) {
                    $a = $byRow ? $matrix[$i][$j] : $matrix[$j][$i];
                    $b = $byRow ? $matrix[$i][$j - 1] : $matrix[$j - 1][$i];
                    if ($a === $b) {
                        $run++;
                        continue;
                    }
                    if ($run >= 5) {
                        $score += 3 + ($run - 5);
                    }
                    $run = 1;
                }
                if ($run >= 5) {
                    $score += 3 + ($run - 5);
                }
            }
        }

        // Rule 2: every 2 by 2 block of one colour
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $v = $matrix[$y][$x];
                if ($v === $matrix[$y][$x + 1] && $v === $matrix[$y + 1][$x] && $v === $matrix[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }

        // Rule 3: the finder-like pattern 1:1:3:1:1 with four light modules on either side
        $patterns = [
            [true, false, true, true, true, false, true, false, false, false, false],
            [false, false, false, false, true, false, true, true, true, false, true],
        ];
        for ($i = 0; $i < $size; $i++) {
            for ($j = 0; $j <= $size - 11; $j++) {
                foreach ($patterns as $pattern) {
                    $row = true;
                    $column = true;
                    for ($k = 0; $k < 11; $k++) {
                        $row = $row && $matrix[$i][$j + $k] === $pattern[$k];
                        $column = $column && $matrix[$j + $k][$i] === $pattern[$k];
                    }
                    $score += ($row ? 40 : 0) + ($column ? 40 : 0);
                }
            }
        }

        // Rule 4: how far the proportion of dark modules is from half
        $dark = 0;
        foreach ($matrix as $row) {
            foreach ($row as $module) {
                $dark += $module ? 1 : 0;
            }
        }
        $percent = $dark * 100 / ($size * $size);
        $score += (int) (abs($percent - 50) / 5) * 10;

        return $score;
    }
}
