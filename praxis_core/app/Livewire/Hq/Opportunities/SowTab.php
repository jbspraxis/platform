<?php

namespace App\Livewire\Hq\Opportunities;

use App\Models\Opportunity;
use App\Models\StatementOfWork;
use App\Support\Ai\AiUnavailable;
use App\Support\Opportunities\OpportunityAccess;
use App\Support\Opportunities\Proposals;
use App\Support\Opportunities\SowPdf;
use App\Support\Opportunities\Sows;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use LogicException;

/**
 * The opportunity page's SOW tab (docs/OPPORTUNITIES.md section 4, row 5). Once the client approved a proposal, the AI
 * drafts the statement of work from it in the background; whoever manages the opportunity edits its sections and
 * milestones (saved as they go); the owner or the lead approves it by signing it for JBS Praxis (name and drawn
 * signature: the HQ countersignature); then it is sent with the PDF and the portal link, where an active contact of
 * the organisation signs it with the one-time code or asks for changes (the next version says what changed). Every
 * request checks canView, and every action its own permission (Sows::canEdit, canDraftWithAi, canApprove).
 */
class SowTab extends Component
{
    #[Locked]
    public string $opportunityId;

    #[Url(as: 'sow', except: '')]
    public string $versionId = '';

    public string $instructions = '';

    public bool $withTranscripts = false;

    public bool $redrafting = false;

    #[Locked]
    public string $loaded = '';

    public string $title = '';

    public array $sections = [];

    public array $milestones = [];

    public $validityDays = Sows::VALIDITY_DAYS;

    public string $changesSummary = '';

    // Approving: HQ's signature
    public string $signerName = '';

    public string $signature = '';

    public array $sendTo = [];

    #[Locked]
    public string $recipientsFor = '';

    public array $linkTo = [];

    #[Locked]
    public string $notice = '';

    public function mount(string $opportunityId): void
    {
        $opportunity = Opportunity::findOrFail($opportunityId);
        abort_unless(OpportunityAccess::canView(Auth::user(), $opportunity), 403);
        $this->opportunityId = $opportunity->id;
        $this->signerName = trim(Auth::user()->first_name . ' ' . Auth::user()->last_name);
    }

    public function hydrate(): void
    {
        abort_unless(OpportunityAccess::canView(Auth::user(), $this->opportunity()), 403);
    }

    private function opportunity(): Opportunity
    {
        return Opportunity::with(['organization', 'lead'])->findOrFail($this->opportunityId);
    }

    private function current(): ?StatementOfWork
    {
        $versions = Sows::versions($this->opportunity());

        return $versions->firstWhere('id', $this->versionId) ?? $versions->first();
    }

    private function authorizeEdit(): Opportunity
    {
        $opportunity = $this->opportunity();
        abort_unless(Sows::canEdit(Auth::user(), $opportunity), 403);
        $this->notice = '';
        $this->resetErrorBag();

        return $opportunity;
    }

    private function editable(): ?StatementOfWork
    {
        $sow = $this->current();
        if (! $sow || ! $sow->isEditable()) {
            $this->addError('sow', __('sow.errors.not_editable'));

            return null;
        }

        return $sow;
    }

    public function show(string $id): void
    {
        $this->versionId = $id;
        $this->reset('loaded', 'redrafting', 'sendTo', 'recipientsFor', 'signature');
        $this->notice = '';
        $this->resetErrorBag();
    }

    /** Version 1 from the approved proposal: blank, or drafted by the AI */
    public function start(bool $withAi): void
    {
        $opportunity = $this->authorizeEdit();
        abort_if($withAi && ! Sows::canDraftWithAi(Auth::user(), $opportunity), 403);
        $this->validateInstructions();
        $sow = Sows::start($opportunity, Auth::user());
        if (! $sow) {
            $this->addError('sow', __('sow.errors.no_proposal'));

            return;
        }
        $this->versionId = $sow->id;
        $this->loaded = '';
        if ($withAi) {
            $this->askAi($sow);
        }
        $this->dispatch('opportunity-stage-changed');
        $this->dispatch('sow-changed');
    }

    public function draft(): void
    {
        $opportunity = $this->authorizeEdit();
        abort_unless(Sows::canDraftWithAi(Auth::user(), $opportunity), 403);
        $this->validateInstructions();
        if ($sow = $this->editable()) {
            $this->askAi($sow);
            $this->redrafting = false;
        }
    }

