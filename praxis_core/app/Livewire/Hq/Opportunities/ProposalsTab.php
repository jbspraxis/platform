<?php

namespace App\Livewire\Hq\Opportunities;

use App\Models\AiRun;
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\ProposalComment;
use App\Support\Ai\AiUnavailable;
use App\Support\Opportunities\OpportunityAccess;
use App\Support\Opportunities\Proposals;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use LogicException;

/**
 * The opportunity page's Proposals tab (docs/OPPORTUNITIES.md section 4, row 4). The AI drafts a version in the
 * background from the opportunity; whoever manages it (the permission, or the opportunity's team) edits it (saved
 * as they go: each field when they leave it, each line or section added or removed); the owner or the lead approves
 * it; then it is sent to the client's people with the PDF and the portal link. Client comments show under their
 * sections; changes lead to the next version, which says what changed. Legacy proposals are listed below with a link
 * to the old builder. Every request checks canView for this opportunity, and every action its own permission on the
 * server (Proposals::canEdit, canDraftWithAi, canApprove), always with the opportunity.
 */
class ProposalsTab extends Component
{
    #[Locked]
    public string $opportunityId;

    #[Url(as: 'version', except: '')]
    public string $versionId = '';

    // Starting, and the AI's instructions
    public string $currency = 'USD';

    public string $instructions = '';

    public bool $redrafting = false;

    // The draft being edited (loaded from the version; saved as they go)
    #[Locked]
    public string $loaded = '';

    public string $title = '';

    public array $sections = [];

    public array $lines = [];

    public $vatRate = 0;

    public string $schedule = '';

    public $validityDays = Proposals::VALIDITY_DAYS;

    public string $changesSummary = '';

    /** The "what changed" job the editor is waiting for, so its text replaces the field when it is filed */
    #[Locked]
    public string $changesPending = '';

    public string $addCode = '';

    // Sending: who it goes to, filled in once per approved version (then it is the person's choice)
    public array $sendTo = [];

    #[Locked]
    public string $recipientsFor = '';

    /** "Send the link again": who to, filled in with the version's recipients */
    public array $linkTo = [];

    /** What was just done, announced politely */
    #[Locked]
    public string $notice = '';

    public function mount(string $opportunityId): void
    {
        $opportunity = Opportunity::findOrFail($opportunityId);
        abort_unless(OpportunityAccess::canView(Auth::user(), $opportunity), 403);
        $this->opportunityId = $opportunity->id;
    }

    /** Every later request (each action, and the poll while the AI works): still allowed to open this opportunity */
    public function hydrate(): void
    {
        abort_unless(OpportunityAccess::canView(Auth::user(), $this->opportunity()), 403);
    }

    private function opportunity(): Opportunity
    {
        return Opportunity::with(['organization', 'lead'])->findOrFail($this->opportunityId);
    }

    /** The version on screen: the one chosen, else the latest */
    private function current(): ?Proposal
    {
        $versions = Proposals::versions($this->opportunity());

        return $versions->firstWhere('id', $this->versionId) ?? $versions->first();
    }

    private function authorizeEdit(): Opportunity
    {
        $opportunity = $this->opportunity();
        abort_unless(Proposals::canEdit(Auth::user(), $opportunity), 403);
        $this->notice = '';
        $this->resetErrorBag();

        return $opportunity;
    }

    /** The version on screen, which must be a draft nobody approved */
    private function editable(): ?Proposal
    {
        $proposal = $this->current();
        if (! $proposal || ! $proposal->isEditable()) {
            $this->addError('proposal', __('proposals.errors.not_editable'));

            return null;
        }

        return $proposal;
    }

    public function show(string $id): void
    {
        $this->versionId = $id;
        $this->reset('loaded', 'redrafting', 'sendTo', 'recipientsFor', 'changesPending');
        $this->notice = '';
        $this->resetErrorBag();
    }

    /** Version 1: blank, or drafted by the AI */
    public function start(bool $withAi): void
    {
        $opportunity = $this->authorizeEdit();
        abort_if($withAi && ! Proposals::canDraftWithAi(Auth::user(), $opportunity), 403);
        $this->instructions = trim($this->instructions);
        $this->validate([
            'currency' => ['required', Rule::in(Proposals::currencies()->all())],
            'instructions' => ['nullable', 'string', 'max:' . Proposals::MAX_NOTE],
        ], [
            'currency.*' => __('proposals.errors.currency'),
            'instructions.max' => __('proposals.errors.note_long', ['max' => Proposals::MAX_NOTE]),
        ]);

        $proposal = Proposals::start($opportunity, $this->currency, Auth::user());
        $this->versionId = $proposal->id;
        $this->loaded = '';
        if ($withAi) {
            $this->askAi($proposal);
        }
        $this->dispatch('proposals-changed');
    }

