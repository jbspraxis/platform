<?php

namespace App\Support\Google;

/**
 * A meeting transcript as Google gave it: the whole text, the lines (speaker, time, text) when Meet's API has them,
 * and the Google Doc it lives in. $source is a meeting_transcripts.source value (meet_api | calendar_doc).
 */
final class Transcript
{
    public function __construct(
        public readonly string $source,
        public readonly string $text,
        public readonly ?array $entries = null,
        public readonly ?string $language = null,
        public readonly ?string $googleDocId = null,
    ) {}
}
