<?php

namespace App\Events\Opportunities;

use App\Models\Meeting;
use Illuminate\Foundation\Events\Dispatchable;

/** A meeting was created in Google Calendar from HQ or the portal (docs/OPPORTUNITIES.md 8.5) */
class MeetingScheduled
{
    use Dispatchable;

    public function __construct(public readonly Meeting $meeting) {}
}
