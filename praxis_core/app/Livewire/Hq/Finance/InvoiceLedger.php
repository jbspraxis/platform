<?php

namespace App\Livewire\Hq\Finance;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Support\Access;
use App\Support\Finance\ReceivingAccounts;
use App\Support\Finance\Vat;
use Illuminate\Validation\Rule;

#[Layout('components.layouts.hq')]
class InvoiceLedger extends Component
{
    use WithPagination;

    /** Statuses an invoice can be cancelled from: unpaid ones only */
    private const CANCELLABLE = ['Pending', 'Overdue'];

    /** Invoices shown per page (Paid and Cancelled only grow) */
    private const PER_PAGE = 50;

    // The invoices, the projects to invoice and the VAT are read when the page renders (never kept in the page)
    public $activeTab = 'Pending'; // Pending, Overdue, Paid, Cancelled

    // Create Invoice State
    public $showCreateModal = false;
    public $projectPhases = [];
    
    // Form Inputs
    public $new_project_id = '';
    public $new_invoice_type = 'Tax Invoice - Phase Milestone';
    public $new_currency = 'USD';
    public $new_payment_terms = 'Net 10';
    public $new_remittance_id = '';

    /**
     * One invoice, several milestones (owner, 10 Nov 2026): the milestones ticked (ids), and the invoice's lines, each
     * ['kind' => 'charge'|'discount', 'phase_id' => milestone id or null for a free line, 'description', 'amount']. A
     * ticked milestone adds its line, filled with its agreed amount less its deposit share; the amount and wording stay
     * editable. "Add line" adds a free line; "Add discount" a discount (typed positive, stored negative, 13 Nov 2026).
     * Every line becomes its own invoice line, with its milestone. Advances on a ticked milestone are taken off by
     * themselves (App\Support\Finance\Advances): those lines are shown, never typed.
     */
    public array $picked = [];
    public array $lines = [];

    /**
     * What Create invoice makes (13 Nov 2026): 'invoice' (milestones and lines, as above) or 'advance' (part of one
     * milestone billed ahead of its acceptance, taken off that milestone's own invoice later).
     */
    public string $new_kind = 'invoice';
    public string $advance_phase_id = '';
    public $advance_amount = '';
    public string $advance_description = '';

    /** Record payment (finance.approve): the invoice it is for, and what arrived */
    public ?string $pay_invoice_id = null;
    public $pay_amount = '';
    public string $pay_received_on = '';
    public string $pay_method = 'Bank Transfer';
    public string $pay_reference = '';
    public $pay_wht = '';
    public string $pay_note = '';

    /** At most this many lines on one invoice */
    private const MAX_LINES = 30;

    /** Each project's billing company (Vat::projectEntity), looked up once per request */
    private array $entities = [];

    public function mount()
    {
        $this->prefillFromProject();
    }

    /**
     * ?create=1&project=<id>&phase=<id>: a milestone dragged to Invoiced on its project page (docs/PROJECTS.md)
     * opens Create invoice with the project, the milestone and its share of the contract filled in.
     */
    private function prefillFromProject(): void
    {
        if (! request()->boolean('create') || ! Auth::user()->can('finance.create')) {
            return;
        }
        $project = $this->activeProject((string) request()->query('project'));
        if ($project) {
            $this->new_project_id = $project->id;
            $this->updatedNewProjectId($project->id);
            $phase = collect($this->projectPhases)->firstWhere('id', (string) request()->query('phase'));
            if ($phase) {
                $this->picked = [(string) $phase->id];
                $this->syncLines();
            }
        }
        $this->showCreateModal = true;
    }

    /**
     * A milestone's line: its agreed amount, to the unit (project_phases.amount; weight × total for an older row), less
     * the SOW deposit's share when the project has one ("less deposit, every milestone": Deposit)
     */
    private function milestoneLine(object $phase): array
    {
        $due = \App\Support\Projects\Deposit::forMilestone($phase);

        return [
            'kind' => 'charge',
            'phase_id' => (string) $phase->id,
            'description' => Str::limit($due['deposit']
                ? __('projects.invoice_line_less', ['milestone' => $phase->title, 'percent' => \App\Support\Projects\Deposit::percentLabel($due['deposit']['percent'], app()->getLocale())])
                : __('projects.invoice_line', ['milestone' => $phase->title]), 250),
            'amount' => $due['due'],
        ];
    }

    /** Ticking or unticking milestones: their lines follow, in milestone order, before the free lines; edits are kept */
    public function updatedPicked(): void
    {
        $this->syncLines();
    }

