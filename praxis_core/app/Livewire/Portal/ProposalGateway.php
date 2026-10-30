<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\WithGatewayLanguage;
use App\Mail\SigningCode;
use App\Mail\UserInvitation;
use App\Support\Access;
use App\Support\MailLocale;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

#[Layout('components.layouts.portal')]
class ProposalGateway extends Component
{
    use WithGatewayLanguage;

    // Locked: the browser cannot point the page at another proposal or skip a step
    #[Locked]
    public $token;

    // Only interactive inputs remain in public state for security and speed
    public $signerName = '';
    public $signerTitle = '';
    public $signerEmail = '';
    public $signatureBase64 = ''; 
    public $otpInput = '';
    #[Locked]
    public $viewState = 'review'; // review, otp_verification, completed

    // The standard phase names HQ stores in English (current and earlier wording), by meaning;
    // they are shown in the page language (lang gateway.proposal.phase_names)
    private const PHASE_NAMES = [
        'diagnosis and design' => 'diagnosis',
        'diagnostic & blueprint' => 'diagnosis',
        'build' => 'build',
        'architecture build' => 'build',
        'go-live' => 'go_live',
        'execution engine' => 'go_live',
        'testing' => 'testing',
        'testing and handover' => 'testing_handover',
        'qa & handover' => 'testing_handover',
        'go-live and handover' => 'go_live_handover',
    ];

    // Validation messages in the page language (lang/<locale>/gateway.php)
    protected function messages(): array
    {
        return (array) __('gateway.validation.proposal');
    }

    public function mount($token)
    {
        $this->token = $token;
        
        $proposal = DB::table('proposals')->where('token_hash', $this->token)->first();

        // The page speaks the organisation's language unless the person already chose one
        $this->useGatewayLanguage($proposal
            ? DB::table('organizations')->where('id', $proposal->organization_id)->value('preferred_language')
            : null);

        if (!$proposal) {
            abort(404, __('gateway.proposal.not_found'));
        }

        // Temporal Expiration Check
        $expirationDate = Carbon::parse($proposal->updated_at)->addDays($proposal->validity_days);
        if (now()->greaterThan($expirationDate) && !in_array($proposal->status, ['Authorized', 'Converted'])) {
            DB::table('proposals')->where('id', $proposal->id)->update(['status' => 'Expired']);
            abort(403, __('gateway.proposal.expired'));
        }

        // Resume State Recovery (If they refresh during OTP phase)
        if (in_array($proposal->status, ['Authorized', 'Converted'])) {
            $this->viewState = 'completed';
        } elseif ($proposal->auth_otp && $proposal->otp_expires_at && now()->lessThan($proposal->otp_expires_at)) {
            $this->viewState = 'otp_verification';
        }
    }

    // --- PHASE 1: PRE-SAVE & OTP DISPATCH ---
    public function requestAuthorizationOtp($signatureB64)
    {
        $this->signatureBase64 = $signatureB64; 

        $this->validate([
            'signerName' => 'required|string|max:150',
            'signerTitle' => 'required|string|max:100',
            'signerEmail' => 'required|email|max:100',
            'signatureBase64' => 'required|string'
        ]);

        $proposal = DB::table('proposals')->where('token_hash', $this->token)->first();
        if (!$proposal) return;

        // A signed proposal can never be signed again: its recorded signature and signer stay as they are
        if (in_array($proposal->status, ['Authorized', 'Converted'])) {
            $this->viewState = 'completed';
            $this->dispatch('reinit-deck');
            return;
        }
        if (! $this->awaitsClientSignature($proposal)) {
            $this->addError('signingCode', __('gateway.proposal.not_ready'));
            return;
        }

        // The signatory must be an active contact of this organisation (strict email match)
        $vectorPerson = DB::table('client_users')
            ->where('organization_id', $proposal->organization_id)
            ->where('email', $this->signerEmail)
            ->whereNull('deleted_at')
            ->first();

        if (!$vectorPerson) {
            $this->addError('signerEmail', __('gateway.proposal.email_not_recognised'));

            // Event to re-initialise the canvas in case the page layout shifted
            $this->dispatch('reinit-canvas');
            return;
        }

        // At most 3 signing codes per proposal and IP every 15 minutes (the same limit as the sign-off page)
        $limiterKey = 'proposal-otp-send:' . $proposal->id . ':' . Request::ip();
        if (RateLimiter::tooManyAttempts($limiterKey, 3)) {
            $this->addError('signingCode', __('gateway.proposal.too_many_codes'));
            return;
        }
        RateLimiter::hit($limiterKey, 900);

        // Generate OTP
        $otp = (string) random_int(100000, 999999);
        
        // PRE-SAVE: Secure the signature immediately so it survives F5 refreshes
        DB::table('proposals')->where('id', $proposal->id)->update([
            'auth_otp' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
            'signed_by_name' => $this->signerName . ' (' . $this->signerTitle . ')',
            'client_signature_b64' => $this->signatureBase64,
            'updated_at' => now()
        ]);

        // Email the signing code: the organisation's preferred language, else the page language
        $orgLanguage = DB::table('organizations')->where('id', $proposal->organization_id)->value('preferred_language');
        // The signer is on the page now: a language they picked on the site wins over the stored preference
        $locale = session()->has('locale') ? MailLocale::forVisitor() : MailLocale::forRecipient($orgLanguage);
        try {
            Mail::to($this->signerEmail)->locale($locale)
                ->send(new SigningCode($otp, SigningCode::PROPOSAL, $proposal->title ?: $proposal->proposal_ref, $vectorPerson->first_name));
        } catch (\Exception $e) {
            // Fallback for local testing so you can read the OTP in your log file
            Log::info("Praxis Auth OTP for {$this->signerEmail}: {$otp}");
        }

        $this->viewState = 'otp_verification';
        $this->dispatch('reinit-deck'); // Tells Alpine.js to hold on Card 5
    }