    private function validateInstructions(): void
    {
        $this->instructions = trim($this->instructions);
        $this->validate(['instructions' => ['nullable', 'string', 'max:' . Sows::MAX_NOTE]],
            ['instructions.max' => __('sow.errors.note_long', ['max' => Sows::MAX_NOTE])]);
    }

    private function askAi(StatementOfWork $sow): void
    {
        try {
            Sows::draft($sow, $this->instructions, $this->withTranscripts, Auth::user());
            $this->instructions = '';
            $this->withTranscripts = false;
            $this->loaded = '';
        } catch (AiUnavailable $e) {
            $this->addError('draft', $e->getMessage());
        }
    }

    public function openRedraft(): void
    {
        $this->authorizeEdit();
        $this->redrafting = true;
    }

    public function cancelRedraft(): void
    {
        $this->reset('redrafting', 'instructions', 'withTranscripts');
        $this->resetErrorBag();
    }

    public function dismissFailure(): void
    {
        $this->authorizeEdit();
        if ($sow = $this->current()) {
            Sows::dismissFailure($sow);
        }
    }

    /** Saves the draft whenever a field is left (wire:model.blur) */
    public function updated(string $name): void
    {
        $field = explode('.', $name, 2)[0];
        if (in_array($field, ['title', 'sections', 'milestones', 'validityDays', 'changesSummary'], true) && $this->loaded !== '') {
            $this->persist();
        }
    }

    public function addSection(): void
    {
        $this->authorizeEdit();
        if (count($this->sections) < Sows::MAX_SECTIONS) {
            $this->sections[] = ['key' => 'section_' . (count($this->sections) + 1), 'title' => __('sow.editor.new_section', [], $this->opportunity()->language), 'body' => ''];
            $this->persist();
        }
    }

    public function removeSection(int $index): void
    {
        $this->authorizeEdit();
        unset($this->sections[$index]);
        $this->sections = array_values($this->sections);
        $this->persist();
    }

    public function moveSection(int $index, int $by): void
    {
        $this->authorizeEdit();
        $to = $index + ($by < 0 ? -1 : 1);
        if (isset($this->sections[$index], $this->sections[$to])) {
            [$this->sections[$index], $this->sections[$to]] = [$this->sections[$to], $this->sections[$index]];
            $this->persist();
        }
    }

    public function addMilestone(): void
    {
        $this->authorizeEdit();
        if (count($this->milestones) < Sows::MAX_MILESTONES) {
            $this->milestones[] = ['key' => 'm' . (count($this->milestones) + 1), 'title' => '', 'description' => '', 'weeks' => 2, 'weight' => 0];
            $this->persist();
        }
    }

    public function removeMilestone(int $index): void
    {
        $this->authorizeEdit();
        unset($this->milestones[$index]);
        $this->milestones = array_values($this->milestones);
        $this->persist();
    }

    public function moveMilestone(int $index, int $by): void
    {
        $this->authorizeEdit();
        $to = $index + ($by < 0 ? -1 : 1);
        if (isset($this->milestones[$index], $this->milestones[$to])) {
            [$this->milestones[$index], $this->milestones[$to]] = [$this->milestones[$to], $this->milestones[$index]];
            $this->persist();
        }
    }

    /** Approve: the owner or the lead signs it for JBS Praxis (the HQ countersignature) */
    public function approve(): void
    {
        $opportunity = $this->opportunity();
        abort_unless(Sows::canApprove(Auth::user(), $opportunity), 403);
        $this->notice = '';
        $this->resetErrorBag();
        $this->signerName = trim($this->signerName);
        $this->validate([
            'signerName' => ['required', 'string', 'max:150'],
            'signature' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:400000'],
        ], [
            'signerName.*' => __('sow.approve.errors.name'),
            'signature.*' => __('sow.approve.errors.signature'),
        ]);
        if (! ($sow = $this->current())) {
            return;
        }
        if ($sow->isEditable() && $this->loaded !== '' && Sows::canEdit(Auth::user(), $opportunity)) {
            $this->persist();
        }
        $why = Sows::approve($sow, Auth::user(), $this->signerName, $this->signature, request()->ip());
        if ($why === null) {
            $this->signature = '';
            $this->notice = __('sow.approve.done');
        } else {
            $this->addError('approve', __('sow.approve.errors.' . $why));
        }
    }

    public function reopen(): void
    {
        abort_unless(Sows::canApprove(Auth::user(), $this->opportunity()), 403);
        $this->resetErrorBag();
        if (($sow = $this->current()) && Sows::reopen($sow, Auth::user())) {
            $this->loaded = '';
            $this->recipientsFor = '';
            $this->notice = __('sow.approve.reopened');
        }
    }