    private function syncLines(): void
    {
        $offered = collect($this->projectPhases)->keyBy(fn ($p) => (string) $p->id);
        $picked = array_values(array_unique(array_filter(array_map('strval', $this->picked), fn ($id) => $offered->has($id))));
        $this->picked = $picked;

        $lines = collect($this->lines)->filter(fn ($l) => empty($l['phase_id']) || in_array((string) $l['phase_id'], $picked, true));
        $have = $lines->pluck('phase_id')->filter()->map(fn ($id) => (string) $id)->all();
        foreach ($picked as $id) {
            if (! in_array($id, $have, true) && $lines->count() < self::MAX_LINES) {
                $lines->push($this->milestoneLine($offered->get($id)));
            }
        }
        $rank = $offered->keys()->flip();
        $milestones = $lines->filter(fn ($l) => ! empty($l['phase_id']))->sortBy(fn ($l) => $rank[(string) $l['phase_id']] ?? PHP_INT_MAX)->values();
        $free = $lines->filter(fn ($l) => empty($l['phase_id']))->values();
        $this->lines = $milestones->concat($free)->values()->all();
    }

    /** A free line: any description and amount (a change order, an expense, part of a milestone) */
    public function addLine(): void
    {
        if (count($this->lines) < self::MAX_LINES) {
            $this->lines[] = ['kind' => 'charge', 'phase_id' => null, 'description' => '', 'amount' => ''];
        }
    }

    /** A discount: typed as a positive amount, taken off the total (e.g. "Long-term client discount") */
    public function addDiscount(): void
    {
        if (count($this->lines) < self::MAX_LINES) {
            $this->lines[] = ['kind' => 'discount', 'phase_id' => null, 'description' => '', 'amount' => ''];
        }
    }

    /** Choosing the milestone to advance on suggests the wording; the amount is the finance person's */
    public function updatedAdvancePhaseId($phaseId): void
    {
        $phase = collect($this->projectPhases)->firstWhere('id', (string) $phaseId);
        $this->advance_description = $phase ? Str::limit(__('invoices.lines.advance_on', [
            'milestone' => \App\Support\Projects\ProjectPlan::code($phase) . ' · ' . \App\Support\Projects\ProjectPlan::title($phase),
        ]), 250) : '';
    }

    /** At most this can still be advanced on a milestone: its amount (less deposit) less live advances on it */
    private function advanceRoom(object $phase): float
    {
        return max(0.0, round((float) \App\Support\Projects\Deposit::forMilestone($phase)['due'] - \App\Support\Finance\Advances::advancedOn((string) $phase->id), 2));
    }

    /** The "less advance" lines for the milestones ticked: shown in the form, written with the invoice */
    private function deductions(array $milestoneIds): array
    {
        return \App\Support\Finance\Advances::deductionLines($milestoneIds,
            fn ($advance) => Str::limit(__('invoices.lines.less_advance', ['number' => $advance->invoice_number]), 250));
    }

    /** A line's signed amount: a discount counts against the total */
    private static function signed(array $line): float
    {
        $amount = round(abs((float) ($line['amount'] ?? 0)), 2);

        return ($line['kind'] ?? 'charge') === 'discount' ? -$amount : $amount;
    }

    /** Removing a milestone's line unticks the milestone */
    public function removeLine(int $index): void
    {
        $line = $this->lines[$index] ?? null;
        if (! $line) {
            return;
        }
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        if (! empty($line['phase_id'])) {
            $this->picked = array_values(array_filter($this->picked, fn ($id) => (string) $id !== (string) $line['phase_id']));
        }
    }