    public function verifyOtpAndExecuteLaunch()
    {
        $this->validate(['otpInput' => 'required|string|size:6']);

        $proposal = DB::table('proposals')->where('token_hash', $this->token)->first();
        if (!$proposal) return;

        // Idempotency Lock
        if (in_array($proposal->status, ['Authorized', 'Converted'])) {
            $this->viewState = 'completed';
            $this->dispatch('reinit-deck');
            return;
        }
        if (! $this->awaitsClientSignature($proposal)) {
            $this->addError('otpInput', __('gateway.proposal.not_ready'));
            return;
        }

        // At most 5 wrong codes per proposal and IP every 15 minutes (the same limit as the sign-off page)
        $checkKey = 'proposal-otp-check:' . $proposal->id . ':' . Request::ip();
        if (RateLimiter::tooManyAttempts($checkKey, 5)) {
            $this->addError('otpInput', __('gateway.proposal.too_many_attempts'));
            return;
        }

        // Validate OTP
        if ($proposal->auth_otp !== $this->otpInput || now()->greaterThan($proposal->otp_expires_at)) {
            RateLimiter::hit($checkKey, 900);
            $this->addError('otpInput', __('gateway.proposal.code_invalid'));
            return;
        }
        RateLimiter::clear($checkKey);

        $organization = DB::table('organizations')->where('id', $proposal->organization_id)->first();
        $ipAddress = Request::ip();
        $timestamp = now();
        
        $finalName = $proposal->signed_by_name; 
        
        // Generate the immutable SHA-256 seal
        $clientHash = 'SHA256-CLIENT-' . strtoupper(hash('sha256', $proposal->id . $finalName . $ipAddress . $timestamp));

        $signed = DB::transaction(function () use ($proposal, $organization, $ipAddress, $timestamp, $clientHash, $finalName) {

            // 1. Lock the proposal (code cleared, fingerprint applied), only while it still awaits the
            // client: HQ may have unlocked it since the page was opened
            $locked = DB::table('proposals')->where('id', $proposal->id)
                ->where('status', 'Pending Auth')->whereNotNull('hq_signature_hash')
                ->update([
                    'status' => 'Authorized',
                    'client_signature_hash' => $clientHash,
                    'client_ip_address' => $ipAddress,
                    'signed_at' => $timestamp,
                    'auth_otp' => null,
                    'otp_expires_at' => null,
                    'updated_at' => $timestamp
                ]);
            if (! $locked) {
                return false;
            }

            DB::table('organizations')->where('id', $organization->id)->update(['status' => 'Active Client', 'updated_at' => $timestamp]);

            // 2. Create the project
            $projectId = (string) Str::uuid();
            DB::table('projects')->insert([
                'id' => $projectId,
                'organization_id' => $organization->id,
                'title' => $proposal->title,
                'description' => 'Created from signed proposal ' . $proposal->proposal_ref . '.',
                'status' => 'Scoping', 
                'currency' => $proposal->currency,
                'total_budget' => $proposal->total_value,
                'created_by' => $proposal->created_by,
                'created_at' => $timestamp,
                'updated_at' => $timestamp
            ]);

            // updated_at is set here, or MySQL's ON UPDATE writes it in the server's own time zone
            DB::table('proposals')->where('id', $proposal->id)->update(['project_id' => $projectId, 'updated_at' => $timestamp]);

            // 3. Add the phases and budgets
            $narrative = DB::table('proposal_ai_narratives')->where('proposal_id', $proposal->id)->first();
            $phases = $narrative ? json_decode($narrative->ai_phases_json, true) : [];

            if ($phases && is_array($phases)) {
                foreach ($phases as $index => $phase) {
                    DB::table('project_phases')->insert([
                        'id' => (string) Str::uuid(), 
                        'project_id' => $projectId,
                        'title' => current(explode(':', $phase['title'] ?? 'Phase ' . ($index + 1))), 
                        'description' => ($phase['title'] ?? 'Phase') . ': scope as set out in the proposal.',
                        'phase_number' => $index + 1, 
                        'weight_percentage' => ((float)($phase['weight'] ?? 1) / 4.0) * 100, 
                        'status' => 'Pending', 
                        'created_at' => $timestamp
                    ]);
                }
            }

            $lineItems = DB::table('proposal_line_items')->where('proposal_id', $proposal->id)->get();
            foreach ($lineItems as $line) {
                DB::table('project_budgets')->insert([
                    'id' => (string) Str::uuid(), 
                    'project_id' => $projectId,
                    'description' => $line->description, 
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price, 
                    'total_allocated' => $line->total_price, 
                    'created_at' => $timestamp
                ]);
            }

            // 4. CALCULATE INVOICE #001 (Automated Deposit Generation)
            $depositPercentage = $proposal->payment_schedule_type === 'custom' 
                ? (float) $proposal->custom_deposit_percent 
                : (float) current(explode('_', $proposal->payment_schedule_type));

            $depositSubtotal = $proposal->subtotal * ($depositPercentage / 100);
            $depositVat = $proposal->vat_rate ? ($depositSubtotal * ($proposal->vat_rate / 100)) : 0;
            $depositTotal = $depositSubtotal + $depositVat;

            $invoiceId = (string) Str::uuid();
            $invNumber = 'INV-' . date('Ym') . '-' . random_int(1000, 9999);
            
            // Auto-FX Lock
            $fxRate = 1.000000;
            if ($proposal->currency !== 'USD') {
                $fxRate = DB::table('mdm_exchange_rates')->where('currency_code', $proposal->currency)->value('rate_to_usd') ?? 1.000000;
            }

            $remittanceId = DB::table('mdm_remittance_accounts')->where('is_active', 1)->value('id');

            // Due in 10 days
            $dueDate = now()->addDays(10);
            
            // SHA-256 fingerprint of the invoice
            $invHashData = $invoiceId . $depositTotal . $timestamp;
            $invoiceHash = 'SHA256-INV-' . strtoupper(hash('sha256', $invHashData));

            DB::table('invoices')->insert([
                'id' => $invoiceId, 
                'project_id' => $projectId, 
                'invoice_number' => $invNumber,
                'invoice_type' => 'Tax Invoice - Initial Deposit', 
                'po_sow_reference' => $proposal->proposal_ref,
                'client_billing_address' => $organization->billing_address ?? 'TBD', 
                'client_tax_id' => $organization->tax_identification_number ?? null,
                'amount_due' => $depositSubtotal, 
                'vat_amount' => $depositVat, 
                'wht_amount' => 0.00,
                'total_amount' => $depositTotal,
                'currency' => $proposal->currency, 
                'exchange_rate_locked' => $fxRate,
                'status' => 'Pending', // Direct to Active
                'due_date' => $dueDate, 
                'payment_terms' => 'Net 10',
                'remittance_account_id' => $remittanceId, 
                'jbs_signature_hash' => $invoiceHash,
                'created_by' => $proposal->created_by, // Attributed to the admin who drafted it
                'issued_at' => $timestamp,
                'created_at' => $timestamp, 
                'updated_at' => $timestamp
            ]);

            DB::table('invoice_lines')->insert([
                'invoice_id' => $invoiceId, 
                'description' => "Deposit ({$depositPercentage}%): " . $proposal->title,
                'metric_of_billing' => 'Fixed Milestone', 
                'quantity' => 1, 
                'unit_price' => $depositSubtotal, 
                'total_price' => $depositSubtotal
            ]);

            // 5. TRIGGER THE IFRS GENERAL LEDGER ENGINE
            \App\Livewire\Hq\Finance\GeneralLedgerManager::recordInvoiceIssued($invoiceId);

            // 6. Notify HQ: the sales team (deal won) and finance (deposit invoice issued)
            $recipients = array_unique(array_merge(Access::staffWith('crm.edit'), Access::staffWith('finance.approve')));
            $notifications = [];
            foreach($recipients as $adminId) {
                $notifications[] = [
                    'id' => (string) Str::uuid(), 'user_id' => $adminId,
                    'title' => 'Proposal signed',
                    'message' => "{$organization->name} signed proposal {$proposal->proposal_ref}. Deposit invoice {$invNumber} is issued and posted to the general ledger. Find it in Invoices.",
                    'type' => 'Project', 'created_at' => $timestamp
                ];
            }
            if(!empty($notifications)) DB::table('notifications')->insert($notifications);

            // 7. Make the signer the primary contact and send the account setup link
            // The signer's lead contact becomes the organisation's primary portal account
            $clientUser = DB::table('client_users')->where('organization_id', $organization->id)->where('email', $this->signerEmail)->whereNull('deleted_at')->first();
            if (!$clientUser) {
                $clientUser = DB::table('client_users')->where('organization_id', $organization->id)->whereNull('deleted_at')->orderBy('created_at')->first();
            }

            if ($clientUser) {
                $invitationToken = Str::random(60);

                DB::table('client_users')->where('id', $clientUser->id)->update([
                    'invitation_token' => $invitationToken,
                    'lifecycle_stage' => 'Onboarding',
                    'is_primary_client' => 1, 
                    'updated_at' => $timestamp
                ]);
                
                $setupUrl = route('client.setup', ['token' => $invitationToken]);
                // Only contacts who have never set a password get the setup email; a returning client already has access
                if ($clientUser->password === null) {
                    try {
                        $locale = session()->has('locale') ? MailLocale::forVisitor() : MailLocale::forRecipient($organization->preferred_language ?? null);
                        Mail::to($clientUser->email)->locale($locale)
                            ->send(new UserInvitation($clientUser->first_name, $invitationToken, true, $proposal->title ?: $proposal->proposal_ref));
                    } catch (\Exception $e) {
                        Log::info("Praxis Setup URL for {$clientUser->email}: {$setupUrl}");
                    }
                }
            }

            return true;
        });

        if (! $signed) {
            $this->addError('otpInput', __('gateway.proposal.not_ready'));
            return;
        }

        $this->viewState = 'completed';
        $this->dispatch('reinit-deck');
    }