    public function send(): void
    {
        $opportunity = $this->authorizeEdit();
        $sow = $this->current();
        $this->validate([
            'sendTo' => ['required', 'array', 'min:1', 'max:' . Sows::MAX_RECIPIENTS],
            'sendTo.*' => [Rule::in(array_keys(Proposals::people($opportunity)))],
        ], [
            'sendTo.required' => __('proposals.send.errors.nobody'),
            'sendTo.min' => __('proposals.send.errors.nobody'),
            'sendTo.max' => __('proposals.send.errors.too_many', ['max' => Sows::MAX_RECIPIENTS]),
            'sendTo.*' => __('proposals.send.errors.unknown'),
        ]);
        if (! $sow) {
            return;
        }
        try {
            $unsent = Sows::send($sow, $this->sendTo, Auth::user());
        } catch (LogicException) {
            $this->addError('send', __('sow.send.errors.state'));

            return;
        }
        if ($sow->fresh()->status !== StatementOfWork::SENT) {
            $this->addError('send', __('proposals.send.errors.failed'));

            return;
        }
        $this->sendTo = [];
        $this->notice = $unsent ? __('proposals.send.partly', ['emails' => implode(', ', $unsent)]) : __('sow.send.done');
        $this->dispatch('opportunity-stage-changed');
        $this->dispatch('sow-changed');
    }

    public function resend(): void
    {
        $this->authorizeEdit();
        if ($sow = $this->current()) {
            $unsent = Sows::resend($sow, Auth::user());
            $unsent ? $this->addError('send', __('proposals.send.still_unsent', ['emails' => implode(', ', $unsent)])) : $this->notice = __('sow.send.done');
        }
    }

    public function sendLinkAgain(): void
    {
        $opportunity = $this->authorizeEdit();
        $this->validate([
            'linkTo' => ['required', 'array', 'min:1', 'max:' . Sows::MAX_RECIPIENTS],
            'linkTo.*' => [Rule::in(array_keys(Proposals::people($opportunity)))],
        ], [
            'linkTo.required' => __('proposals.send.errors.nobody'),
            'linkTo.min' => __('proposals.send.errors.nobody'),
            'linkTo.max' => __('proposals.send.errors.too_many', ['max' => Sows::MAX_RECIPIENTS]),
            'linkTo.*' => __('proposals.send.errors.unknown'),
        ]);
        if (! ($sow = $this->current())) {
            return;
        }
        try {
            $unsent = Sows::sendAgain($sow, $this->linkTo, Auth::user());
        } catch (LogicException) {
            $this->addError('linkTo', __('sow.send.errors.link_state'));

            return;
        }
        $unsent ? $this->addError('send', __('proposals.send.still_unsent', ['emails' => implode(', ', $unsent)])) : $this->notice = __('sow.send.link_done');
    }

    public function newVersion(): void
    {
        $opportunity = $this->authorizeEdit();
        if ($next = Sows::newVersion($opportunity, Auth::user())) {
            $this->show($next->id);
            $this->notice = __('sow.versions.started', ['n' => $next->version]);
            $this->dispatch('sow-changed');
        }
    }

    public function discard(): void
    {
        $opportunity = $this->authorizeEdit();
        if (! ($sow = $this->current())) {
            return;
        }
        if (! Sows::discard($sow, Auth::user(), Sows::canApprove(Auth::user(), $opportunity))) {
            $this->addError('sow', __('sow.versions.not_discarded'));

            return;
        }
        $this->show('');
        $this->notice = __('sow.versions.discarded', ['n' => $sow->version]);
        $this->dispatch('sow-changed');
    }

    /** wire:poll while the AI works: render() applies what it finished */
    public function check(): void {}

    private function persist(): void
    {
        abort_unless(Sows::canEdit(Auth::user(), $this->opportunity()), 403);
        $sow = $this->current();
        if (! $sow || $sow->id !== $this->loaded) {
            return;
        }
        $saved = Sows::save($sow, [
            'title' => $this->title,
            'sections' => $this->sections,
            'milestones' => $this->milestones,
            'validity_days' => is_numeric($this->validityDays) ? $this->validityDays : $sow->validity_days,
            'changes_summary' => $this->changesSummary,
        ], Auth::user());
        if (! $saved) {
            $this->loaded = '';
            $this->addError('sow', __('sow.errors.not_saved'));
        }
    }

