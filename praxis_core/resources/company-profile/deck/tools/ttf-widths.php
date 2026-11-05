<?php

/**
 * The advance widths of the deck's two faces, read out of their TrueType files.
 *
 * The deck is set in Georgia and Arial, which every machine that opens it already has
 * (docs/company-profile/DECK.md, "Fonts"). Nothing in the application may depend on those files being
 * present, so their metrics are read once here and committed as deck-metrics.json; the renderer reads the
 * JSON and never touches a font file. Widths are in thousandths of an em, which is how a PostScript or an
 * OOXML metric is usually quoted and keeps the file small.
 *
 * Run it again only when the deck takes a new face or a new character:
 *
 *   php resources/company-profile/deck/tools/ttf-widths.php > resources/company-profile/deck/deck-metrics.json
 *
 * It needs the fonts at their Windows paths; on any other machine, pass a folder as the first argument.
 */
$dir = $argv[1] ?? 'C:/Windows/Fonts';

$faces = [
    'serif' => ['regular' => 'georgia.ttf', 'bold' => 'georgiab.ttf', 'italic' => 'georgiai.ttf'],
    'sans' => ['regular' => 'arial.ttf', 'bold' => 'arialbd.ttf', 'italic' => 'ariali.ttf'],
];

/** Latin-1, the quotes and dashes the brand writes, the guillemets, the narrow no-break space and the naira */
$wanted = [];
for ($c = 0x20; $c <= 0x7E; $c++) {
    $wanted[] = $c;
}
for ($c = 0xA0; $c <= 0xFF; $c++) {
    $wanted[] = $c;
}
foreach ([0x0152, 0x0153, 0x0178, 0x2013, 0x2014, 0x2018, 0x2019, 0x201C, 0x201D, 0x2026, 0x2039, 0x203A, 0x202F, 0x20A6, 0x2022] as $c) {
    $wanted[] = $c;
}

function table(string $font, string $tag): ?array
{
    $count = unpack('n', substr($font, 4, 2))[1];
    for ($i = 0; $i < $count; $i++) {
        $at = 12 + ($i * 16);
        if (substr($font, $at, 4) === $tag) {
            $r = unpack('Noffset/Nlength', substr($font, $at + 8, 8));

            return ['offset' => $r['offset'], 'length' => $r['length']];
        }
    }

    return null;
}

/** The Unicode cmap, format 4: codepoint => glyph id */
function cmap(string $font): array
{
    $t = table($font, 'cmap') ?? throw new RuntimeException('no cmap');
    $base = $t['offset'];
    $n = unpack('n', substr($font, $base + 2, 2))[1];

    // A font may carry several Unicode subtables; take the first in format 4, whatever platform wrote it
    $sub = null;
    for ($i = 0; $i < $n && $sub === null; $i++) {
        $r = unpack('nplatform/nencoding/Noffset', substr($font, $base + 4 + ($i * 8), 8));
        $at = $base + $r['offset'];
        $format = unpack('n', substr($font, $at, 2))[1];
        if ($format === 4 && (($r['platform'] === 3 && $r['encoding'] === 1) || $r['platform'] === 0)) {
            $sub = $at;
        }
    }
    if ($sub === null) {
        throw new RuntimeException('no format 4 cmap');
    }

    $segX2 = unpack('n', substr($font, $sub + 6, 2))[1];
    $segs = intdiv($segX2, 2);
    $ends = array_values(unpack('n*', substr($font, $sub + 14, $segX2)));
    $starts = array_values(unpack('n*', substr($font, $sub + 16 + $segX2, $segX2)));
    $deltas = array_values(unpack('n*', substr($font, $sub + 16 + (2 * $segX2), $segX2)));
    $rangeAt = $sub + 16 + (3 * $segX2);
    $ranges = array_values(unpack('n*', substr($font, $rangeAt, $segX2)));

    $map = [];
    for ($s = 0; $s < $segs; $s++) {
        for ($c = $starts[$s]; $c <= $ends[$s] && $c !== 0xFFFF; $c++) {
            if ($ranges[$s] === 0) {
                $g = ($c + $deltas[$s]) & 0xFFFF;
            } else {
                $at = $rangeAt + ($s * 2) + $ranges[$s] + (($c - $starts[$s]) * 2);
                $g = unpack('n', substr($font, $at, 2))[1];
                if ($g !== 0) {
                    $g = ($g + $deltas[$s]) & 0xFFFF;
                }
            }
            if ($g !== 0) {
                $map[$c] = $g;
            }
        }
    }

    return $map;
}

$out = ['note' => 'Advance widths in thousandths of an em. Built by resources/company-profile/deck/tools/ttf-widths.php.', 'faces' => []];

foreach ($faces as $face => $cuts) {
    foreach ($cuts as $cut => $file) {
        $font = file_get_contents($dir.'/'.$file) ?: throw new RuntimeException("cannot read {$file}");

        $head = table($font, 'head') ?? throw new RuntimeException('no head');
        $em = unpack('n', substr($font, $head['offset'] + 18, 2))[1];

        $hhea = table($font, 'hhea') ?? throw new RuntimeException('no hhea');
        $metrics = unpack('n', substr($font, $hhea['offset'] + 34, 2))[1];

        $hmtx = table($font, 'hmtx') ?? throw new RuntimeException('no hmtx');
        $map = cmap($font);

        $widths = [];
        foreach ($wanted as $c) {
            $g = $map[$c] ?? null;
            if ($g === null) {
                continue;
            }
            $i = min($g, $metrics - 1);
            $adv = unpack('n', substr($font, $hmtx['offset'] + ($i * 4), 2))[1];
            $widths[$c] = (int) round($adv * 1000 / $em);
        }
        ksort($widths);
        $out['faces'][$face][$cut] = $widths;
    }
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
