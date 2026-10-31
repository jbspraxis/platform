<?php

namespace Tests\Support;

/** A CBOR byte string (a PHP string alone is written as text) */
final class CborBytes
{
    public function __construct(public string $bytes) {}
}
