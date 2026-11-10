<?php

namespace App\Livewire\Hq\Leads;

use App\Models\LeadIntelReport;
use App\Support\CompanyDocuments\CannotRead;
use App\Support\Leads\LeadAccess;
use App\Support\Leads\LeadIntel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * A lead's Intel tab (docs/LEADS.md section 13), inside LeadPage, for people who can edit leads. The research the
 * sales team gathered on the company goes in as a file or as pasted text; Claude puts it into the fixed layout in the
 * background (LeadIntel, purpose lead_intel_read); a person checks it against the original and edits it; a report stays
 * switched on for the AI documents (proposals, the consultation line, the pitch brief) until someone switches it off.
 * Several reports per lead, newest first, each kept with its original.
 */
class IntelTab extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $leadId;

    /** The add form that is open: '' (none), 'upload' or 'paste' */
    public string $form = '';

    public $file = null;

    public string $title = '';

    public string $pasteText = '';

    /** The report that is expanded */
    public ?string $open = null;

    /** The report being edited, and its fields (the stored layout; open_questions as lines of text) */
    public ?string $editing = null;

    public array $edit = [];

    public ?string $notice = null;

    public ?string $warning = null;

    public function mount(string $leadId): void
    {
        abort_unless(LeadIntel::canUse(Auth::user()), 403);
        $lead = LeadAccess::find($leadId);
        abort_unless($lead, 404);
        $this->leadId = $lead->id;
        $this->open = LeadIntel::reportsFor($lead->id)->first()?->id;
    }

    private function authorizeEdit(): void
    {
        abort_unless(LeadIntel::canUse(Auth::user()), 403);
        $this->resetErrorBag();
        $this->notice = $this->warning = null;
    }

    private function lead()
    {
        $lead = LeadAccess::find($this->leadId);
        abort_unless($lead, 404);

        return $lead;
    }

    private function report(?string $id): LeadIntelReport
    {
        abort_unless($id, 404);

        return LeadIntelReport::where('organization_id', $this->leadId)->findOrFail($id);
    }

    // --- Adding a report ---

    public function openForm(string $kind): void
    {
        $this->authorizeEdit();
        $this->reset(['file', 'title', 'pasteText']);
        $this->form = in_array($kind, ['upload', 'paste'], true) ? $kind : '';
    }

    public function closeForm(): void
    {
        $this->form = '';
        $this->reset(['file', 'title', 'pasteText']);
        $this->resetErrorBag();
    }

    public function saveUpload(): void
    {
        $this->authorizeEdit();
        $this->validate([
            'file' => ['required', 'file', 'max:' . LeadIntel::MAX_KB, 'extensions:' . implode(',', array_keys(LeadIntel::FILES))],
            'title' => ['nullable', 'string', 'max:200'],
        ], [
            'file.required' => 'Choose a file to upload.',
            'file.max' => 'That file is over ' . (LeadIntel::MAX_KB / 1024) . ' MB. Split it, or paste the text instead.',
            'file.extensions' => 'Upload a PDF, a Word file (.docx), a text file or a Markdown file.',
            'title.max' => 'Keep the title under 200 characters.',
        ]);

        try {
            $source = LeadIntel::storeUpload($this->file);
        } catch (CannotRead $e) {
            $this->addError('file', $e->getMessage());

            return;
        }
        $this->file = null;   // storing moved Livewire's temporary file
        $this->added(LeadIntel::add($this->lead(), $source, Auth::user(), $this->title));
    }

    public function savePaste(): void
    {
        $this->authorizeEdit();
        $this->validate([
            'pasteText' => ['required', 'string', 'min:30', 'max:' . (LeadIntel::MAX_TEXT * 2)],
            'title' => ['nullable', 'string', 'max:200'],
        ], [
            'pasteText.required' => 'Paste or type the notes first.',
            'pasteText.min' => 'Add a little more: at least a couple of sentences.',
            'pasteText.max' => 'That is too long to read in one go. Split it into two reports.',
            'title.max' => 'Keep the title under 200 characters.',
        ]);

        $this->added(LeadIntel::add($this->lead(), LeadIntel::textSource($this->pasteText), Auth::user(), $this->title));
    }

    private function added(LeadIntelReport $report): void
    {
        $this->closeForm();
        $this->open = $report->id;
        $state = LeadIntel::state($report);
        if ($state['state'] === 'failed') {
            $this->warning = 'The report was added, but Claude could not read it yet: ' . $state['message'] . ' The original is kept; use Read it again when that is sorted.';
        } else {
            $this->notice = 'The report was added. Claude is putting it into the layout; it shows here within a minute or two.';
        }
        $this->dispatch('lead-changed');
    }

    // --- One report ---

    public function toggle(string $id): void
    {
        $this->open = $this->open === $id ? null : $this->report($id)->id;
    }

    public function readAgain(string $id): void
    {
        $this->authorizeEdit();
        $report = $this->report($id);
        LeadIntel::read($report);
        $state = LeadIntel::state($report->fresh());
        if ($state['state'] === 'failed') {
            $this->warning = 'Claude could not read it: ' . $state['message'];
        } else {
            $this->notice = 'Claude is reading it again. The layout updates here when it is done.';
        }
    }

    public function toggleAi(string $id): void
    {
        $this->authorizeEdit();
        $report = $this->report($id);
        LeadIntel::useForAi($report, ! $report->use_for_ai, Auth::user());
        $this->notice = $report->fresh()->use_for_ai
            ? 'The AI will read this report when it drafts proposals and briefs.'
            : 'The AI will not read this report any more. It stays here.';
    }

    public function remove(string $id): void
    {
        $this->authorizeEdit();
        LeadIntel::remove($this->report($id), Auth::user());
        if ($this->open === $id) {
            $this->open = null;
        }
        if ($this->editing === $id) {
            $this->cancelEdit();
        }
        $this->notice = 'The report and its file were removed.';
        $this->dispatch('lead-changed');
    }

    public function download(string $id)
    {
        abort_unless(LeadIntel::canUse(Auth::user()), 403);
        $report = $this->report($id);
        abort_unless($report->path && Storage::disk('local')->exists($report->path), 404);

        return Storage::disk('local')->download($report->path, $report->original_name ?: 'intel-report');
    }

    // --- Editing the layout ---

    public function startEdit(string $id): void
    {
        $this->authorizeEdit();
        $report = $this->report($id);
        $layout = $report->structured ?? LeadIntel::blank();
        $layout['open_questions'] = implode("\n", $layout['open_questions']);
        $this->edit = ['title' => $report->title] + $layout;
        $this->editing = $report->id;
        $this->open = $report->id;
    }

    public function cancelEdit(): void
    {
        $this->editing = null;
        $this->edit = [];
        $this->resetErrorBag();
    }

    public function addRow(string $list): void
    {
        $this->authorizeEdit();
        $max = LeadIntel::MAX_ROWS[$list === 'tools' ? 'tools' : $list] ?? 0;
        $rows = $list === 'tools' ? ($this->edit['tools']['items'] ?? []) : ($this->edit[$list] ?? []);
        if (! in_array($list, ['decision_makers', 'tools', 'pain_points'], true) || count($rows) >= $max) {
            return;
        }
        $rows[] = match ($list) {
            'decision_makers' => ['name' => '', 'role' => '', 'deal_role' => 'unknown', 'contact' => '', 'approach' => ''],
            'tools' => ['tool' => '', 'used_for' => '', 'cost_amount' => '', 'cost_period' => 'month', 'cost_basis' => 'unknown', 'note' => ''],
            default => ['point' => '', 'detail' => ''],
        };
        $list === 'tools' ? $this->edit['tools']['items'] = $rows : $this->edit[$list] = $rows;
    }

    public function removeRow(string $list, int $index): void
    {
        $this->authorizeEdit();
        if (! in_array($list, ['decision_makers', 'tools', 'pain_points'], true)) {
            return;
        }
        if ($list === 'tools') {
            unset($this->edit['tools']['items'][$index]);
            $this->edit['tools']['items'] = array_values($this->edit['tools']['items'] ?? []);
        } else {
            unset($this->edit[$list][$index]);
            $this->edit[$list] = array_values($this->edit[$list] ?? []);
        }
    }

    public function saveEdit(): void
    {
        $this->authorizeEdit();
        $report = $this->report($this->editing);
        $this->validate([
            'edit.title' => ['required', 'string', 'max:200'],
            'edit.tools.currency' => ['nullable', 'regex:/^[A-Za-z]{3}$/'],
            'edit.tools.items.*.cost_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
        ], [
            'edit.title.required' => 'Give the report a title.',
            'edit.tools.currency.regex' => 'Write the currency as three letters, for example NGN or XAF.',
            'edit.tools.items.*.cost_amount.numeric' => 'Write the cost as a number, for example 25000.',
            'edit.tools.items.*.cost_amount.min' => 'A cost cannot be below zero.',
        ]);

        $data = $this->edit;
        unset($data['title']);
        $data['open_questions'] = preg_split('/\R/u', (string) ($data['open_questions'] ?? '')) ?: [];
        LeadIntel::rename($report, $this->edit['title'], Auth::user());
        LeadIntel::update($report, $data, Auth::user());

        $this->cancelEdit();
        $this->notice = 'Your changes to the report were saved.';
    }

    // --- Pitch brief ---

    public function makeBrief(): void
    {
        $this->authorizeEdit();
        $run = LeadIntel::latestBrief($this->leadId);
        if ($run && ! $run->isFinished()) {
            return;
        }
        $problem = LeadIntel::startBrief($this->lead());
        if ($problem) {
            $this->warning = $problem;
        } else {
            $this->notice = 'Claude is writing the pitch brief. It shows below within a minute or two.';
        }
    }

    public function render()
    {
        $reports = LeadIntel::reportsFor($this->leadId)->map(fn (LeadIntelReport $r) => (object) [
            'report' => $r,
            'state' => LeadIntel::state($r),
            'totals' => $r->structured ? LeadIntel::totals($r->structured) : null,
        ]);
        $brief = LeadIntel::latestBrief($this->leadId);
        $busy = $reports->contains(fn ($x) => $x->state['state'] === 'reading') || ($brief && ! $brief->isFinished());

        return view('livewire.hq.leads.intel-tab', [
            'reports' => $reports,
            'brief' => $brief,
            'busy' => $busy,
            'dealRoles' => LeadIntel::DEAL_ROLES,
            'costBases' => LeadIntel::COST_BASES,
            'confidence' => LeadIntel::CONFIDENCE,
            'maxMb' => LeadIntel::MAX_KB / 1024,
        ]);
    }
}
