<?php

namespace App\Mail;

use App\Mail\Concerns\FormatsMeetingTimes;
use App\Models\Meeting;
use App\Models\MeetingTranscript;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Support\Opportunities\PortalLinks;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailables\Content;

/**
 * The minutes of a client meeting, as the lead edited them (docs/OPPORTUNITIES.md decision 4): decisions and next
 * steps only, never the transcript. The button opens the client's meetings page, where the minutes stay and a
 * follow-up can be booked.
 */
class MeetingMinutesMail extends BrandedMail
{
    use FormatsMeetingTimes;

    public function __construct(public MeetingTranscript $transcript, public ?string $clientUserId = null, public ?string $firstName = null) {}

    protected function subjectLine(): string
    {
        $meeting = $this->transcript->meeting;

        return __('emails.minutes.subject', ['title' => $meeting->title, 'date' => $this->meetingDay($meeting->starts_at, $meeting->time_zone ?: 'Africa/Lagos')]);
    }

    public function content(): Content
    {
        $meeting = $this->transcript->meeting;

        return new Content(view: 'emails.meeting-minutes', with: [
            'meeting' => $meeting,
            'company' => $meeting->opportunity?->organization?->name ?? $meeting->organization?->name ?? '',
            'meetingDate' => $this->meetingDay($meeting->starts_at, $meeting->time_zone ?: 'Africa/Lagos'),
            'blocks' => self::blocks((string) $this->transcript->minutes),
            'portalUrl' => $meeting->opportunity ? PortalLinks::meetings($meeting->opportunity, $this->clientUserId) : null,
        ]);
    }

    /**
     * The minutes' text in blocks, for the email and the portal: a paragraph, or a list (lines starting "- ",
     * after an optional heading line).
     *
     * @return list<array{heading?: ?string, items?: list<string>, text?: string}>
     */
    public static function blocks(string $text): array
    {
        $blocks = [];
        foreach (preg_split('/\n\s*\n/', trim(str_replace("\r", '', $text))) as $block) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), fn ($l) => $l !== ''));
            if (! $lines) {
                continue;
            }
            $heading = str_starts_with($lines[0], '- ') ? null : $lines[0];
            $rest = $heading === null ? $lines : array_slice($lines, 1);
            if ($rest && collect($rest)->every(fn ($l) => str_starts_with($l, '- '))) {
                $blocks[] = ['heading' => $heading, 'items' => array_map(fn ($l) => trim(substr($l, 2)), $rest)];
            } else {
                $blocks[] = ['text' => implode("\n", $lines)];
            }
        }

        return $blocks;
    }

    public static function samples(): array
    {
        $organisation = (new Organization)->forceFill(['id' => '7f6c2a52-1b1e-4a8e-9a7a-0c0d1e2f3a4b', 'name' => 'Acme Ltd (demo)']);
        $opportunity = (new Opportunity)->forceFill(['id' => '0b5c8d1e-2f3a-4b5c-8d6e-7f8091a2b3c4', 'title' => 'Finance system', 'language' => 'en'])
            ->setRelation('organization', $organisation);
        $meeting = (new Meeting)->forceFill([
            'id' => '3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f', 'kind' => 'client', 'title' => 'Discovery: finance system',
            'starts_at' => CarbonImmutable::parse('2026-11-05 09:30:00', 'UTC'), 'ends_at' => CarbonImmutable::parse('2026-11-05 10:30:00', 'UTC'),
            'time_zone' => 'Africa/Lagos', 'status' => 'held',
        ])->setRelation('opportunity', $opportunity);
        $transcript = (new MeetingTranscript)->forceFill([
            'id' => '9a8b7c6d-5e4f-4a3b-8c2d-1e0f9a8b7c6d',
            'minutes' => "We looked at how month-end closing runs today and where the finance team loses time.\n\nDecisions\n- Start with the three Lagos entities\n- Keep the current chart of accounts for the first phase\n\nNext steps\n- Send the trial balances for September (Acme finance team, 12 November)\n- Share a first outline of the approach (JBS Praxis, 19 November)",
        ])->setRelation('meeting', $meeting);

        return ['minutes' => new static($transcript, null, 'Ada')];
    }
}
