<?php

namespace App\Support\Leads;

use App\Models\AiRun;
use App\Models\LeadIntelReport;
use App\Models\Organization;
use App\Models\User;
use App\Support\Ai\Ai;
use App\Support\Ai\AiUnavailable;
use App\Support\CompanyDocuments\CannotRead;
use App\Support\CompanyDocuments\Reader;
use App\Support\Opportunities\OpportunityContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Intel reports on a lead (docs/LEADS.md section 13). The sales team gathers research on a company before it is a
 * client: the tools it uses and what they cost, who decides, what hurts. A report goes in as a file (PDF, Word, text,
 * Markdown) or as pasted text; Claude puts it into one fixed layout (purpose lead_intel_read, queued); a person checks
 * and edits the result; then the AI documents read it (OpportunityContext 'intel', the consultation line, the pitch
 * brief). The original is kept as it came. A report is only ever as good as its source: costs are a basis (stated,
 * estimated, free, unknown) and totals count only what has an amount.
 *
 * Who: crm.edit sees and changes intel (it is research that was not given to us, so a view-only colleague does not
 * see it). The AI is told never to quote it or hint where it came from, so nothing a client sees gives it away.
 */
final class LeadIntel
{
    public const READ = 'lead_intel_read';

    public const PREFILL = 'lead_intel_prefill';

    public const BRIEF = 'lead_pitch_brief';

