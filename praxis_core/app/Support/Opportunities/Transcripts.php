<?php

namespace App\Support\Opportunities;

use App\Events\Opportunities\TranscriptCollected;
use App\Mail\MeetingMinutesMail;
use App\Models\AiRun;
use App\Models\Meeting;
use App\Models\MeetingTranscript;
use App\Support\Ai\Ai;
use App\Support\Google\Transcript;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Throwable;

/**
 * Transcripts of meetings (docs/OPPORTUNITIES.md 2 step 3, 6.1): filed from Google by the collector or added by
 * hand (a .txt or .vtt file, or pasted text); then the AI writes the internal notes (meeting_notes, Haiku) and, for
 * client meetings, draft minutes (client_minutes, Haiku), which the lead edits and sends. Notes and minutes land
 * on the transcript when their run finishes (fileAiOutput, on AiRunFinished), whoever started it.
 * Full transcripts are personal data and stay in HQ; clients only ever get the minutes.
 */
final class Transcripts
{
    /** Longest transcript taken by hand: about 30 hours of talk */
    public const MAX_CHARS = 500000;

    public const SOURCES = ['meet_api', 'calendar_doc', 'upload', 'paste'];

    public static function fromGoogle(Meeting $meeting, Transcript $transcript): MeetingTranscript
    {
        return self::file($meeting, [
            'source' => $transcript->source,
            'google_doc_id' => $transcript->googleDocId,
            'language' => $transcript->language,
            'text' => $transcript->text,
            'entries' => $transcript->entries,
        ], automatic: true);
    }

    /** Uploaded ($source upload, from a .txt or .vtt file) or pasted text; WebVTT is read into speaker lines */
    public static function fromText(Meeting $meeting, string $text, string $source = 'paste'): MeetingTranscript
    {
        if (! in_array($source, ['upload', 'paste'], true)) {
            throw new LogicException("A transcript added by hand is an upload or a paste, not '{$source}'");
        }
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        $entries = null;
        if (preg_match('/^\x{FEFF}?WEBVTT/u', $text)) {
            [$text, $entries] = self::readVtt($text);
        }

        return self::file($meeting, ['source' => $source, 'text' => $text, 'entries' => $entries], automatic: false);
    }

