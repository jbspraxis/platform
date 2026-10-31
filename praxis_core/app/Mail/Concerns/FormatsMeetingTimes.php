<?php

namespace App\Mail\Concerns;

use Carbon\CarbonInterface;

/** Meeting dates and times in emails, in the meeting's time zone (WAT): "Thursday 5 November 2026", "10:30" / "10 h 30" */
trait FormatsMeetingTimes
{
    protected function meetingDay(CarbonInterface $at, string $tz): string
    {
        return $at->copy()->setTimezone($tz)->locale(app()->getLocale())->isoFormat(__('emails.common.date_format'));
    }

    protected function meetingClock(CarbonInterface $at, string $tz): string
    {
        return $at->copy()->setTimezone($tz)->isoFormat(app()->getLocale() === 'fr' ? "H[\u{202F}h\u{202F}]mm" : 'HH:mm');
    }

    /** "10:30 to 11:30 (WAT)" */
    protected function meetingRange(CarbonInterface $starts, CarbonInterface $ends, string $tz): string
    {
        return __('emails.meeting.time_range', ['start' => $this->meetingClock($starts, $tz), 'end' => $this->meetingClock($ends, $tz)]);
    }
}
