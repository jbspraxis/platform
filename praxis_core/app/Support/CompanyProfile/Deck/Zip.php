<?php

namespace App\Support\CompanyProfile\Deck;

/**
 * A .pptx is a zip, and the live web PHP has no `ext-zip` (the ground DOCUMENTS.md section 3 found for gd
 * and imagick; the Laragon PHP this was built on has none either, so every test here runs without it).
 * So the deck writes its own archive: local headers, raw deflate through zlib, a central directory and an
 * end record. That is the whole of the zip format a .pptx needs — no encryption, no zip64, no data
 * descriptors — and `gzdeflate()` is part of PHP itself.
 *
 * The timestamp on every entry is fixed, so the same profile in the same language renders to the same
 * bytes twice. A test that compares two renders would otherwise fail a second after midnight.
 *
 * `entries()` and `read()` are the other half: the tests open the file they just wrote, which is the only
 * honest way to say a .pptx is valid when ZipArchive is not there to say it.
 */
final class Zip
{
    /** 1 January 2026, as MS-DOS date and time: the fixed stamp every entry carries. */
    private const DOS_DATE = 23585;

    private const DOS_TIME = 0;

    /**
     * @param  array<string, string>  $files  path inside the archive => its bytes, in the order to write them
     */
    public static function write(array $files): string
    {
        $local = '';
        $central = '';
        $count = 0;

        foreach ($files as $name => $bytes) {
            $name = str_replace(DIRECTORY_SEPARATOR, '/', (string) $name);
            $offset = strlen($local);
            $size = strlen($bytes);
            $crc = crc32($bytes);

            $deflated = gzdeflate($bytes, 9);
            $method = 8;
            if ($deflated === false || strlen($deflated) >= $size) {
                $deflated = $bytes;   // a PNG or a JPEG is already compressed: store it rather than grow it
                $method = 0;
            }

            // The 26 bytes a local header and a central directory entry share, in the same order
            $shared = pack(
                'vvvvvVVVvv',
                20, 0, $method, self::DOS_TIME, self::DOS_DATE,
                $crc, strlen($deflated), $size, strlen($name), 0
            );

            $local .= "PK\x03\x04".$shared.$name.$deflated;
            $central .= "PK\x01\x02".pack('v', 0x0314).$shared.pack('vvvVV', 0, 0, 0, 0, $offset).$name;
            $count++;
        }

        return $local.$central."PK\x05\x06".pack(
            'vvvvVVv',
            0, 0, $count, $count, strlen($central), strlen($local), 0
        );
    }

    /**
     * Every entry in an archive, in central-directory order: name => uncompressed bytes.
     *
     * @return array<string, string>
     */
    public static function entries(string $archive): array
    {
        $end = self::endRecord($archive);
        $at = $end['offset'];
        $out = [];

        for ($i = 0; $i < $end['count']; $i++) {
            if (substr($archive, $at, 4) !== "PK\x01\x02") {
                throw new \RuntimeException('Not a zip: the central directory ends after '.$i.' of '.$end['count'].' entries.');
            }
            $head = unpack('vmethod/vtime/vdate/Vcrc/Vpacked/Vsize/vname/vextra/vcomment/vdisk/vinternal/Vexternal/Vat', substr($archive, $at + 10, 36));
            $name = substr($archive, $at + 46, $head['name']);
            $out[$name] = self::at($archive, (int) $head['at'], (int) $head['method'], (int) $head['packed'], (int) $head['size'], (int) $head['crc']);
            $at += 46 + $head['name'] + $head['extra'] + $head['comment'];
        }

        return $out;
    }

    /** One entry's bytes, or null when the archive has no such name. */
    public static function read(string $archive, string $name): ?string
    {
        return self::entries($archive)[$name] ?? null;
    }

    /** @return array{count: int, offset: int} */
    private static function endRecord(string $archive): array
    {
        // The end record is the last 22 bytes unless the archive carries a comment, which ours never does
        $at = strrpos($archive, "PK\x05\x06");
        if ($at === false) {
            throw new \RuntimeException('Not a zip: no end-of-central-directory record.');
        }
        $end = unpack('vdisk/vstart/vhere/vtotal/Vsize/Voffset', substr($archive, $at + 4, 16));

        return ['count' => (int) $end['total'], 'offset' => (int) $end['offset']];
    }

    private static function at(string $archive, int $at, int $method, int $packed, int $size, int $crc): string
    {
        if (substr($archive, $at, 4) !== "PK\x03\x04") {
            throw new \RuntimeException('Not a zip: no local header at '.$at.'.');
        }
        $head = unpack('vname/vextra', substr($archive, $at + 26, 4));
        $from = $at + 30 + $head['name'] + $head['extra'];
        $bytes = substr($archive, $from, $packed);

        if ($method === 8) {
            $bytes = gzinflate($bytes);
            if ($bytes === false) {
                throw new \RuntimeException('Not a zip: an entry would not inflate.');
            }
        }

        if (strlen($bytes) !== $size || crc32($bytes) !== $crc) {
            throw new \RuntimeException('Not a zip: an entry does not match its size or its checksum.');
        }

        return $bytes;
    }
}