    private function load(StatementOfWork $sow): void
    {
        $this->loaded = $sow->id;
        $this->title = (string) $sow->title;
        $this->sections = array_values((array) $sow->sections);
        $this->milestones = array_values((array) $sow->milestones);
        $this->validityDays = (int) $sow->validity_days;
        $this->changesSummary = (string) $sow->changes_summary;
    }

    public function render()
    {
        $opportunity = $this->opportunity();
        $user = Auth::user();
        $versions = Sows::versions($opportunity);
        $sow = $versions->firstWhere('id', $this->versionId) ?? $versions->first();

        if ($sow) {
            if (Sows::sync($sow)) {
                $this->loaded = '';
                $sow = Sows::versions($opportunity)->firstWhere('id', $sow->id);
            }
            $sow->load(['aiRun', 'approver', 'previousVersion', 'proposal']);
            if ($sow->aiRun && ! $sow->aiRun->isFinished()) {
                $this->loaded = '';
            } elseif ($sow->isEditable() && $this->loaded !== $sow->id) {
                $this->load($sow);
            }
            if ($sow->status === StatementOfWork::DRAFT && $sow->approved_at && $this->recipientsFor !== $sow->id) {
                $previous = $sow->previousVersion;
                $people = Proposals::people($opportunity);
                $emails = $previous ? array_map(fn ($r) => mb_strtolower((string) $r['email']), (array) $previous->recipients)
                    : array_values(array_filter([mb_strtolower((string) $sow->proposal?->client_approved_email)]));
                $this->sendTo = array_values(array_intersect($emails ?: array_slice(array_keys($people), 0, 1), array_keys($people)));
                $this->recipientsFor = $sow->id;
            } elseif ($sow->status === StatementOfWork::SENT && $this->recipientsFor !== $sow->id . ':sent') {
                $this->linkTo = array_values(array_intersect(array_map(fn ($r) => mb_strtolower((string) $r['email']), (array) $sow->recipients), array_keys(Proposals::people($opportunity))));
                $this->recipientsFor = $sow->id . ':sent';
            }
        }

        $drafting = $sow?->aiRun && ! $sow->aiRun->isFinished();
        $latest = $versions->first();
        $canEdit = Sows::canEdit($user, $opportunity);
        $proposal = Sows::approvedProposal($opportunity) ?? $sow?->proposal;
        $total = $sow ? Sows::contractTotal($sow) : (float) ($proposal?->total_value ?? 0);
        $editing = $canEdit && $sow?->isEditable() && ! $drafting;
        $milestones = $editing ? $this->milestones : (array) $sow?->milestones;

        return view('livewire.hq.opportunities.sow-tab', [
            'o' => $opportunity,
            'versions' => $versions,
            'sow' => $sow,
            'latest' => $latest,
            'proposal' => $proposal,
            'canEdit' => $canEdit,
            'canDraft' => Sows::canDraftWithAi($user, $opportunity),
            'canApprove' => Sows::canApprove($user, $opportunity),
            'editing' => $editing,
            'drafting' => $drafting,
            'draftError' => $sow?->aiRun?->status === 'failed' ? $sow->aiRun->errorMessage() : null,
            'polling' => $drafting,
            'legalName' => Sows::legalName(),
            'partyName' => $sow ? Sows::partyName($sow) : (Sows::legalName() ?? Sows::LEGAL_NAME_PLACEHOLDER),
            // An imported SOW (opportunities:import, signed on paper) has no approved proposal: no fees or PDF from HQ
            'imported' => $sow !== null && Sows::isImported($sow),
            'fees' => $sow && ! Sows::isImported($sow) ? SowPdf::fees($sow) : null,
            'total' => $total,
            'currency' => (string) ($proposal?->currency ?? ''),
            'milestoneList' => $milestones,
            'amounts' => Sows::amounts(array_values($milestones), $total),
            'weightTotal' => Sows::weightTotal($milestones),
            'people' => $canEdit ? Proposals::people($opportunity) : [],
            'signedVersion' => $versions->firstWhere('status', StatementOfWork::SIGNED),
            'canStartVersion' => $canEdit && $latest && in_array($latest->status, [StatementOfWork::SENT, StatementOfWork::CHANGES_REQUESTED], true)
                && ! $versions->contains(fn ($v) => in_array($v->status, [StatementOfWork::DRAFT, StatementOfWork::SIGNED], true)),
            'depositInvoice' => $sow?->deposit_invoice_id ? \Illuminate\Support\Facades\DB::table('invoices')->where('id', $sow->deposit_invoice_id)->first(['invoice_number', 'total_amount', 'currency', 'status']) : null,
        ]);
    }
}
