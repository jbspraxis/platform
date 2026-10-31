<?php

namespace App\Support\Opportunities;

use App\Models\Meeting;
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\StatementOfWork;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The text the AI reads about an opportunity (docs/OPPORTUNITIES.md 8.4): the organisation and its cost of poor
 * quality estimate, every meeting in date order (kind, attendees, notes, and the transcript when asked), the
 * questionnaire's questions and answers, then the approved proposal and the SOW when they exist.
 *
 *   OpportunityContext::for($opportunity)                                     everything but full transcripts
 *   OpportunityContext::for($opportunity, include: [...OpportunityContext::DEFAULT, 'transcripts'])
 *
 * It is cached after the company brief (prompt caching), so it is deterministic: the same records give the same
 * text, with no "today" in it. Labels are English; the AI writes in the opportunity's language. Dates are
 * formatted without intl (the server's command-line PHP, which runs the AI queue, has none).
 */
final class OpportunityContext
{
    public const PARTS = ['profile', 'copq', 'meetings', 'internal', 'notes', 'transcripts', 'questionnaire', 'proposal', 'sow'];

    /** Full transcripts are read once (by the proposal); later documents read the notes (6.2, cost rule 4) */
    public const DEFAULT = ['profile', 'copq', 'meetings', 'internal', 'notes', 'questionnaire', 'proposal', 'sow'];

    public static function for(Opportunity $opportunity, array $include = self::DEFAULT): string
    {
        $want = fn (string $part) => in_array($part, $include, true);
        $blocks = [];

        if ($want('profile')) {
            $blocks[] = self::profile($opportunity);
        }
        if ($want('copq') && ($copq = self::copq($opportunity))) {
            $blocks[] = $copq;
        }
        if ($want('meetings') && ($meetings = self::meetings($opportunity, $want('internal'), $want('notes'), $want('transcripts')))) {
            $blocks[] = $meetings;
        }
        if ($want('questionnaire') && ($questionnaire = self::questionnaire($opportunity))) {
            $blocks[] = $questionnaire;
        }
        if ($want('proposal') && ($proposal = self::proposal($opportunity))) {
            $blocks[] = $proposal;
        }
        if ($want('sow') && ($sow = self::sow($opportunity))) {
            $blocks[] = $sow;
        }

        return implode("\n\n", array_filter($blocks));
    }

    private static function profile(Opportunity $opportunity): string
    {
        $org = $opportunity->organization;
        $lines = ['ORGANISATION'];
        foreach ([
            'Name' => $org?->name, 'Industry' => $org?->industry, 'Country' => $org?->country, 'City' => $org?->city,
            'Stage of the organisation' => $org?->enterprise_stage, 'Systems in use' => $org?->tech_reality,
            'Governance' => $org?->governance_status, 'Annual turnover bracket' => $org?->turnover_bracket,
            'Budget indicated' => $org?->budget_estimation,
        ] as $label => $value) {
            if (filled($value)) {
                $lines[] = "{$label}: {$value}";
            }
        }

        $lines[] = '';
        $lines[] = 'THIS PIECE OF WORK';
        $lines[] = 'Title: ' . $opportunity->title;
        $lines[] = 'Stage: ' . str_replace('_', ' ', $opportunity->stage);
        $lines[] = 'Language of the documents: ' . ($opportunity->language === 'fr' ? 'French' : 'English');
        $lines[] = 'Opened: ' . self::date($opportunity->created_at);

        return implode("\n", $lines);
    }

    private static function copq(Opportunity $opportunity): ?string
    {
        $copq = DB::table('copq_assessments')->where('organization_id', $opportunity->organization_id)->orderByDesc('created_at')->orderByDesc('id')->first();
        if (! $copq) {
            return null;
        }

        $lines = ['COST OF POOR QUALITY (the latest estimate, ' . self::date($copq->created_at) . '; an estimate, never a guaranteed saving)'];
        foreach ([
            'Industry' => $copq->copq_industry, 'Main bottleneck' => $copq->bottleneck, 'Main system in use' => $copq->primary_tool,
            'Headcount' => $copq->headcount, 'Managers who approve work' => $copq->mgmt_nodes,
        ] as $label => $value) {
            if (filled($value)) {
                $lines[] = "{$label}: {$value}";
            }
        }
        if ($copq->calculated_leakage !== null) {
            $lines[] = 'Estimated cost of poor quality a year: ' . $copq->copq_currency . ' ' . number_format((float) $copq->calculated_leakage, 0, '.', ',');
        }
        if (filled($copq->ai_friction_analysis)) {
            $lines[] = 'Analysis: ' . trim($copq->ai_friction_analysis);
        }

        return implode("\n", $lines);
    }

    private static function meetings(Opportunity $opportunity, bool $internal, bool $notes, bool $transcripts): ?string
    {
        $meetings = Meeting::where('opportunity_id', $opportunity->id)
            ->when(! $internal, fn ($q) => $q->where('kind', 'client'))
            ->where('status', '!=', 'cancelled')
            ->with(['attendees' => fn ($q) => $q->orderBy('role')->orderBy('email'), 'transcripts' => fn ($q) => $q->orderBy('collected_at')->orderBy('id')])
            ->orderBy('starts_at')->orderBy('id')->get();
        if ($meetings->isEmpty()) {
            return null;
        }

        $out = ['MEETINGS (in date order)'];
        foreach ($meetings as $i => $m) {
            $out[] = '';
            $out[] = sprintf('%d. %s meeting: %s, %s (%s)', $i + 1, $m->kind === 'internal' ? 'Internal' : 'Client', $m->title,
                self::date($m->starts_at, $m->time_zone, withTime: true), $m->status);
            if ($m->attendees->isNotEmpty()) {
                $out[] = 'Attendees: ' . $m->attendees->map(fn ($a) => trim(($a->name ?: $a->email) . " ({$a->role})"))->implode('; ');
            }
            if (filled($m->agenda)) {
                $out[] = 'Agenda: ' . trim($m->agenda);
            }
            foreach ($m->transcripts as $t) {
                if ($notes && is_array($t->notes) && $t->notes) {
                    $out[] = 'Notes:';
                    $out[] = self::notes($t->notes);
                }
                if ($notes && filled($t->minutes) && $t->minutes_sent_at) {
                    $out[] = 'Minutes sent to the client: ' . trim($t->minutes);
                }
                if ($transcripts && filled($t->text)) {
                    $out[] = 'Transcript:';
                    $out[] = trim($t->text);
                }
            }
            if ($m->transcripts->isEmpty() && $m->status === 'held') {
                $out[] = 'No transcript was filed for this meeting.';
            }
        }

        return implode("\n", $out);
    }

    /** The meeting_notes output as plain lines */
    private static function notes(array $notes): string
    {
        $lines = [];
        if (filled($notes['summary'] ?? null)) {
            $lines[] = '  Summary: ' . $notes['summary'];
        }
        foreach (['decisions' => 'Decisions', 'needs' => 'Needs', 'risks' => 'Risks', 'open_questions' => 'Open questions'] as $key => $label) {
            foreach ((array) ($notes[$key] ?? []) as $item) {
                $lines[] = "  {$label}: {$item}";
            }
        }
        foreach ((array) ($notes['next_steps'] ?? []) as $step) {
            $lines[] = '  Next step: ' . ($step['action'] ?? '') . (filled($step['owner'] ?? null) ? " (owner: {$step['owner']})" : '') . (filled($step['due'] ?? null) ? " (due: {$step['due']})" : '');
        }

        return implode("\n", $lines);
    }

    private static function questionnaire(Opportunity $opportunity): ?string
    {
        $questionnaire = $opportunity->questionnaires()->whereIn('status', ['open', 'complete'])->with('questions')->first();
        if (! $questionnaire || $questionnaire->questions->isEmpty()) {
            return null;
        }

        $out = ['QUESTIONNAIRE (answered by our team' . ($questionnaire->completed_at ? ', completed ' . self::date($questionnaire->completed_at) : ', in progress') . ')'];
        foreach ($questionnaire->questions as $q) {
            $options = (array) $q->options;
            $answer = match (true) {
                filled($q->answer_text) && $q->answer_option !== null => ($options[$q->answer_option] ?? '') . ' — ' . $q->answer_text,
                filled($q->answer_text) => $q->answer_text,
                $q->answer_option !== null => $options[$q->answer_option] ?? null,
                default => null,
            };
            $out[] = '';
            $out[] = "Q{$q->position}. {$q->question}";
            $out[] = 'Answer: ' . ($answer ?: 'not answered yet');
        }

        return implode("\n", $out);
    }

    private static function proposal(Opportunity $opportunity): ?string
    {
        // Approved by the client (PR 4), or signed under the legacy flow
        $proposal = Proposal::where('opportunity_id', $opportunity->id)
            ->where(fn ($q) => $q->whereNotNull('client_approved_at')->orWhereIn('status', ['Authorized', 'Converted']))
            ->orderByDesc('version')->orderByDesc('created_at')->first();
        if (! $proposal) {
            return null;
        }

        $out = ['APPROVED PROPOSAL' . ($proposal->proposal_ref ? " {$proposal->proposal_ref}" : '') . " (version {$proposal->version})"];
        if (filled($proposal->title)) {
            $out[] = 'Title: ' . $proposal->title;
        }
        if (is_array($proposal->sections) && $proposal->sections) {
            foreach ($proposal->sections as $section) {
                $out[] = '';
                $out[] = strtoupper((string) ($section['title'] ?? $section['key'] ?? ''));
                $out[] = trim((string) ($section['body'] ?? ''));
            }
        } else {
            // A proposal from before PR 4: its summary and the AI narrative it was drafted with
            $narrative = DB::table('proposal_ai_narratives')->where('proposal_id', $proposal->id)->orderByDesc('id')->first();
            foreach ([
                'Executive summary' => $proposal->executive_summary ?: $narrative?->ai_executive_summary,
                'Approach' => $narrative?->ai_operational_strategy,
                'Exclusions' => $proposal->exclusions,
            ] as $label => $text) {
                if (filled($text)) {
                    $out[] = '';
                    $out[] = strtoupper($label);
                    $out[] = trim($text);
                }
            }
        }
        $lines = DB::table('proposal_line_items')->where('proposal_id', $proposal->id)->orderBy('sort_order')->orderBy('id')->get();
        if ($lines->isNotEmpty()) {
            $out[] = '';
            $out[] = 'LINE ITEMS (' . $proposal->currency . ')';
            foreach ($lines as $line) {
                $out[] = "- {$line->code} {$line->description}: {$line->quantity} × " . number_format((float) $line->unit_price, 2, '.', ',');
            }
            $out[] = 'Total: ' . $proposal->currency . ' ' . number_format((float) $proposal->total_value, 2, '.', ',');
        }

        return implode("\n", $out);
    }

    private static function sow(Opportunity $opportunity): ?string
    {
        $sow = StatementOfWork::where('opportunity_id', $opportunity->id)->whereIn('status', ['signed', 'sent', 'awaiting_approval', 'draft'])
            ->orderByRaw("FIELD(status, 'signed', 'sent', 'awaiting_approval', 'draft')")->orderByDesc('version')->first();
        if (! $sow || ! is_array($sow->sections) || ! $sow->sections) {
            return null;
        }

        $out = ['STATEMENT OF WORK' . ($sow->reference ? " {$sow->reference}" : '') . " (version {$sow->version}, {$sow->status})"];
        foreach ($sow->sections as $section) {
            $out[] = '';
            $out[] = strtoupper((string) ($section['title'] ?? $section['key'] ?? ''));
            $out[] = trim((string) ($section['body'] ?? ''));
        }

        return implode("\n", $out);
    }

    /** "Thursday 5 November 2026" or "Thursday 5 November 2026, 10:30 (WAT)", in English without intl */
    private static function date(CarbonInterface|string|null $at, string $tz = 'Africa/Lagos', bool $withTime = false): string
    {
        if (! $at) {
            return 'unknown date';
        }
        $local = Carbon::parse($at)->setTimezone($tz)->locale('en');

        return $local->format('l j F Y') . ($withTime ? ', ' . $local->format('H:i') . ($tz === 'Africa/Lagos' ? ' (WAT)' : " ({$tz})") : '');
    }
}