    /** Only a proposal HQ has signed and sent (status Pending Auth) can be signed by the client */
    private function awaitsClientSignature(object $proposal): bool
    {
        return $proposal->status === 'Pending Auth' && filled($proposal->hq_signature_hash);
    }

    public function render()
    {
        // Queries run fresh on every render, so only the token travels in the page state
        $proposal = DB::table('proposals')->where('token_hash', $this->token)->first();
        $organization = $proposal ? DB::table('organizations')->where('id', $proposal->organization_id)->first() : null;
        $narrative = $proposal ? DB::table('proposal_ai_narratives')->where('proposal_id', $proposal->id)->first() : null;
        $lineItems = $proposal ? DB::table('proposal_line_items')->where('proposal_id', $proposal->id)->orderBy('sort_order')->get() : [];
        $phases = $narrative ? (json_decode($narrative->ai_phases_json, true) ?? []) : [];
        $phases = array_values(is_array($phases) ? $phases : []);
        $phases = array_map(fn ($phase, $index) => $this->withPhaseLabels((array) $phase, $index), $phases, array_keys($phases));

        $selectedTech = [];
        if ($proposal) {
            $selectedTech = DB::table('proposal_tech_stack')
                ->join('mdm_tech_catalog', 'proposal_tech_stack.tech_id', '=', 'mdm_tech_catalog.id')
                ->where('proposal_tech_stack.proposal_id', $proposal->id)
                ->pluck('mdm_tech_catalog.tech_name')
                ->toArray();
        }

        return view('livewire.portal.proposal-gateway', [
            'proposal' => $proposal,
            'organization' => $organization,
            'narrative' => $narrative,
            'lineItems' => $lineItems,
            'phases' => $phases,
            'selectedTech' => $selectedTech
        ])->title(__('gateway.proposal.page_title'));
    }

    /**
     * Adds 'label' (the full title) and 'short' (the step view's heading) in the page language. A standard
     * name ("Phase 3: Go-live") is translated; any other title is shown as stored.
     */
    private function withPhaseLabels(array $phase, int $index): array
    {
        $stored = trim((string) ($phase['title'] ?? ''));
        $number = $index + 1;
        $name = $stored;
        if (preg_match('/^phase\s*(\d+)\s*:\s*(.+)$/iu', $stored, $m)) {
            $number = (int) $m[1];
            $name = trim($m[2]);
        }

        $key = self::PHASE_NAMES[mb_strtolower($name)] ?? null;
        if ($key) {
            $phase['label'] = __('gateway.proposal.phase_title', ['number' => $number, 'name' => __('gateway.proposal.phase_names.' . $key)]);
            $phase['short'] = $phase['label'];
        } else {
            $phase['label'] = $stored;
            $phase['short'] = current(explode(':', $stored)); // as before: the part before the colon
        }

        return $phase;
    }
}