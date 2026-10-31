<?php

namespace App\Events\Opportunities;

use App\Models\Meeting;
use Illuminate\Foundation\Events\Dispatchable;

/** A scheduled meeting's end time passed: it is now held (meetings:collect-transcripts, every 5 minutes) */
class MeetingEnded
{
    use Dispatchable;

    public function __construct(public readonly Meeting $meeting) {}
}
