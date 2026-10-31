<?php

namespace App\Support\SignIn;

use RuntimeException;

/**
 * The part of CBOR (RFC 8949) a passkey's answers use: integers, byte and text strings, arrays, maps, true,
 * false and null. Byte strings come back as PHP strings (raw bytes), maps as PHP arrays keyed by int or string.
 * Indefinite lengths, tags and floats are refused: no authenticator sends them in these structures.
 */
final class Cbor
{
    private const MAX_DEPTH = 16;

    /** One item from the start of $bytes; anything after it is refused. */
    public static function decode(string $bytes): mixed
    {
        [$value, $end] = self::decodeAt($bytes, 0);
        if ($end !== strlen($bytes)) {
            throw new RuntimeException('CBOR: trailing bytes');
        }

        return $value;
    }

    /**
     * One item starting at $offset, and the offset just after it (the credential's public key sits in the middle
     * of the authenticator data, with extensions after it).
     *
     * @return array{0: mixed, 1: int}
     */
    public static function decodeAt(string $bytes, int $offset, int $depth = 0): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw new RuntimeException('CBOR: nested too deep');
        }
        $first = self::byte($bytes, $offset++);
        $major = $first >> 5;
        $info = $first & 0x1f;

        if ($major === 7) {
            return match ($info) {
                20 => [false, $offset],
                21 => [true, $offset],
                22 => [null, $offset],
                default => throw new RuntimeException('CBOR: unsupported simple value'),
            };
        }

        [$length, $offset] = self::argument($bytes, $offset, $info);

        switch ($major) {
            case 0:
                return [$length, $offset];
            case 1:
                return [-1 - $length, $offset];
            case 2:
            case 3:
                if ($offset + $length > strlen($bytes)) {
                    throw new RuntimeException('CBOR: string runs past the end');
                }

                return [substr($bytes, $offset, $length), $offset + $length];
            case 4:
                $items = [];
                for ($i = 0; $i < $length; $i++) {
                    [$items[], $offset] = self::decodeAt($bytes, $offset, $depth + 1);
                }

                return [$items, $offset];
            case 5:
                $map = [];
                for ($i = 0; $i < $length; $i++) {
                    [$key, $offset] = self::decodeAt($bytes, $offset, $depth + 1);
                    if (! is_int($key) && ! is_string($key)) {
                        throw new RuntimeException('CBOR: unsupported map key');
                    }
                    [$map[$key], $offset] = self::decodeAt($bytes, $offset, $depth + 1);
                }

                return [$map, $offset];
            default:
                throw new RuntimeException('CBOR: tags are not supported');
        }
    }

    /** @return array{0: int, 1: int} */
    private static function argument(string $bytes, int $offset, int $info): array
    {
        if ($info < 24) {
            return [$info, $offset];
        }
        $size = match ($info) {
            24 => 1,
            25 => 2,
            26 => 4,
            27 => 8,
            default => throw new RuntimeException('CBOR: indefinite lengths are not supported'),
        };
        if ($offset + $size > strlen($bytes)) {
            throw new RuntimeException('CBOR: argument runs past the end');
        }
        $value = 0;
        for ($i = 0; $i < $size; $i++) {
            $value = ($value << 8) | ord($bytes[$offset + $i]);
        }
        if ($value < 0) {
            throw new RuntimeException('CBOR: number too large');
        }

        return [$value, $offset + $size];
    }

    private static function byte(string $bytes, int $offset): int
    {
        if ($offset >= strlen($bytes)) {
            throw new RuntimeException('CBOR: unexpected end');
        }

        return ord($bytes[$offset]);
    }
}
