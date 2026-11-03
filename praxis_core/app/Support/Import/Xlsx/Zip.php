<?php

namespace App\Support\Import\Xlsx;

use App\Support\Import\ImportFailed;

/**
 * Just enough of the zip format for .xlsx files, in plain PHP: the live PHP may not have the zip extension
 * (docs/LEADS.md section 12, PR 3). Reading takes stored and deflated entries (deflate needs zlib's gzinflate;
 * ZipArchive is used instead when only that is there). Writing deflates when zlib is there, else stores.
 */
final class Zip
{
    /** One entry may not unpack to more than this (a zip bomb stops here) */
    public const MAX_ENTRY_BYTES = 30 * 1024 * 1024;

    /** @var array<string, array{method: int, size: int, packed: int, offset: int}> */
    private array $entries = [];

    private function __construct(private readonly string $bytes, private readonly ?string $path = null)
    {
    }

    /**
     * Open a zip file's bytes ($path is only used by the ZipArchive fallback).
     *
     * @throws ImportFailed when it isn't a zip file
     */
    public static function open(string $bytes, ?string $path = null): self
    {
        $zip = new self($bytes, $path);
        $zip->readDirectory();

        return $zip;
    }

    public function has(string $name): bool
    {
        return isset($this->entries[$name]);
    }

    /** The unpacked bytes of an entry, or null when it isn't there */
    public function get(string $name): ?string
    {
        $e = $this->entries[$name] ?? null;
        if (! $e) {
            return null;
        }
        if ($e['size'] > self::MAX_ENTRY_BYTES) {
            throw new ImportFailed('This file is too big to read. Keep it under 1,000 rows and remove pictures and other sheets.');
        }
        $local = substr($this->bytes, $e['offset'], 30);
        if (strlen($local) < 30 || substr($local, 0, 4) !== "PK\x03\x04") {
            throw self::unreadable();
        }
        $head = unpack('vnameLength/vextraLength', substr($local, 26, 4));
        $data = substr($this->bytes, $e['offset'] + 30 + $head['nameLength'] + $head['extraLength'], $e['packed']);

        return match ($e['method']) {
            0 => $data,
            8 => $this->inflate($data, $name, $e['size']),
            default => throw self::unreadable(),
        };
    }

    private function inflate(string $data, string $name, int $size): string
    {
        if (function_exists('gzinflate')) {
            $out = @gzinflate($data, max($size, 1));
            if ($out === false) {
                throw self::unreadable();
            }

            return $out;
        }
        if ($this->path && class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($this->path) === true) {
                $out = $zip->getFromName($name);
                $zip->close();
                if ($out !== false) {
                    return $out;
                }
            }
        }
        throw new ImportFailed('This server can’t unpack Excel files. Save the sheet as CSV and upload that instead.');
    }

    /** The central directory at the end of the file lists every entry, its size and where it starts */
    private function readDirectory(): void
    {
        $tail = substr($this->bytes, -min(strlen($this->bytes), 65557));
        $at = strrpos($tail, "PK\x05\x06");
        if ($at === false || strlen($tail) - $at < 22) {
            throw self::unreadable();
        }
        $end = unpack('vdisk/vdirDisk/vhere/ventries/Vsize/Voffset', substr($tail, $at + 4, 16));
        $pos = $end['offset'];
        for ($i = 0; $i < $end['entries']; $i++) {
            if (substr($this->bytes, $pos, 4) !== "PK\x01\x02") {
                throw self::unreadable();
            }
            $h = unpack('vmade/vneed/vflags/vmethod/vtime/vdate/Vcrc/Vpacked/Vsize/vnameLength/vextraLength/vcommentLength/vdisk/vinternal/Vexternal/Voffset',
                substr($this->bytes, $pos + 4, 42));
            $name = substr($this->bytes, $pos + 46, $h['nameLength']);
            $this->entries[$name] = ['method' => $h['method'], 'size' => $h['size'], 'packed' => $h['packed'], 'offset' => $h['offset']];
            $pos += 46 + $h['nameLength'] + $h['extraLength'] + $h['commentLength'];
        }
    }

    /**
     * A zip file holding $files (name => bytes), in that order.
     */
    public static function build(array $files): string
    {
        $out = '';
        $directory = '';
        [$time, $date] = self::dosTime(time());
        foreach ($files as $name => $data) {
            $crc = crc32($data);
            $packed = function_exists('gzdeflate') ? gzdeflate($data, 6) : false;
            $method = $packed === false ? 0 : 8;
            $body = $packed === false ? $data : $packed;
            $offset = strlen($out);
            // version needed, flags (UTF-8 names), method, time, date, CRC, packed size, size, name length
            $common = pack('vvvvvVVVv', 20, 0x0800, $method, $time, $date, $crc, strlen($body), strlen($data), strlen($name));
            $out .= "PK\x03\x04" . $common . pack('v', 0) . $name . $body;
            $directory .= "PK\x01\x02" . pack('v', 20) . $common . pack('vvvvVV', 0, 0, 0, 0, 0, $offset) . $name;
        }

        return $out . $directory . "PK\x05\x06" . pack('vvvvVVv', 0, 0, count($files), count($files), strlen($directory), strlen($out), 0);
    }

    private static function dosTime(int $ts): array
    {
        $d = getdate($ts);

        return [
            ($d['hours'] << 11) | ($d['minutes'] << 5) | intdiv($d['seconds'], 2),
            (max($d['year'] - 1980, 0) << 9) | ($d['mon'] << 5) | $d['mday'],
        ];
    }

    private static function unreadable(): ImportFailed
    {
        return new ImportFailed('This file isn’t an Excel workbook we can read. Save it as .xlsx (Excel workbook) or CSV and upload it again.');
    }
}