    /** What can be uploaded: extension => the mime type kept with the report */
    public const FILES = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt' => 'text/plain',
        'md' => 'text/markdown',
    ];

    public const MAX_KB = 10240;

    public const MAX_TEXT = 60000;

    public const DEAL_ROLES = ['decision_maker' => 'Decision maker', 'finance' => 'Finance', 'technical' => 'Technical', 'user' => 'User', 'unknown' => 'Role not known'];

    public const COST_BASES = ['stated' => 'Stated', 'estimated' => 'Estimate', 'free' => 'Free', 'unknown' => 'Not known'];

    public const CONFIDENCE = ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];

    /** The most rows one report keeps in each list */
    public const MAX_ROWS = ['decision_makers' => 30, 'tools' => 60, 'pain_points' => 30, 'open_questions' => 30];

    public static function canUse(?User $user): bool
    {
        return LeadAccess::canEdit($user);
    }

    /** A report with nothing in it yet: what an edit form starts from */
    public static function blank(): array
    {
        return self::normalise([]);
    }

    // ------------------------------------------------------------------ getting a report in

    /** The file the person chose, on the private disk (an inbox until a lead owns it): path, name, mime, size */
    public static function storeUpload(UploadedFile $file): array
    {
        // Everything is read from the upload before it is stored: a Livewire upload is moved by storeAs, and asking it
        // for its size afterwards finds nothing (seen in the browser, not in tests)
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $name = mb_substr((string) $file->getClientOriginalName(), 0, 255);
        $size = (int) $file->getSize();
        if (! isset(self::FILES[$ext])) {
            throw new CannotRead('Upload a PDF, a Word file (.docx), a text file or a Markdown file.');
        }
        if ($size > self::MAX_KB * 1024) {
            throw new CannotRead('That file is over ' . (self::MAX_KB / 1024) . ' MB. Split it, or paste the text instead.');
        }
        self::purgeInbox();
        $path = $file->storeAs('lead-intel/inbox', Str::uuid() . '.' . $ext, 'local');

        return ['kind' => 'file', 'path' => $path, 'name' => $name, 'mime' => self::FILES[$ext], 'size' => $size];
    }

    /** Files chosen on Add a lead and never attached (the tab was closed) are removed after two days */
    public static function purgeInbox(): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->files('lead-intel/inbox') as $path) {
            if ($disk->lastModified($path) < now()->subDays(2)->getTimestamp()) {
                $disk->delete($path);
            }
        }
    }

    /** Text typed or pasted in */
    public static function textSource(string $text): array
    {
        return ['kind' => 'text', 'text' => mb_substr(trim($text), 0, self::MAX_TEXT * 2)];
    }

    /**
     * What Claude is given for a source: the report's words, or the PDF itself. $preface goes first in MATERIAL.
     *
     * @return array{material: string, files?: list<array{path: string, media_type: string}>}
     */
    public static function input(array $source, string $preface = ''): array
    {
        if (($source['kind'] ?? '') === 'text') {
            $text = trim((string) ($source['text'] ?? ''));
            if ($text === '') {
                throw new CannotRead('There is no text to read.');
            }

            return ['material' => $preface . "THE REPORT (text typed or pasted in):\n" . Str::limit($text, self::MAX_TEXT, '')];
        }

        $path = (string) ($source['path'] ?? '');
        $mime = (string) ($source['mime'] ?? '');
        if ($mime === self::FILES['pdf']) {
            if (! Storage::disk('local')->exists($path)) {
                throw new CannotRead('The file is no longer there. Upload it again.');
            }

            return ['material' => $preface . 'THE REPORT is the attached PDF.', 'files' => [['path' => $path, 'media_type' => 'application/pdf']]];
        }

        $bytes = Storage::disk('local')->get($path);
        if ($bytes === null) {
            throw new CannotRead('The file is no longer there. Upload it again.');
        }
        if ($mime === self::FILES['docx']) {
            $text = Reader::docxText($bytes);
        } else {
            $text = trim(mb_check_encoding($bytes, 'UTF-8') ? $bytes : mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252'));
        }
        if ($text === '') {
            throw new CannotRead('We could not find any text in that file. Paste the text instead.');
        }

        return ['material' => $preface . "THE REPORT (the text of the file):\n" . Str::limit($text, self::MAX_TEXT, '')];
    }

    /**
     * Adds a report to a lead and asks Claude to read it. $source: storeUpload() or textSource(). The report exists even
     * when Claude can't be reached (read_error says why): the person can read the original and try again.
     */
    public static function add(Organization $lead, array $source, ?User $by, string $title = ''): LeadIntelReport
    {
        $id = (string) Str::uuid();
        $isFile = ($source['kind'] ?? '') === 'file';
        $path = null;
        if ($isFile) {
            $ext = pathinfo((string) $source['path'], PATHINFO_EXTENSION);
            $path = "lead-intel/{$lead->id}/{$id}.{$ext}";
            Storage::disk('local')->move($source['path'], $path);
        }
        $title = trim($title) !== '' ? $title : ($isFile ? pathinfo((string) $source['name'], PATHINFO_FILENAME) : 'Notes added ' . now('Africa/Lagos')->format('j M Y'));

        $report = LeadIntelReport::create([
            'id' => $id,
            'organization_id' => $lead->id,
            'title' => mb_substr($title, 0, 200),
            'input_kind' => $isFile ? 'file' : 'text',
            'original_name' => $isFile ? $source['name'] : null,
            'path' => $path,
            'mime' => $isFile ? $source['mime'] : null,
            'size' => $isFile ? $source['size'] : null,
            'raw_text' => $isFile ? null : $source['text'],
            'created_by' => $by?->id,
            'updated_by' => $by?->id,
        ]);

        LeadTimeline::record($lead->id, 'intel_added', 'An intel report was added.', ['actor' => $by, 'related_type' => 'lead_intel_report', 'related_id' => $id]);
        LeadAccess::log('LEAD_INTEL_ADDED', "Added an intel report to the lead {$lead->name}.", $lead->id, [], ['intel_report_id' => $id, 'kind' => $report->input_kind]);
        self::read($report);

        return $report->fresh();
    }

    /** Asks Claude to read (or read again) the report in the background; the Intel tab shows the progress */
    public static function read(LeadIntelReport $report): void
    {
        $report->forceFill(['ai_run_id' => null, 'read_error' => null])->save();

        try {
            $company = (string) DB::table('organizations')->where('id', $report->organization_id)->value('name');
            $source = $report->input_kind === 'file'
                ? ['kind' => 'file', 'path' => $report->path, 'mime' => $report->mime]
                : ['kind' => 'text', 'text' => (string) $report->raw_text];
            $run = Ai::queue(self::READ, $report, self::input($source, "COMPANY: {$company}\nTITLE: {$report->title}\n\n"));
            $report->forceFill(['ai_run_id' => $run->id])->save();
        } catch (AiUnavailable $e) {
            $report->forceFill(['read_error' => $e->key()])->save();
        } catch (CannotRead $e) {
            $report->forceFill(['read_error' => $e->getMessage()])->save();
        }
    }

    /** A finished reading files its answer on the report (FileLeadIntelAi calls this); a report a person has deleted is left alone */
    public static function fileOutput(AiRun $run): void
    {
        if ($run->purpose !== self::READ || ! $run->succeeded() || $run->subject_type !== (new LeadIntelReport)->getMorphClass()) {
            return;
        }
        $report = LeadIntelReport::find($run->subject_id);
        if (! $report) {
            return;
        }
        $report->forceFill(['structured' => self::normalise((array) $run->output), 'structured_at' => now(), 'read_error' => null])->save();
    }

    /**
     * Where the reading stands: reading (with progress), failed (with what to tell the person), ready or none.
     * A report that was read before and is being read again is still ready to show: reading says so alongside.
     *
     * @return array{state: string, message: ?string, progress: int, has: bool}
     */
    public static function state(LeadIntelReport $report): array
    {
        $run = $report->ai_run_id ? AiRun::find($report->ai_run_id) : null;
        if ($run && $run->succeeded() && $report->structured === null) {
            self::fileOutput($run);
            $report->refresh();
        }
        $has = $report->structured !== null;

        if ($report->read_error) {
            return ['state' => 'failed', 'message' => __($report->read_error), 'progress' => 0, 'has' => $has];
        }
        if ($run && ! $run->isFinished()) {
            return ['state' => 'reading', 'message' => null, 'progress' => (int) $run->progress, 'has' => $has];
        }
        if ($run && $run->status === 'failed') {
            return ['state' => 'failed', 'message' => $run->errorMessage(), 'progress' => 0, 'has' => $has];
        }

        return ['state' => $has ? 'ready' : 'none', 'message' => null, 'progress' => 0, 'has' => $has];
    }

    // ------------------------------------------------------------------ keeping it

    /** A person's edits (or Claude's answer) in the stored layout; lists are cut to MAX_ROWS and text to a sane length */
    public static function update(LeadIntelReport $report, array $data, ?User $by): LeadIntelReport
    {
        $report->forceFill(['structured' => self::normalise($data), 'structured_at' => now(), 'updated_by' => $by?->id])->save();
        LeadAccess::log('LEAD_INTEL_EDITED', 'Edited an intel report.', $report->organization_id, [], ['intel_report_id' => $report->id]);

        return $report;
    }

    public static function rename(LeadIntelReport $report, string $title, ?User $by): void
    {
        $title = mb_substr(trim($title), 0, 200);
        if ($title !== '') {
            $report->forceFill(['title' => $title, 'updated_by' => $by?->id])->save();
        }
    }

    public static function useForAi(LeadIntelReport $report, bool $on, ?User $by): void
    {
        $report->forceFill(['use_for_ai' => $on, 'updated_by' => $by?->id])->save();
        LeadAccess::log('LEAD_INTEL_AI_' . ($on ? 'ON' : 'OFF'), ($on ? 'Allowed' : 'Stopped') . ' the AI reading an intel report.', $report->organization_id, [], ['intel_report_id' => $report->id]);
    }

    /** Deletes the report and its file (the timeline keeps the line that one was removed) */
    public static function remove(LeadIntelReport $report, ?User $by): void
    {
        if ($report->path) {
            Storage::disk('local')->delete($report->path);
        }
        $orgId = $report->organization_id;
        $report->forceFill(['updated_by' => $by?->id])->save();
        $report->delete();
        LeadTimeline::record($orgId, 'intel_removed', 'An intel report was removed.', ['actor' => $by, 'related_type' => 'lead_intel_report', 'related_id' => $report->id]);
        LeadAccess::log('LEAD_INTEL_REMOVED', 'Removed an intel report.', $orgId, [], ['intel_report_id' => $report->id]);
    }

    /** A lead's reports, newest first */
    public static function reportsFor(string $orgId): Collection
    {
        return LeadIntelReport::where('organization_id', $orgId)->orderByDesc('created_at')->orderByDesc('id')->get();
    }

    // ------------------------------------------------------------------ the layout

    /**
     * Any answer (Claude's, or an edit form's) as the one stored layout: every key present, text trimmed and cut, roles
     * and bases from their lists, money as numbers. A tool without a usable amount counts as not known, so a total
     * never rests on a number nobody gave.
     */
    public static function normalise(array $in): array
    {
        $text = fn ($v, int $max = 6000) => mb_substr(trim((string) ($v ?? '')), 0, $max);
        $rows = fn ($v, string $key) => array_slice(array_values(array_filter((array) $v, 'is_array')), 0, self::MAX_ROWS[$key]);
        $lines = fn ($v) => array_slice(array_values(array_filter(array_map(fn ($l) => $text(is_array($l) ? '' : $l, 500), (array) $v), fn ($l) => $l !== '')), 0, self::MAX_ROWS['open_questions']);

        $snapshot = (array) ($in['snapshot'] ?? []);
        $people = [];
        foreach ($rows($in['decision_makers'] ?? [], 'decision_makers') as $p) {
            $person = [
                'name' => $text($p['name'] ?? '', 150), 'role' => $text($p['role'] ?? '', 150),
                'deal_role' => array_key_exists($p['deal_role'] ?? '', self::DEAL_ROLES) ? $p['deal_role'] : 'unknown',
                'contact' => $text($p['contact'] ?? '', 200), 'approach' => $text($p['approach'] ?? '', 1500),
            ];
            if ($person['name'] !== '' || $person['role'] !== '') {
                $people[] = $person;
            }
        }

        $toolsIn = (array) ($in['tools'] ?? []);
        $currency = strtoupper($text($toolsIn['currency'] ?? '', 3));
        $currency = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : '';
        $tools = [];
        foreach ($rows($toolsIn['items'] ?? [], 'tools') as $t) {
            $basis = array_key_exists($t['cost_basis'] ?? '', self::COST_BASES) ? $t['cost_basis'] : 'unknown';
            $period = in_array($t['cost_period'] ?? '', ['month', 'year'], true) ? $t['cost_period'] : 'none';
            $amount = is_numeric($t['cost_amount'] ?? null) ? round(max(0.0, min((float) $t['cost_amount'], 1.0e12)), 2) : 0.0;
            if ($basis === 'free') {
                [$amount, $period] = [0.0, 'none'];
            } elseif ($basis === 'unknown' || $amount <= 0 || $period === 'none') {
                [$amount, $period, $basis] = [0.0, 'none', 'unknown'];
            }
            $tool = ['tool' => $text($t['tool'] ?? '', 150), 'used_for' => $text($t['used_for'] ?? '', 300),
                'cost_amount' => $amount, 'cost_period' => $period, 'cost_basis' => $basis, 'note' => $text($t['note'] ?? '', 500)];
            if ($tool['tool'] !== '') {
                $tools[] = $tool;
            }
        }

        $pains = [];
        foreach ($rows($in['pain_points'] ?? [], 'pain_points') as $p) {
            $pain = ['point' => $text($p['point'] ?? '', 300), 'detail' => $text($p['detail'] ?? '', 800)];
            if ($pain['point'] !== '') {
                $pains[] = $pain;
            }
        }

        $source = (array) ($in['source'] ?? []);

        return [
            'snapshot' => ['summary' => $text($snapshot['summary'] ?? ''), 'size' => $text($snapshot['size'] ?? '', 300),
                'location' => $text($snapshot['location'] ?? '', 300), 'owners' => $text($snapshot['owners'] ?? '', 500)],
            'decision_makers' => $people,
            'tools' => ['currency' => $currency, 'items' => $tools],
            'pain_points' => $pains,
            'how_we_can_help' => $text($in['how_we_can_help'] ?? ''),
            'meeting_approach' => $text($in['meeting_approach'] ?? ''),
            'source' => ['how' => $text($source['how'] ?? '', 800),
                'confidence' => array_key_exists($source['confidence'] ?? '', self::CONFIDENCE) ? $source['confidence'] : 'low',
                'note' => $text($source['note'] ?? '', 1000)],
            'open_questions' => $lines($in['open_questions'] ?? []),
            'other_findings' => $text($in['other_findings'] ?? ''),
        ];
    }

    /**
     * What the tools cost, from the amounts that exist. A year's price counts a twelfth a month. Only tools with a
     * stated or estimated amount are added up; free ones and ones without a price are counted beside it, so the total
     * is never read as the whole bill. "estimate" is true when any counted amount was a guess.
     *
     * @return array{currency: string, monthly: float, yearly: float, counted: int, free: int, unknown: int, estimate: bool}
     */
    public static function totals(array $structured): array
    {
        $tools = (array) ($structured['tools'] ?? []);
        $monthly = 0.0;
        $out = ['currency' => (string) ($tools['currency'] ?? ''), 'counted' => 0, 'free' => 0, 'unknown' => 0, 'estimate' => false];
        foreach ((array) ($tools['items'] ?? []) as $t) {
            if (($t['cost_basis'] ?? '') === 'free') {
                $out['free']++;
            } elseif (in_array($t['cost_basis'] ?? '', ['stated', 'estimated'], true) && ($t['cost_amount'] ?? 0) > 0) {
                $monthly += ($t['cost_period'] ?? '') === 'year' ? $t['cost_amount'] / 12 : $t['cost_amount'];
                $out['counted']++;
                $out['estimate'] = $out['estimate'] || $t['cost_basis'] === 'estimated';
            } else {
                $out['unknown']++;
            }
        }

        return $out + ['monthly' => round($monthly, 2), 'yearly' => round($monthly * 12, 2)];
    }

    // ------------------------------------------------------------------ what the AI reads

    /**
     * The intel the AI may read for a lead, as text: every report that is switched on and has been read, oldest first
     * (deterministic: the opportunity context is cached, so the same records must give the same text). Null when
     * there is none.
     */
    public static function contextText(string $orgId): ?string
    {
        $reports = LeadIntelReport::where('organization_id', $orgId)->where('use_for_ai', true)->whereNotNull('structured')
            ->orderBy('created_at')->orderBy('id')->get();
        if ($reports->isEmpty()) {
            return null;
        }

        $blocks = ['CONFIDENTIAL INTEL ON THIS ORGANISATION (research by the JBS Praxis sales team, checked by a person). Use it to make the document fit this organisation: its real tools, people and pains. Never quote it, never say or hint that we hold research on them, and never say how or from whom it was obtained. It is the sales team’s view, not a promise: where it is marked as an estimate, say so, and where it is silent, say plainly what is not known yet.'];
        foreach ($reports as $i => $report) {
            $blocks[] = self::describe($report, $i + 1);
        }

        return implode("\n\n", $blocks);
    }

    private static function describe(LeadIntelReport $report, int $n): string
    {
        $s = $report->structured;
        $out = ["INTEL REPORT {$n}: {$report->title} (added " . $report->created_at->format('j M Y') . ')'];
        $add = function (string $label, ?string $value) use (&$out) {
            if (filled($value)) {
                $out[] = "{$label}: " . $value;
            }
        };

        $snap = $s['snapshot'] ?? [];
        $add('What they do', $snap['summary'] ?? '');
        $add('Size', $snap['size'] ?? '');
        $add('Where', $snap['location'] ?? '');
        $add('Owners', $snap['owners'] ?? '');

        if (! empty($s['decision_makers'])) {
            $out[] = 'People:';
            foreach ($s['decision_makers'] as $p) {
                $out[] = '- ' . trim($p['name'] . ($p['role'] !== '' ? ', ' . $p['role'] : '')) . ' (' . (self::DEAL_ROLES[$p['deal_role']] ?? 'Role not known') . ')'
                    . ($p['approach'] !== '' ? '. How to approach: ' . $p['approach'] : '');
            }
        }

        $tools = $s['tools']['items'] ?? [];
        if ($tools) {
            $cur = $s['tools']['currency'] ?? '';
            $out[] = 'Tools in use' . ($cur !== '' ? " (amounts in {$cur})" : '') . ':';
            foreach ($tools as $t) {
                $cost = match ($t['cost_basis']) {
                    'free' => 'free',
                    'unknown' => 'cost not known',
                    default => rtrim(rtrim(number_format($t['cost_amount'], 2, '.', ''), '0'), '.') . ' a ' . $t['cost_period'] . ' (' . ($t['cost_basis'] === 'estimated' ? 'an estimate' : 'stated') . ')',
                };
                $out[] = '- ' . $t['tool'] . ($t['used_for'] !== '' ? ': ' . $t['used_for'] : '') . '. ' . ucfirst($cost) . ($t['note'] !== '' ? '. ' . $t['note'] : '');
            }
            $totals = self::totals($s);
            if ($totals['counted'] > 0 && $totals['currency'] !== '') {
                $out[] = "Added up: about {$totals['currency']} " . number_format($totals['monthly']) . ' a month, ' . number_format($totals['yearly']) . ' a year, for the '
                    . $totals['counted'] . ' tool' . ($totals['counted'] === 1 ? '' : 's') . ' with a price' . ($totals['estimate'] ? ' (partly estimated)' : '')
                    . ($totals['unknown'] > 0 ? "; {$totals['unknown']} more have no price, so the real bill is higher" : '') . '.';
            }
        }

        if (! empty($s['pain_points'])) {
            $out[] = 'Pain points and signals:';
            foreach ($s['pain_points'] as $p) {
                $out[] = '- ' . $p['point'] . ($p['detail'] !== '' ? ': ' . $p['detail'] : '');
            }
        }
        $add('Where we could help (the sales team’s view)', $s['how_we_can_help'] ?? '');
        $add('Meeting approach', $s['meeting_approach'] ?? '');
        $src = $s['source'] ?? [];
        if (filled($src['how'] ?? '')) {
            $out[] = 'Source (internal, never to be revealed): ' . $src['how'] . ' (confidence: ' . ($src['confidence'] ?? 'low') . ')' . (filled($src['note'] ?? '') ? '. ' . $src['note'] : '');
        }
        if (! empty($s['open_questions'])) {
            $out[] = 'Still unknown: ' . implode('; ', $s['open_questions']);
        }
        $add('Other findings', $s['other_findings'] ?? '');

        return Str::limit(implode("\n", $out), 20000, '…');
    }

    // ------------------------------------------------------------------ Add a lead, and the pitch brief

    /**
     * "Fill from the report": the facts for the Add a lead form, read while the person waits.
     *
     * @return array{ok: bool, fields: ?array, message: ?string}
     */
    public static function prefill(array $source): array
    {
        try {
            $industries = implode("\n", array_map(fn ($i) => "- {$i}", Leads::industries()));
            $run = Ai::now(self::PREFILL, ['type' => 'lead_intel_draft', 'id' => (string) Str::uuid()], self::input($source, "INDUSTRIES:\n{$industries}\n\n"));
        } catch (AiUnavailable $e) {
            return ['ok' => false, 'fields' => null, 'message' => $e->getMessage()];
        } catch (CannotRead $e) {
            return ['ok' => false, 'fields' => null, 'message' => $e->getMessage()];
        }

        return $run->succeeded()
            ? ['ok' => true, 'fields' => (array) $run->output, 'message' => null]
            : ['ok' => false, 'fields' => null, 'message' => $run->errorMessage()];
    }

    /** Everything HQ holds about the lead that a pitch brief needs (internal: it includes the intel) */
    public static function briefMaterial(Organization $lead): string
    {
        $blocks = [OpportunityContext::forLead($lead)];

        $contacts = DB::table('client_users')->where('organization_id', $lead->id)->whereNull('deleted_at')
            ->orderByDesc('is_main_contact')->orderBy('first_name')->get(['first_name', 'last_name', 'job_title', 'deal_role']);
        if ($contacts->isNotEmpty()) {
            $blocks[] = "CONTACTS\n" . $contacts->map(fn ($c) => '- ' . trim($c->first_name . ' ' . $c->last_name) . ($c->job_title ? ', ' . $c->job_title : '')
                . ($c->deal_role ? ' (' . (Contacts::DEAL_ROLES[$c->deal_role] ?? $c->deal_role) . ')' : ''))->implode("\n");
        }

        $copq = DB::table('copq_assessments')->where('organization_id', $lead->id)->orderByDesc('created_at')->orderByDesc('id')->first();
        if ($copq && (float) $copq->calculated_leakage > 0) {
            $blocks[] = "COST OF POOR QUALITY (an estimate, never a guaranteed saving)\nMain bottleneck: {$copq->bottleneck}\nMain system in use: {$copq->primary_tool}\nEstimated cost a year: {$copq->copq_currency} " . number_format((float) $copq->calculated_leakage);
        }
        if (filled($lead->next_step)) {
            $blocks[] = 'NEXT STEP: ' . $lead->next_step;
        }
        $blocks[] = self::contextText($lead->id) ?? 'INTEL: none on file yet.';

        return implode("\n\n", array_filter($blocks));
    }

    /** Starts a pitch brief in the background; the Intel tab shows it when it is done. Returns a message when it can't start. */
    public static function startBrief(Organization $lead): ?string
    {
        try {
            Ai::queue(self::BRIEF, $lead, ['material' => self::briefMaterial($lead)]);
        } catch (AiUnavailable $e) {
            return $e->getMessage();
        }

        return null;
    }

    /** The lead's latest pitch brief run (queued, running, done or failed), or null */
    public static function latestBrief(string $orgId): ?AiRun
    {
        return AiRun::where('purpose', self::BRIEF)->where('subject_type', (new Organization)->getMorphClass())->where('subject_id', $orgId)
            ->orderByDesc('created_at')->first();
    }
}