    /**
     * WebVTT cues as "Speaker (00:01:05): text" lines, with the speaker from a voice tag (<v Ada Obi>) when there is
     * one. Cue numbers, NOTE and STYLE blocks and other tags are left out.
     *
     * @return array{0: string, 1: list<array{speaker: ?string, time: ?string, text: string}>}
     */
    public static function readVtt(string $vtt): array
    {
        $entries = [];
        foreach (preg_split('/\n{2,}/', str_replace(["\r\n", "\r"], "\n", $vtt)) as $block) {
            $lines = explode("\n", trim($block));
            $timing = null;
            foreach ($lines as $i => $line) {
                if (preg_match('/^((?:\d+:)?\d{2}:\d{2})[.,]\d{3}\s+-->/', $line, $m)) {
                    $timing = $i;
                    $time = strlen($m[1]) === 5 ? '00:' . $m[1] : str_pad($m[1], 8, '0', STR_PAD_LEFT);
                    break;
                }
            }
            if ($timing === null) {
                continue; // the header, a NOTE or STYLE block
            }
            $text = trim(implode(' ', array_slice($lines, $timing + 1)));
            $speaker = preg_match('/<v(?:\.[^\s>]+)*\s+([^>]+)>/u', $text, $v) ? trim($v[1]) : null;
            $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5));
            if ($text !== '') {
                $entries[] = ['speaker' => $speaker, 'time' => $time, 'text' => $text];
            }
        }

        $text = implode("\n", array_map(fn ($e) => ($e['speaker'] ? "{$e['speaker']} ({$e['time']}): " : "({$e['time']}) ") . $e['text'], $entries));

        return [$text, $entries];
    }

    /** The AI's internal notes for this transcript; throws AiUnavailable (no key, over budget) before anything is spent */
    public static function draftNotes(MeetingTranscript $transcript): AiRun
    {
        return self::draft('meeting_notes', $transcript);
    }

    /** Draft minutes for the client (client meetings only); the lead edits them before sending */
    public static function draftMinutes(MeetingTranscript $transcript): AiRun
    {
        if ($transcript->meeting->kind !== 'client') {
            throw new LogicException('Internal meetings have no client minutes');
        }

        return self::draft('client_minutes', $transcript);
    }

    /** The latest notes or minutes run of each transcript, keyed by transcript id then purpose */
    public static function latestRuns(array $transcriptIds): array
    {
        $out = [];
        if (! $transcriptIds) {
            return $out;
        }
        $runs = AiRun::where('subject_type', 'meeting_transcript')->whereIn('subject_id', $transcriptIds)
            ->whereIn('purpose', ['meeting_notes', 'client_minutes'])->orderBy('created_at')->orderBy('id')
            ->get(['id', 'purpose', 'subject_id', 'status', 'progress', 'error', 'created_at']);
        foreach ($runs as $run) {
            $out[$run->subject_id][$run->purpose] = $run;
        }

        return $out;
    }

    /** AiRunFinished: a finished notes or minutes run writes onto its transcript (minutes only until they are sent) */
    public static function fileAiOutput(AiRun $run): void
    {
        if (! $run->succeeded() || $run->subject_type !== 'meeting_transcript' || ! in_array($run->purpose, ['meeting_notes', 'client_minutes'], true)) {
            return;
        }
        $transcript = MeetingTranscript::with('meeting.opportunity')->find($run->subject_id);
        if (! $transcript) {
            return;
        }
        if ($run->purpose === 'meeting_notes') {
            $transcript->forceFill(['notes' => $run->output])->save();
        } elseif ($transcript->minutes_sent_at === null) {
            $transcript->forceFill(['minutes' => self::minutesText($run->output, $transcript->meeting->opportunity->language)])->save();
        }
    }

    /** The client_minutes answer as the editable text the lead sends: summary, decisions, next steps */
    public static function minutesText(array $minutes, string $language): string
    {
        $blocks = [trim((string) ($minutes['summary'] ?? ''))];
        $decisions = array_values(array_filter(array_map('trim', (array) ($minutes['decisions'] ?? []))));
        if ($decisions) {
            $blocks[] = __('meetings.minutes.decisions', [], $language) . "\n" . implode("\n", array_map(fn ($d) => "- {$d}", $decisions));
        }
        $steps = [];
        foreach ((array) ($minutes['next_steps'] ?? []) as $step) {
            $who = implode(', ', array_filter([trim((string) ($step['owner'] ?? '')), trim((string) ($step['due'] ?? ''))]));
            if (trim((string) ($step['action'] ?? '')) !== '') {
                $steps[] = '- ' . trim($step['action']) . ($who !== '' ? " ({$who})" : '');
            }
        }
        if ($steps) {
            $blocks[] = __('meetings.minutes.next_steps', [], $language) . "\n" . implode("\n", $steps);
        }

        return trim(implode("\n\n", array_filter($blocks)));
    }

    /**
     * Send the minutes, as edited, to everyone invited who isn't staff (branded email, the opportunity's language),
     * and show them in the portal. Returns the addresses it could not send to; nothing counts as sent if none went.
     */
    public static function sendMinutes(MeetingTranscript $transcript, string $text): array
    {
        $meeting = $transcript->meeting()->with('attendees', 'opportunity.organization')->first();
        if ($meeting->kind !== 'client' || $transcript->minutes_sent_at !== null) {
            throw new LogicException('Only unsent minutes of a client meeting can be sent');
        }
        $recipients = $meeting->attendees->whereIn('role', Meetings::EMAILED_ROLES);
        $transcript->forceFill(['minutes' => trim($text), 'updated_by' => Auth::guard('web')->id()])->save();

        $unsent = [];
        foreach ($recipients as $a) {
            try {
                Mail::to($a->email, $a->name)->locale($meeting->opportunity->language)
                    ->send(new MeetingMinutesMail($transcript, $a->client_user_id, Meetings::firstName($a)));
            } catch (Throwable $e) {
                Log::warning('Meetings: minutes could not be sent', ['transcript' => $transcript->id, 'error' => $e->getMessage()]);
                $unsent[] = $a->email;
            }
        }
        if (count($unsent) < $recipients->count()) {
            $transcript->forceFill(['minutes_sent_at' => now()])->save();
            OpportunityAccess::log('MINUTES_SENT', sprintf('Sent the minutes of “%s” to %d %s.', $meeting->title, $recipients->count() - count($unsent),
                $recipients->count() - count($unsent) === 1 ? 'person' : 'people'), 'opportunities', $meeting->opportunity_id, [], ['transcript_id' => $transcript->id]);
        }

        return $unsent;
    }

    private static function file(Meeting $meeting, array $values, bool $automatic): MeetingTranscript
    {
        $transcript = DB::transaction(function () use ($meeting, $values) {
            $transcript = $meeting->transcripts()->create($values + ['collected_at' => now(), 'created_by' => Auth::guard('web')->id()]);
            $meeting->forceFill([
                'transcript_status' => 'ready',
                'transcript_next_try_at' => null,
                'status' => $meeting->status === 'scheduled' && $meeting->starts_at->isPast() ? 'held' : $meeting->status,
            ])->save();

            return $transcript;
        });

        OpportunityAccess::log($automatic ? 'TRANSCRIPT_COLLECTED' : 'TRANSCRIPT_ADDED',
            sprintf('%s the transcript of “%s” (%s).', $automatic ? 'Collected' : 'Added', $meeting->title, $values['source']),
            'opportunities', $meeting->opportunity_id, [], ['meeting_id' => $meeting->id, 'transcript_id' => $transcript->id, 'source' => $values['source']]);
        TranscriptCollected::dispatch($transcript, $automatic);

        return $transcript;
    }

    private static function draft(string $purpose, MeetingTranscript $transcript): AiRun
    {
        $meeting = $transcript->meeting()->with('attendees', 'opportunity.organization')->first();
        $opportunity = $meeting->opportunity;

        return Ai::queue($purpose, $transcript, [
            // Who the client is, cached after the company brief; the transcript itself is this job's material
            'context' => OpportunityContext::for($opportunity, include: ['profile']),
            'material' => self::material($meeting, $transcript),
            'language' => $opportunity->language,
        ]);
    }

    private static function material(Meeting $meeting, MeetingTranscript $transcript): string
    {
        $org = $meeting->opportunity->organization?->name ?: 'the client';
        $who = $meeting->attendees->map(fn ($a) => ($a->name ?: $a->email) . ' (' . match ($a->role) {
            'staff' => 'JBS Praxis', 'client' => $org, default => 'guest',
        } . ')')->implode('; ');

        return implode("\n", array_filter([
            sprintf('Meeting: %s (%s meeting), %s WAT', $meeting->title, $meeting->kind, Meetings::when($meeting)),
            $who !== '' ? "Attendees: {$who}" : null,
            filled($meeting->agenda) ? 'Agenda: ' . trim($meeting->agenda) : null,
            '',
            'Transcript:',
            trim($transcript->text),
        ], fn ($line) => $line !== null));
    }
}
