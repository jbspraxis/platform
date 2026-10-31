<?php

namespace App\Support\Opportunities;

use App\Models\Meeting;

/** What Meetings did: the meeting as saved, and the client emails that could not be sent (to say so on screen) */
final class MeetingChange
{
    /** @param  list<string>  $unsent  email addresses */
    public function __construct(
        public readonly Meeting $meeting,
        public readonly array $unsent = [],
    ) {}
}