    /** Draft the version on screen again with the AI (it replaces the text and line items) */
    public function draft(): void
    {
        $opportunity = $this->authorizeEdit();
        abort_unless(Proposals::canDraftWithAi(Auth::user(), $opportunity), 403);
        $this->instructions = trim($this->instructions);
        $this->validate(['instructions' => ['nullable', 'string', 'max:' . Proposals::MAX_NOTE]],
            ['instructions.max' => __('proposals.errors.note_long', ['max' => Proposals::MAX_NOTE])]);
        if ($proposal = $this->editable()) {
            $this->askAi($proposal);
            $this->redrafting = false;
        }
    }

    private function askAi(Proposal $proposal): void
    {
        $stage = $this->opportunity()->stage;
        try {
            Proposals::draft($proposal, $this->instructions, Auth::user());
            $this->instructions = '';
            $this->loaded = '';
        } catch (AiUnavailable $e) {
            $this->addError('draft', $e->getMessage());
        }
        if ($this->opportunity()->stage !== $stage) {
            $this->dispatch('opportunity-stage-changed');
        }
    }

    public function openRedraft(): void
    {
        $this->authorizeEdit();
        $this->redrafting = true;
    }

    public function cancelRedraft(): void
    {
        $this->reset('redrafting', 'instructions');
        $this->resetErrorBag();
    }

    public function dismissFailure(string $column): void
    {
        $this->authorizeEdit();
        if ($proposal = $this->current()) {
            Proposals::dismissFailure($proposal, $column);
        }
    }

    /** Saves the draft whenever a field is left (wire:model.blur) or a select changes */
    public function updated(string $name): void
    {
        $field = explode('.', $name, 2)[0];
        if (in_array($field, ['title', 'sections', 'lines', 'vatRate', 'schedule', 'validityDays', 'changesSummary', 'currency'], true) && $this->loaded !== '') {
            if ($field === 'currency') {
                $this->repriceLines();
            }
            $this->persist();
        }
    }