    /**
     * The project's milestones no live invoice bills yet, in order, each with its board column (so Finance sees which
     * are accepted: the SOW invoices on written acceptance) and the amount its line would carry. Any of them can be
     * ticked (owner, 10 Nov 2026).
     */
    private function offeredPhases(string $projectId): \Illuminate\Support\Collection
    {
        $phases = DB::table('project_phases')->where('project_id', $projectId)->whereNull('deleted_at')->orderBy('phase_number')->get();
        $billed = \App\Support\Finance\MilestoneInvoices::billed($phases->pluck('id'));

        return $phases->reject(fn ($p) => in_array((string) $p->id, $billed, true))->values()->map(function ($p) {
            $p->column = \App\Support\Projects\ProjectPlan::column($p);
            $p->due = \App\Support\Projects\Deposit::forMilestone($p)['due'];

            return $p;
        });
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    /** The projects Create invoice offers: not completed or cancelled, newest first, with their client */
    private function activeProjects()
    {
        return DB::table('projects')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->whereNotIn('projects.status', ['Completed', 'Cancelled'])
            ->select('projects.id', 'projects.title', 'projects.currency', 'organizations.name as client_name')
            ->orderBy('projects.created_at', 'desc');
    }

    private function activeProject(string $projectId): ?object
    {
        return $projectId === '' ? null : $this->activeProjects()->where('projects.id', $projectId)->first();
    }

    /** The project's billing company, once per request (render and the checks ask for it again) */
    private function entityOf(string $projectId): ?string
    {
        if (! array_key_exists($projectId, $this->entities)) {
            $this->entities[$projectId] = Vat::projectEntity($projectId);
        }

        return $this->entities[$projectId];
    }

    public function updatedNewProjectId($projectId)
    {
        // When a project is selected, fetch the phases (milestones) not invoiced yet and its currency. A milestone is
        // invoiced on the client's acceptance, so accepted ones are offered too (docs/PROJECTS.md, decision 2). Another
        // project's ticks and milestone lines don't carry over; free lines do.
        $this->picked = [];
        $this->lines = array_values(array_filter($this->lines, fn ($l) => empty($l['phase_id'])));
        $this->advance_phase_id = '';
        $this->advance_description = '';
        if (!empty($projectId)) {
            $this->projectPhases = $this->offeredPhases((string) $projectId);

            $project = $this->activeProject((string) $projectId);
            if ($project) {
                $this->new_currency = $project->currency;
            }
            // The only account that fits is chosen for you
            $matching = $this->matchingAccounts();
            $this->new_remittance_id = $matching->count() === 1 ? (string) $matching->first()->id : '';
        } else {
            $this->projectPhases = [];
        }
    }

    /** The tab's invoices, soonest due first: only what the list shows */
    private function invoices()
    {
        return DB::table('invoices')
            ->join('projects', 'invoices.project_id', '=', 'projects.id')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->where('invoices.status', $this->activeTab)
            ->select(
                'invoices.id', 'invoices.invoice_number', 'invoices.invoice_type', 'invoices.currency', 'invoices.total_amount',
                'invoices.status', 'invoices.due_date', 'invoices.emailed_at',
                'projects.title as project_title',
                'organizations.name as client_name'
            )
            ->orderBy('invoices.due_date', 'asc')
            ->paginate(self::PER_PAGE);
    }

    public function draftSubsequentInvoice()
    {
        abort_unless(Auth::user()->can('finance.create'), 403);

        if ($this->new_kind === 'advance') {
            $this->createAdvance();

            return;
        }

        // The milestones that may be on it are worked out again here, never taken from the form: this project's, and
        // not already billed by a live invoice
        $offered = $this->new_project_id ? $this->offeredPhases((string) $this->new_project_id)->pluck('id')->map(fn ($id) => (string) $id)->all() : [];
        $this->lines = array_values(array_map(function ($l) {
            $discount = ($l['kind'] ?? 'charge') === 'discount';

            return [
                'kind' => $discount ? 'discount' : 'charge',
                // A discount is never a milestone's line
                'phase_id' => $discount || empty($l['phase_id']) ? null : (string) $l['phase_id'],
                'description' => trim((string) ($l['description'] ?? '')),
                'amount' => $l['amount'] ?? '',
            ];
        }, $this->lines));
        $this->validate([
            'new_project_id' => 'required|exists:projects,id',
            'lines' => 'required|array|min:1|max:' . self::MAX_LINES,
            'lines.*.description' => 'required|string|max:255',
            'lines.*.amount' => 'required|numeric|min:0.01|max:9999999999999',
            'lines.*.phase_id' => ['nullable', 'distinct', Rule::in($offered)],
            'new_payment_terms' => 'required|string',
            'new_remittance_id' => ['required', Rule::in($this->matchingAccounts()->pluck('id')->map(fn ($id) => (string) $id)->all())],
        ]);
        if (! collect($this->lines)->contains('kind', 'charge')) {
            $this->addError('lines', 'An invoice needs at least one line to charge, not only discounts.');

            return;
        }

        $milestoneIds = array_values(array_unique(array_filter(array_column($this->lines, 'phase_id'))));
        // Advances already invoiced on these milestones come off by themselves (App\Support\Finance\Advances)
        $lines = array_merge(
            array_map(fn ($l) => $l + ['advance_invoice_id' => null, 'amount_signed' => self::signed($l)], $this->lines),
            array_map(fn ($d) => $d + ['amount_signed' => round((float) $d['amount'], 2)], $this->deductions($milestoneIds)),
        );
        if (round(array_sum(array_column($lines, 'amount_signed')), 2) <= 0) {
            $this->addError('lines', 'The discounts and advances take this invoice to nothing. Lower the discount, or check the advances on these milestones.');

            return;
        }

        $this->issue($this->new_invoice_type, $lines, $milestoneIds, count($milestoneIds) === 1 ? $milestoneIds[0] : null);
    }

    /**
     * An advance on one milestone (owner, 13 Nov 2026): part of the milestone billed before its acceptance. One line,
     * kind 'advance'; the milestone is still offered for its own invoice, which then takes the advance off.
     */
    private function createAdvance(): void
    {
        $offered = $this->new_project_id ? $this->offeredPhases((string) $this->new_project_id) : collect();
        $this->advance_description = trim($this->advance_description);
        $this->validate([
            'new_project_id' => 'required|exists:projects,id',
            'advance_phase_id' => ['required', Rule::in($offered->pluck('id')->map(fn ($id) => (string) $id)->all())],
            'advance_amount' => 'required|numeric|min:0.01|max:9999999999999',
            'advance_description' => 'required|string|max:255',
            'new_payment_terms' => 'required|string',
            'new_remittance_id' => ['required', Rule::in($this->matchingAccounts()->pluck('id')->map(fn ($id) => (string) $id)->all())],
        ], [
            'advance_phase_id.required' => 'Choose the milestone this advance is on.',
            'advance_phase_id.in' => 'That milestone is already invoiced, or belongs to another project.',
        ], ['advance_amount' => 'amount', 'advance_description' => 'description']);

        $phase = $offered->firstWhere('id', $this->advance_phase_id);
        $amount = round((float) $this->advance_amount, 2);
        $room = $this->advanceRoom($phase);
        if ($amount > $room + 0.004) {
            $this->addError('advance_amount', 'At most ' . \App\Livewire\Hq\HqMoney::format($this->new_currency, $room) . ' can be advanced on this milestone: its amount less what is already advanced on it.');

            return;
        }

        $this->issue(\App\Support\Finance\Advances::INVOICE_TYPE, [[
            'kind' => 'advance',
            'phase_id' => (string) $phase->id,
            'advance_invoice_id' => null,
            'description' => $this->advance_description,
            'amount_signed' => $amount,
        ]], [], null);
    }

    /**
     * Writes the invoice and its lines (each: kind, phase_id, advance_invoice_id, description, amount_signed), accrues
     * it in the general ledger, then tells and emails the client's billing people.
     */
    private function issue(string $type, array $lines, array $milestoneIds, ?string $phaseId): void
    {
        $project = DB::table('projects')->where('id', $this->new_project_id)->first();
        $org = DB::table('organizations')->where('id', $project->organization_id)->first();
        $amountDue = round(array_sum(array_map(fn ($l) => round((float) $l['amount_signed'], 2), $lines)), 2);

        // 1. VAT: only when the company issuing it (the project's opportunity's billing company, else Nigeria's) charges
        // VAT today, at 0% for a client abroad (App\Support\Finance\Vat); the invoice records what it applied, and who
        $vat = Vat::for($org, null, $this->entityOf((string) $project->id));
        $vatAmount = $vat['applies'] ? round($amountDue * $vat['rate'] / 100, 2) : 0.0;
        $totalAmount = $amountDue + $vatAmount;

        // 2. Due date: handle "Receipt" and "Net X" terms
        if (stripos(strtolower($this->new_payment_terms), 'receipt') !== false) {
            $days = 0;
        } else {
            $parsedDays = (int) filter_var($this->new_payment_terms, FILTER_SANITIZE_NUMBER_INT);
            $days = $parsedDays > 0 ? $parsedDays : 10; // Only fallback if explicitly 0 but not "Receipt"
        }
        $dueDate = now()->addDays($days);

        // Lock Current Exchange Rate (as it is now in Master data)
        $fxRate = 1.000000;
        if ($this->new_currency !== 'USD') {
            $rateData = DB::table('mdm_exchange_rates')->where('currency_code', $this->new_currency)->first(['rate_to_usd']);
            if ($rateData) $fxRate = $rateData->rate_to_usd;
        }

        $invoiceId = (string) Str::uuid();
        $invNumber = 'INV-' . date('Ym') . '-' . rand(1000, 9999);
        $timestamp = now();
        $deducted = array_values(array_filter(array_column($lines, 'advance_invoice_id')));

        DB::transaction(function () use ($invoiceId, $invNumber, $project, $org, $vat, $vatAmount, $totalAmount, $dueDate, $fxRate, $timestamp, $lines, $amountDue, $milestoneIds, $phaseId, $type, $deducted) {
            // Someone else may have invoiced one of these milestones, or used one of its advances, since the form
            // opened: then nothing is created
            if ($milestoneIds !== []) {
                DB::table('project_phases')->whereIn('id', $milestoneIds)->lockForUpdate()->get(['id']);
                if (\App\Support\Finance\MilestoneInvoices::billed($milestoneIds) !== []) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'lines' => 'A milestone on this invoice was invoiced a moment ago. Close this window, open Create invoice again and tick the milestones still to invoice.',
                    ]);
                }
                $open = \App\Support\Finance\Advances::open($milestoneIds)->pluck('invoice_id')->map(fn ($id) => (string) $id)->sort()->values()->all();
                if ($open !== collect($deducted)->sort()->values()->all()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'lines' => 'An advance on one of these milestones changed a moment ago. Close this window and open Create invoice again.',
                    ]);
                }
            }

            DB::table('invoices')->insert([
                'id' => $invoiceId,
                'project_id' => $project->id,
                // An invoice for exactly one milestone names it, as before; with several, each line names its own. An
                // advance never names it (it doesn't bill the milestone: Advances)
                'phase_id' => $phaseId,
                'invoice_type' => $type,
                'invoice_number' => $invNumber,
                'amount_due' => $amountDue,
                'vat_amount' => $vatAmount,
                'vat_rate' => $vat['rate'],
                'vat_applied' => $vat['applies'],
                'billing_entity_id' => $vat['entity_id'],
                'region_code' => $vat['region'] ?? Vat::HOME,
                'total_amount' => $totalAmount,
                'wht_amount' => 0.00,
                'currency' => $this->new_currency,
                'exchange_rate_locked' => $fxRate,
                'status' => 'Pending',
                'due_date' => $dueDate,
                'client_billing_address' => $org->billing_address ?? 'TBD',
                'client_tax_id' => $org->tax_identification_number ?? null,
                'remittance_account_id' => $this->new_remittance_id,
                'payment_terms' => $this->new_payment_terms,
                'jbs_signature_hash' => 'SHA256-JBS-' . strtoupper(hash('sha256', $invoiceId . $totalAmount . $timestamp)),
                'created_by' => Auth::id(),
                'issued_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp
            ]);

            // 3. INVOICE LINES, one per line in the form's order, each with its milestone, then the advances taken off
            // (id is auto-increment; no timestamps)
            $metric = ['discount' => 'Discount', 'advance' => 'Advance', 'less_advance' => 'Advance deducted'];
            DB::table('invoice_lines')->insert(array_map(fn ($line) => [
                'invoice_id' => $invoiceId,
                'kind' => $line['kind'],
                'advance_invoice_id' => $line['advance_invoice_id'] ?? null,
                'phase_id' => $line['phase_id'],
                'description' => Str::limit($line['description'], 252),
                'metric_of_billing' => $metric[$line['kind']] ?? ($line['phase_id'] ? 'Phase Completion' : 'Fixed Fee'),
                'quantity' => 1,
                'unit_price' => round((float) $line['amount_signed'], 2),
                'total_price' => round((float) $line['amount_signed'], 2),
            ], $lines));

            // Accrue the receivable in the general ledger, as the proposal gateway does for deposits
            \App\Livewire\Hq\Finance\GeneralLedgerManager::recordInvoiceIssued($invoiceId);
        });
        // The client's billing people hear about it in their portal and on their devices (docs/PWA.md), and get it by
        // email with its PDF (docs/QA-PORTAL.md D11)
        \App\Support\Pwa\ClientNotify::invoice($invoiceId, 'invoice_issued');
        $reached = $this->emailToClient($invoiceId);

        $this->showCreateModal = false;
        $this->reset(['new_project_id', 'picked', 'lines', 'new_kind', 'advance_phase_id', 'advance_amount', 'advance_description']);
        session()->flash('success', ($type === \App\Support\Finance\Advances::INVOICE_TYPE ? 'Advance invoice ' : 'Invoice ') . "{$invNumber} created for " . ($org->name ?? 'the client') . '. ' . $this->emailedLine($reached));
    }

    /** Record payment: opens the form for an unpaid invoice, the amount left filled in */
    public function openRecordPayment(string $invoiceId): void
    {
        abort_unless(Auth::user()->can('finance.approve'), 403);

        $invoice = DB::table('invoices')->where('id', $invoiceId)->whereNull('deleted_at')->whereIn('status', self::CANCELLABLE)->first();
        if (! $invoice) {
            session()->flash('warning', 'Only a sent or overdue invoice can take a payment. Refresh the page and try again.');

            return;
        }
        $this->resetErrorBag();
        $this->pay_invoice_id = (string) $invoice->id;
        $this->pay_amount = \App\Support\Finance\InvoicePayments::balance($invoice);
        $this->pay_received_on = now('Africa/Lagos')->toDateString();
        $this->pay_method = 'Bank Transfer';
        $this->pay_reference = '';
        $this->pay_wht = '';
        $this->pay_note = '';
    }

    public function closeRecordPayment(): void
    {
        $this->pay_invoice_id = null;
        $this->resetErrorBag();
    }

    /**
     * Money that arrived (owner, 13 Nov 2026: a transfer, cash or a cheque the client didn't upload in the portal):
     * recorded and verified at once, part payments add up, and the invoice is Paid when they reach its total.
     */
    public function recordPayment(): void
    {
        abort_unless(Auth::user()->can('finance.approve'), 403);

        $this->validate([
            'pay_invoice_id' => 'required|string',
            'pay_amount' => 'required|numeric|min:0.01|max:9999999999999',
            'pay_received_on' => 'required|date|before_or_equal:' . now('Africa/Lagos')->toDateString(),
            'pay_method' => ['required', Rule::in(\App\Support\Finance\InvoicePayments::METHODS)],
            'pay_reference' => 'required|string|max:250',
            'pay_wht' => 'nullable|numeric|min:0|max:9999999999999',
            'pay_note' => 'nullable|string|max:250',
        ], [
            'pay_received_on.before_or_equal' => 'The day the money arrived can’t be in the future.',
            'pay_reference.required' => 'Add the bank or transfer reference, or say how it was paid (e.g. "Cash, received by Elisha").',
        ], ['pay_amount' => 'amount', 'pay_received_on' => 'date received', 'pay_method' => 'how it was paid', 'pay_reference' => 'reference', 'pay_wht' => 'withholding tax']);

        try {
            $result = \App\Support\Finance\InvoicePayments::recordFromHq($this->pay_invoice_id, [
                'amount' => $this->pay_amount,
                'received_on' => $this->pay_received_on,
                'method' => $this->pay_method,
                'reference' => $this->pay_reference,
                'wht' => $this->pay_wht === '' || $this->pay_wht === null ? 0 : $this->pay_wht,
                'note' => $this->pay_note,
            ], (string) Auth::id());
        } catch (\DomainException $e) {
            $this->addError('pay_amount', $e->getMessage());

            return;
        }

        $invoice = DB::table('invoices')->where('id', $this->pay_invoice_id)->first(['invoice_number', 'currency']);
        $this->pay_invoice_id = null;
        session()->flash('success', $result['paid']
            ? "Payment recorded. Invoice {$invoice->invoice_number} is now paid."
            : "Payment recorded on {$invoice->invoice_number}. " . \App\Livewire\Hq\HqMoney::format($invoice->currency, $result['balance']) . ' is left to pay.');
    }

    /** Finance › Invoices › Email again: the invoice and its PDF to the client's billing contacts */
    public function emailInvoice($invoiceId)
    {
        abort_unless(Auth::user()->can('finance.create'), 403);

        $invoice = DB::table('invoices')->where('id', $invoiceId)->whereNull('deleted_at')->first();
        if (! $invoice || $invoice->status === 'Cancelled') {
            session()->flash('warning', 'Only an invoice that isn’t cancelled can be emailed. Refresh the page and try again.');
            return;
        }
        $reached = $this->emailToClient($invoiceId);
        session()->flash($reached ? 'success' : 'warning', "Invoice {$invoice->invoice_number}: " . $this->emailedLine($reached));
    }

    /** @return list<string>|null the addresses reached; null when the email couldn't be prepared */
    private function emailToClient(string $invoiceId): ?array
    {
        try {
            return \App\Support\Finance\InvoiceMailer::send($invoiceId);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Invoice email not prepared', ['invoice_id' => $invoiceId, 'message' => $e->getMessage()]);

            return null;
        }
    }

    private function emailedLine(?array $reached): string
    {
        return match (true) {
            $reached === null => 'We couldn’t prepare its email. Try Email again; if it keeps failing, check the server log.',
            $reached === [] => 'Nobody at the client was emailed: no active portal account there sees invoices. Invite their finance contact, then use Email again.',
            default => 'Emailed with its PDF to ' . implode(', ', $reached) . '.',
        };
    }

    public function cancelInvoice($invoiceId)
    {
        abort_unless(Auth::user()->can('finance.delete'), 403);

        // Only an unpaid invoice (pending or overdue) can be cancelled: never a paid one, never twice
        $invoice = DB::table('invoices')->where('id', $invoiceId)->first();
        if (! $invoice) {
            session()->flash('warning', "We couldn’t find that invoice. Refresh the page and try again.");
            return;
        }
        if (! in_array($invoice->status, self::CANCELLABLE, true)) {
            session()->flash('warning', $invoice->status === 'Paid'
                ? "Invoice {$invoice->invoice_number} is paid, so it can’t be cancelled."
                : "Invoice {$invoice->invoice_number} is already cancelled.");
            return;
        }
        // Money already recorded on it would be lost with it
        if (\App\Support\Finance\InvoicePayments::hasPayments((string) $invoice->id)) {
            session()->flash('warning', "Invoice {$invoice->invoice_number} has a payment recorded on it, so it can’t be cancelled. Record what is still owed on it instead, or give a discount on the next invoice.");
            return;
        }
        // An advance another invoice has already taken off: cancelling it would bill the client twice
        if ($by = \App\Support\Finance\Advances::deductedBy((string) $invoice->id)) {
            session()->flash('warning', "Advance {$invoice->invoice_number} is taken off invoice {$by->invoice_number}. Cancel {$by->invoice_number} first, then this advance.");
            return;
        }

        $updated = DB::table('invoices')
            ->where('id', $invoiceId)
            ->whereIn('status', self::CANCELLABLE)
            ->update([
                'status' => 'Cancelled',
                'updated_by' => Auth::id(),
                'updated_at' => now()
            ]);

        if (! $updated) {
            session()->flash('warning', "Invoice {$invoice->invoice_number} changed while you were working, so it wasn’t cancelled. Check its status and try again.");
            return;
        }

        DB::table('system_audit_logs')->insert([
            // Safe null UUID handling
            'user_id' => Auth::check() ? Auth::id() : null, 
            'action' => 'INVOICE_CANCELLED',
            'description' => "Cancelled invoice {$invoice->invoice_number}.",
            'target_table' => 'invoices',
            'target_id' => $invoice->id,
            'old_values' => json_encode(['status' => $invoice->status]),
            'new_values' => json_encode(['status' => 'Cancelled']),
            'ip_address' => request()?->ip(),
            'created_at' => now()
        ]);
        // Its receivable leaves the general ledger with it
        \App\Livewire\Hq\Finance\GeneralLedgerManager::recordInvoiceCancelled($invoice->id);
        \App\Support\Pwa\ClientNotify::invoice($invoiceId, 'invoice_cancelled');

        session()->flash('success', "Invoice {$invoice->invoice_number} cancelled.");
    }

    /**
     * Overdue check: marks unpaid invoices past their due date as overdue,
     * which puts the client’s portal access for that project on hold.
     * In production this runs daily via the Laravel scheduler; staff with
     * finance.approve can also run it from the Invoices screen.
     */
    public function enforceGuillotineProtocol()
    {
        abort_unless(Auth::user()->can('finance.approve'), 403);

        $expiredInvoices = DB::table('invoices')
            ->where('status', 'Pending')
            ->whereDate('due_date', '<', now()->toDateString())
            ->get(['id', 'invoice_number']);

        if ($expiredInvoices->isEmpty()) {
            session()->flash('success', 'No unpaid invoices are past their due date. Nothing was changed.');
            return;
        }

        // Finance approvers hear about each invoice marked overdue (looked up once, written in one insert)
        $admins = Access::staffWith('finance.approve');

        $count = 0;
        DB::transaction(function () use ($expiredInvoices, &$count, $admins) {
            $notifications = [];
            foreach ($expiredInvoices as $invoice) {
                // 1. Mark the invoice overdue (only if it is still unpaid: a payment may have landed meanwhile)
                $marked = DB::table('invoices')->where('id', $invoice->id)->where('status', 'Pending')->update([
                    'status' => 'Overdue',
                    'updated_at' => now()
                ]);
                if (! $marked) {
                    continue;
                }
                $count++;

                // 2. Alert finance approvers
                foreach($admins as $adminId) {
                    $notifications[] = [
                        'id' => (string) Str::uuid(), 'user_id' => $adminId,
                        'title' => 'Invoice overdue', 
                        'message' => "Invoice {$invoice->invoice_number} is past its due date. The client’s portal access for this project is on hold until it is paid. Decide whether work on the project should pause.", 
                        'type' => 'System', 'created_at' => now()
                    ];
                }
                \App\Support\Pwa\ClientNotify::invoice($invoice->id, 'invoice_overdue');

                // 3. Audit trail (safe null UUID)
                DB::table('system_audit_logs')->insert([
                    'user_id' => Auth::check() ? Auth::id() : null, // Prevent 'SYSTEM_CRON' crash
                    'action' => 'GUILLOTINE_PROTOCOL_ENGAGED',
                    'description' => "Invoice {$invoice->invoice_number} is past its due date and was marked overdue. Client Portal access for the project is on hold.",
                    'target_table' => 'invoices',
                    'target_id' => $invoice->id,
                    'old_values' => json_encode(['status' => 'Pending']),
                    'new_values' => json_encode(['status' => 'Overdue']),
                    'ip_address' => request()?->ip(),
                    'created_at' => now()
                ]);
            }
            foreach (array_chunk($notifications, 500) as $rows) {
                DB::table('notifications')->insert($rows);
            }
        });

        session()->flash('warning', $count . ($count === 1 ? ' invoice was' : ' invoices were') . " marked overdue. Client Portal access for those projects is on hold until they’re paid.");
    }
    /** Accounts the client may pay this project's invoice into: its currency, held by its billing company (D8) */
    private function matchingAccounts(): \Illuminate\Support\Collection
    {
        return $this->new_project_id ? ReceivingAccounts::for($this->new_currency, $this->entityOf((string) $this->new_project_id)) : collect();
    }

    /** Field names in validation messages: the labels people see, not the property names (docs/BRAND.md sections 5 and 12) */
    protected function validationAttributes(): array
    {
        return [
            'new_project_id' => 'project',
            'new_payment_terms' => 'payment terms',
            'new_remittance_id' => 'account to pay into',
            'lines' => 'lines',
            'lines.*.description' => 'line description',
            'lines.*.amount' => 'line amount',
            'lines.*.phase_id' => 'milestone',
        ];
    }

    /** Validation messages that say what to enter instead */
    public function messages(): array
    {
        return [
            'lines.required' => 'Tick a milestone or add a line.',
            'lines.min' => 'Tick a milestone or add a line.',
            'lines.max' => 'An invoice can have up to ' . self::MAX_LINES . ' lines.',
            'lines.*.description.required' => 'Describe this line.',
            'lines.*.description.max' => 'Keep this line to 255 characters.',
            'lines.*.amount.required' => 'Enter this line’s amount before VAT.',
            'lines.*.amount.numeric' => 'Enter this line’s amount as a number.',
            'lines.*.amount.min' => 'Enter an amount above 0.',
            'lines.*.phase_id.in' => 'This milestone is invoiced already or belongs to another project. Untick it.',
            'lines.*.phase_id.distinct' => 'This milestone is on the invoice twice. Remove one of its lines.',
            'new_remittance_id.in' => 'Choose an account in the project’s currency held by the company that bills it.',
        ];
    }

    public function render()
    {
        // What Create invoice adds for the project chosen: VAT, or a line saying why none (App\Support\Finance\Vat). The
        // client's country and tax number, all Vat::for() reads of the organisation, come with the project.
        $project = $this->showCreateModal && $this->new_project_id ? DB::table('projects')
            ->leftJoin('organizations', 'organizations.id', '=', 'projects.organization_id')
            ->where('projects.id', $this->new_project_id)
            ->first(['projects.id', 'organizations.country', 'organizations.tax_identification_number']) : null;
        $vat = $project ? Vat::for($project, null, $this->entityOf((string) $project->id)) : null;

        $invoices = $this->invoices();
        $milestoneIds = array_values(array_unique(array_filter(array_column($this->lines, 'phase_id'))));
        $deductions = $this->showCreateModal && $this->new_kind === 'invoice' ? $this->deductions($milestoneIds) : [];
        $advancePhase = $this->new_kind === 'advance' ? collect($this->projectPhases)->firstWhere('id', $this->advance_phase_id) : null;

        return view('livewire.hq.finance.invoice-ledger', [
            'invoices' => $invoices,
            // Verified payments so far on each invoice listed (InvoicePayments): "Paid … · … left"
            'settled' => \App\Support\Finance\InvoicePayments::settled($invoices->getCollection()->pluck('id')),
            'deductions' => $deductions,
            'advanceRoom' => $advancePhase ? $this->advanceRoom($advancePhase) : null,
            'payInvoice' => $this->pay_invoice_id ? DB::table('invoices')->where('id', $this->pay_invoice_id)->first(['id', 'invoice_number', 'currency', 'total_amount']) : null,
            'activeProjects' => $this->showCreateModal ? $this->activeProjects()->get() : collect(),
            'vat' => $vat,
            // The accounts of the company Vat::for() chose (the same as matchingAccounts(), without looking it up again)
            'matchingAccounts' => $project ? ReceivingAccounts::heldBy($this->new_currency, $vat['entity_id']) : collect(),
            'billingCompany' => $vat['company'] ?? null,
            // Charges less discounts less the advances taken off
            'linesTotal' => round(array_sum(array_map(fn ($l) => is_numeric($l['amount'] ?? null) ? self::signed($l) : 0.0, $this->lines))
                + array_sum(array_column($deductions, 'amount')), 2),
        ]);
    }
}