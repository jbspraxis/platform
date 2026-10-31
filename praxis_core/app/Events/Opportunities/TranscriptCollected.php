<?php

namespace App\Events\Opportunities;

use App\Models\MeetingTranscript;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A transcript was filed on a meeting: collected from Google, uploaded or pasted. $automatic: HQ collected it
 * (the lead is told); false: someone added it by hand.
 */
class TranscriptCollected
{
    use Dispatchable;

    public function __construct(public readonly MeetingTranscript $transcript, public readonly bool $automatic = true) {}
}