    public function addSection(): void
    {
        $this->authorizeEdit();
        if (count($this->sections) < Proposals::MAX_SECTIONS) {
            $this->sections[] = ['key' => 'section_' . (count($this->sections) + 1), 'title' => __('proposals.editor.new_section', [], $this->opportunity()->language), 'body' => ''];
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

    /** A line from the financial dictionary, priced in the proposal's currency */
    public function addDictionaryLine(): void
    {
        $this->authorizeEdit();
        $item = Proposals::dictionary()->get($this->addCode);
        if (! $item) {
            $this->addError('addCode', __('proposals.errors.item'));

            return;
        }
        if (count($this->lines) < Proposals::MAX_LINES) {
            $this->lines[] = Proposals::lineFor($item, $this->currency);
            $this->addCode = '';
            $this->persist();
        }
    }

    public function addCustomLine(): void
    {
        $this->authorizeEdit();
        if (count($this->lines) < Proposals::MAX_LINES) {
            $this->lines[] = ['code' => 'CUSTOM', 'description' => __('proposals.lines.custom', [], $this->opportunity()->language), 'quantity' => '1', 'unit_price' => 0];
            $this->persist();
        }
    }

    public function removeLine(int $index): void
    {
        $this->authorizeEdit();
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        $this->persist();
    }

    /** "What changed" since the version the client has, drafted by the AI (Haiku) */
    public function summariseChanges(): void
    {
        $this->authorizeEdit();
        if (! ($proposal = $this->editable())) {
            return;
        }
        $this->persist();
        try {
            Proposals::summariseChanges($proposal, Auth::user());
            $this->loaded = '';
        } catch (AiUnavailable $e) {
            $this->addError('changes', $e->getMessage());
        }
    }

    public function approve(): void
    {
        $opportunity = $this->opportunity();
        abort_unless(Proposals::canApprove(Auth::user(), $opportunity), 403);
        $this->notice = '';
        $this->resetErrorBag();
        if (! ($proposal = $this->current())) {
            return;
        }
        if ($proposal->isEditable() && $this->loaded !== '' && Proposals::canEdit(Auth::user(), $opportunity)) {
            $this->persist();
        }
        $why = Proposals::approve($proposal, Auth::user());
        if ($why === null) {
            $this->notice = __('proposals.approve.done');
        } else {
            $this->addError('approve', __('proposals.approve.errors.' . $why));
        }
    }

    public function reopen(): void
    {
        abort_unless(Proposals::canApprove(Auth::user(), $this->opportunity()), 403);
        $this->resetErrorBag();
        if (($proposal = $this->current()) && Proposals::reopen($proposal, Auth::user())) {
            $this->loaded = '';
            $this->recipientsFor = '';
            $this->notice = __('proposals.approve.reopened');
        }
    }

    public function send(): void
    {
        $opportunity = $this->authorizeEdit();
        $proposal = $this->current();
        $people = array_keys(Proposals::people($opportunity));
        $this->validate([
            'sendTo' => ['required', 'array', 'min:1', 'max:' . Proposals::MAX_RECIPIENTS],
            'sendTo.*' => [Rule::in($people)],
        ], [
            'sendTo.required' => __('proposals.send.errors.nobody'),
            'sendTo.min' => __('proposals.send.errors.nobody'),
            'sendTo.max' => __('proposals.send.errors.too_many', ['max' => Proposals::MAX_RECIPIENTS]),
            'sendTo.*' => __('proposals.send.errors.unknown'),
        ]);
        if (! $proposal) {
            return;
        }

        $stage = $opportunity->stage;
        try {
            $unsent = Proposals::send($proposal, $this->sendTo, Auth::user());
        } catch (LogicException) {
            $this->addError('send', __('proposals.send.errors.state'));

            return;
        }
        $proposal->refresh();
        if ($proposal->status !== Proposal::SENT) {
            $this->addError('send', __('proposals.send.errors.failed'));

            return;
        }
        $this->sendTo = [];
        $this->notice = $unsent ? __('proposals.send.partly', ['emails' => implode(', ', $unsent)]) : __('proposals.send.done');
        if ($this->opportunity()->stage !== $stage) {
            $this->dispatch('opportunity-stage-changed');
        }
        $this->dispatch('proposals-changed');
    }

    public function resend(): void
    {
        $this->authorizeEdit();
        if ($proposal = $this->current()) {
            $unsent = Proposals::resend($proposal, Auth::user());
            $unsent ? $this->addError('send', __('proposals.send.still_unsent', ['emails' => implode(', ', $unsent)])) : $this->notice = __('proposals.send.done');
        }
    }

    public function newVersion(): void
    {
        $opportunity = $this->authorizeEdit();
        $next = Proposals::newVersion($opportunity, Auth::user());
        if ($next) {
            $this->show($next->id);
            $this->notice = __('proposals.versions.started', ['n' => $next->version]);
            $this->dispatch('proposals-changed');
        }
    }

    public function discard(): void
    {
        $opportunity = $this->authorizeEdit();
        $proposal = $this->current();
        if (! $proposal) {
            return;
        }
        // An approved draft only by someone who may approve: the owner or the lead
        if (! Proposals::discard($proposal, Auth::user(), Proposals::canApprove(Auth::user(), $opportunity))) {
            $this->addError('proposal', __('proposals.versions.not_discarded'));

            return;
        }
        $this->show('');
        $this->notice = __('proposals.versions.discarded', ['n' => $proposal->version]);
        $this->dispatch('proposals-changed');
    }

    /** The email with the link and the PDF again, to the same people or others chosen from people() */
    public function sendLinkAgain(): void
    {
        $opportunity = $this->authorizeEdit();
        $this->validate([
            'linkTo' => ['required', 'array', 'min:1', 'max:' . Proposals::MAX_RECIPIENTS],
            'linkTo.*' => [Rule::in(array_keys(Proposals::people($opportunity)))],
        ], [
            'linkTo.required' => __('proposals.send.errors.nobody'),
            'linkTo.min' => __('proposals.send.errors.nobody'),
            'linkTo.max' => __('proposals.send.errors.too_many', ['max' => Proposals::MAX_RECIPIENTS]),
            'linkTo.*' => __('proposals.send.errors.unknown'),
        ]);
        if (! ($proposal = $this->current())) {
            return;
        }
        try {
            $unsent = Proposals::sendAgain($proposal, $this->linkTo, Auth::user());
        } catch (LogicException) {
            $this->addError('linkTo', __('proposals.send.errors.link_state'));

            return;
        }
        $unsent ? $this->addError('send', __('proposals.send.still_unsent', ['emails' => implode(', ', $unsent)])) : $this->notice = __('proposals.send.link_done');
    }

    public function resolve(string $commentId, bool $resolved = true): void
    {
        $this->authorizeEdit();
        $comment = ProposalComment::whereIn('proposal_id', Proposals::versions($this->opportunity())->pluck('id'))->findOrFail($commentId);
        Proposals::resolve($comment, $resolved, Auth::user());
    }

    /** wire:poll while the AI works: render() applies what it finished */
    public function check(): void {}

    /** Writes the fields to the draft; a version that can no longer change is reloaded instead */
    private function persist(): void
    {
        $opportunity = $this->opportunity();
        abort_unless(Proposals::canEdit(Auth::user(), $opportunity), 403);
        $proposal = $this->current();
        if (! $proposal || $proposal->id !== $this->loaded) {
            return;
        }
        $saved = Proposals::save($proposal, [
            'title' => $this->title,
            'sections' => $this->sections,
            'lines' => $this->lines,
            'currency' => $this->currency,
            'vat_rate' => is_numeric($this->vatRate) ? $this->vatRate : $proposal->vat_rate,
            'payment_schedule_type' => $this->schedule,
            'validity_days' => is_numeric($this->validityDays) ? $this->validityDays : $proposal->validity_days,
            'changes_summary' => $this->changesSummary,
        ], Auth::user());
        if (! $saved) {
            $this->loaded = '';
            $this->addError('proposal', __('proposals.errors.not_saved'));
        }
    }

    /** The currency changed: dictionary lines take the dictionary's price in it; the lead's own lines keep theirs */
    private function repriceLines(): void
    {
        $dictionary = Proposals::dictionary();
        foreach ($this->lines as $i => $line) {
            $item = $dictionary->get($line['code'] ?? '');
            if ($item && Proposals::currencies()->contains($this->currency)) {
                $this->lines[$i]['unit_price'] = Proposals::lineFor($item, $this->currency)['unit_price'];
            }
        }
    }

    /** Fills the editor from the version once (and again after the AI or another person changed it) */
    private function load(Proposal $proposal): void
    {
        $this->loaded = $proposal->id;
        $this->title = (string) $proposal->title;
        $this->sections = array_values((array) $proposal->sections);
        $this->lines = Proposals::lines($proposal)->map(fn ($l) => [
            'code' => $l->code, 'description' => $l->description, 'quantity' => $l->quantity, 'unit_price' => (float) $l->unit_price,
        ])->all();
        $this->currency = (string) $proposal->currency;
        $this->vatRate = (float) $proposal->vat_rate;
        $this->schedule = (string) $proposal->payment_schedule_type;
        $this->validityDays = (int) $proposal->validity_days;
        $this->changesSummary = (string) $proposal->changes_summary;
    }

    /** Who to send to first: the primary contact, or everyone the last version went to */
    private function defaultRecipients(Opportunity $opportunity, Proposal $proposal): array
    {
        $previous = $proposal->previousVersion;
        $people = Proposals::people($opportunity);
        $emails = $previous ? array_map(fn ($r) => mb_strtolower((string) $r['email']), (array) $previous->recipients) : array_slice(array_keys($people), 0, 1);

        return array_values(array_intersect($emails, array_keys($people)));
    }

    public function render()
    {
        $opportunity = $this->opportunity();
        $user = Auth::user();
        $versions = Proposals::versions($opportunity);
        $proposal = $versions->firstWhere('id', $this->versionId) ?? $versions->first();

        if ($proposal) {
            if (Proposals::sync($proposal)) {
                $this->loaded = ''; // rewritten by the AI: show what is saved now
                $versions = Proposals::versions($opportunity);
                $proposal = $versions->firstWhere('id', $proposal->id);
            }
            $proposal->load(['aiRun', 'changesRun', 'approver', 'previousVersion', 'comments']);
            if ($proposal->aiRun && ! $proposal->aiRun->isFinished()) {
                // The AI is rewriting it (the worker may file it, not this page): load what is saved once it is done
                $this->loaded = '';
            } elseif ($proposal->isEditable() && $this->loaded !== $proposal->id) {
                $this->load($proposal);
            }
            // "What changed" filed by the worker while the editor was open: that one field takes it
            $summarising = $proposal->changesRun && ! $proposal->changesRun->isFinished();
            if ($this->changesPending !== '' && ! $summarising) {
                $this->changesSummary = (string) $proposal->changes_summary;
            }
            $this->changesPending = $summarising ? (string) $proposal->changes_run_id : '';
            if ($proposal->status === Proposal::DRAFT && $proposal->approved_at && $this->recipientsFor !== $proposal->id) {
                $this->sendTo = $this->defaultRecipients($opportunity, $proposal);
                $this->recipientsFor = $proposal->id;
            } elseif ($proposal->status === Proposal::SENT && $this->recipientsFor !== $proposal->id . ':sent') {
                $this->linkTo = array_values(array_intersect(array_map(fn ($r) => mb_strtolower((string) $r['email']), (array) $proposal->recipients),
                    array_keys(Proposals::people($opportunity))));
                $this->recipientsFor = $proposal->id . ':sent';
            }
        }

        $drafting = $proposal?->aiRun && ! $proposal->aiRun->isFinished();
        $summarising = $proposal?->changesRun && ! $proposal->changesRun->isFinished();
        $latest = $versions->first();
        $canEdit = Proposals::canEdit($user, $opportunity);
        // The AI's reasons for the line items it suggested, from its last draft of this version
        $reasons = $proposal ? collect((array) (AiRun::where('subject_type', 'proposal')->where('subject_id', $proposal->id)->where('purpose', 'proposal')
            ->where('status', 'done')->latest('finished_at')->value('output')['suggested_line_items'] ?? []))->pluck('reason', 'code')->all() : [];
        $previous = $proposal?->previousVersion;

        return view('livewire.hq.opportunities.proposals-tab', [
            'o' => $opportunity,
            'versions' => $versions,
            'proposal' => $proposal,
            'latest' => $latest,
            'legacy' => Proposals::legacy($opportunity),
            'canEdit' => $canEdit,
            'canDraft' => Proposals::canDraftWithAi($user, $opportunity),
            'canApprove' => Proposals::canApprove($user, $opportunity),
            'canOpenLegacy' => (bool) $user?->can('crm.view'),
            'editing' => $canEdit && $proposal?->isEditable() && ! $drafting,
            'drafting' => $drafting,
            'summarising' => $summarising,
            'draftError' => $proposal?->aiRun?->status === 'failed' ? $proposal->aiRun->errorMessage() : null,
            'changesError' => $proposal?->changesRun?->status === 'failed' ? $proposal->changesRun->errorMessage() : null,
            'polling' => $drafting || $summarising,
            'comments' => $proposal ? $proposal->comments->where('author_type', 'client')->groupBy('section_key') : collect(),
            // While a later version is written, the client's comments on the one they have show under its sections
            'previousComments' => $proposal?->isEditable() && $previous ? $previous->comments()->where('author_type', 'client')->get()->groupBy('section_key') : collect(),
            'readLines' => $proposal ? Proposals::lines($proposal) : collect(),
            'totals' => $proposal ? Proposals::totals($proposal->isEditable() ? $this->lines : Proposals::lines($proposal), (float) ($proposal->isEditable() && is_numeric($this->vatRate) ? $this->vatRate : $proposal->vat_rate)) : null,
            'dictionary' => $canEdit && $proposal?->isEditable() ? Proposals::dictionary()->groupBy('category') : collect(),
            'currencies' => Proposals::currencies(),
            'people' => $canEdit ? Proposals::people($opportunity) : [],
            'reasons' => $reasons,
            'questionnaireComplete' => $opportunity->questionnaires()->where('status', 'complete')->exists(),
            'canStartVersion' => $canEdit && $latest && in_array($latest->status, [Proposal::SENT, Proposal::CHANGES_REQUESTED], true)
                && ! $versions->contains(fn ($v) => in_array($v->status, [Proposal::DRAFT, Proposal::CLIENT_APPROVED], true)),
            'approvedVersion' => $versions->firstWhere('status', Proposal::CLIENT_APPROVED),
        ]);
    }
}
