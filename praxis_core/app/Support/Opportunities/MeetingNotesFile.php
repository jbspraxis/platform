<?php

namespace App\Support\Opportunities;

use App\Models\MeetingTranscript;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A meeting's notes as a Markdown file (docs/OPPORTUNITIES.md 6.1): the meeting's details, then the AI's notes as
 * the Meetings tab shows them, and with $withTranscript the full transcript at the end. Written in the opportunity's
 * language, the language the AI wrote the notes in. Who may download which is decided by MeetingNotesController.
 */
final class MeetingNotesFile
{
    public static function markdown(MeetingTranscript $transcript, bool $withTranscript): string
    {
        $meeting = $transcript->meeting()->with(['opportunity.organization', 'attendees' => fn ($q) => $q->orderByRaw("FIELD(role, 'staff', 'client', 'guest')")->orderBy('name')])->first();
        $opportunity = $meeting->opportunity;
        $lang = $opportunity->language ?: 'en';
        $zone = $meeting->time_zone ?: Meetings::TIME_ZONE;
        $starts = $meeting->starts_at->copy()->setTimezone($zone)->locale($lang);
        $milestone = $meeting->phase_id ? DB::table('project_phases')->where('id', $meeting->phase_id)->first(['phase_number', 'title']) : null;
        $who = $meeting->attendees->map(fn ($a) => ($a->name ?: $a->email) . ' (' . ($a->role === 'client' && $opportunity->organization
            ? $opportunity->organization->name : __('meetings.roles.' . $a->role, [], $lang)) . ')')->implode(', ');

        $lines = ['# ' . self::line($meeting->title), ''];
        $details = [
            __('meetings.file.opportunity', [], $lang) => trim($opportunity->title . ($opportunity->organization ? " ({$opportunity->organization->name})" : '')),
            __('meetings.file.meeting', [], $lang) => __('opportunities.meetings.kinds.' . $meeting->kind, [], $lang) . ', '
                . $starts->translatedFormat(__('opportunities.formats.date', [], $lang)) . ', ' . $starts->translatedFormat(__('opportunities.formats.time', [], $lang)) . ' ' . $starts->format('T'),
            __('meetings.file.milestone', [], $lang) => $milestone ? "M{$milestone->phase_number} · {$milestone->title}" : null,
            __('meetings.file.attendees', [], $lang) => $who,
            __('meetings.agenda.title', [], $lang) => filled($meeting->agenda) ? self::line($meeting->agenda) : null,
        ];
        foreach (array_filter($details, 'filled') as $label => $value) {
            $lines[] = '- **' . __('meetings.file.field', ['label' => $label], $lang) . '** ' . self::line($value);
        }

        $notes = is_array($transcript->notes) ? $transcript->notes : [];
        $lines = [...$lines, '', '## ' . __('meetings.notes.title', [], $lang), ''];
        if (! $notes) {
            $lines = [...$lines, __('meetings.notes.none', [], $lang), ''];
        }
        if (filled($notes['summary'] ?? null)) {
            $lines = [...$lines, trim($notes['summary']), ''];
        }
        foreach (['decisions', 'needs', 'risks', 'open_questions'] as $key) {
            $items = array_values(array_filter((array) ($notes[$key] ?? []), 'filled'));
            if ($items) {
                $lines = [...$lines, '### ' . __('meetings.notes.' . $key, [], $lang), '', ...array_map(fn ($i) => '- ' . self::line($i), $items), ''];
            }
        }
        $steps = array_values(array_filter((array) ($notes['next_steps'] ?? []), fn ($s) => filled($s['action'] ?? null)));
        if ($steps) {
            $lines = [...$lines, '### ' . __('meetings.notes.next_steps', [], $lang), ''];
            foreach ($steps as $s) {
                $by = collect([$s['owner'] ?? null, $s['due'] ?? null])->filter()->implode(', ');
                $lines[] = '- ' . self::line($s['action']) . ($by !== '' ? ' (' . self::line($by) . ')' : '');
            }
            $lines[] = '';
        }

        if ($withTranscript) {
            // One paragraph per line, so each turn of the conversation stays on its own line when the file is shown as Markdown
            $said = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $transcript->text)), fn ($l) => $l !== ''));
            $lines = [...$lines, '---', '', '## ' . __('meetings.file.transcript', [], $lang), '', implode("\n\n", $said), ''];
        }

        $lines = [...$lines, '---', '', '_' . __('meetings.file.footer', ['date' => now()->setTimezone($zone)->locale($lang)->translatedFormat(__('opportunities.formats.date', [], $lang))], $lang) . '_', ''];

        return implode("\n", $lines);
    }

    /** e.g. 2026-11-01-m3-internal-review-notes.md, from the meeting's date and title */
    public static function filename(MeetingTranscript $transcript, bool $withTranscript): string
    {
        $meeting = $transcript->meeting;
        $lang = $meeting->opportunity?->language ?: 'en';
        $day = Carbon::parse($meeting->starts_at)->setTimezone($meeting->time_zone ?: Meetings::TIME_ZONE)->format('Y-m-d');
        $name = Str::limit(Str::slug($meeting->title), 80, '') ?: 'meeting';

        return "{$day}-{$name}-" . __($withTranscript ? 'meetings.file.name_transcript' : 'meetings.file.name_notes', [], $lang) . '.md';
    }

    /** One line of text: the AI's answers and the agenda can hold line breaks, which would break a list item */
    private static function line(string $text): string
    {
        return trim(preg_replace('/\s*\R\s*/', ' ', $text));
    }
}
