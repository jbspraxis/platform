<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\WithGatewayLanguage;
use App\Mail\SigningCode;
use App\Support\Access;
use App\Support\Finance\DepositInvoice;
use App\Support\MailLocale;
use App\Support\Portal\SignerOnboarding;
use App\Support\Signing\SigningCodes;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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

    // Signing code limits (App\Support\Signing\SigningCodes): 3 codes per proposal and per email address, 5 wrong
    // codes per proposal, every 15 minutes
    public const MAX_CODES = SigningCodes::MAX_CODES;
    public const MAX_WRONG_CODES = SigningCodes::MAX_WRONG_CODES;
    public const LOCK_SECONDS = SigningCodes::LOCK_SECONDS;

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

        // At most 3 signing codes every 15 minutes per proposal and per email address, whichever device asks
        if ($refused = SigningCodes::claimSend('proposal', $proposal->id, $this->signerEmail)) {
            $this->addError('signingCode', __('gateway.proposal.' . $refused[0], ['wait' => self::minutes($refused[1])]));
            return;
        }

        // Generate OTP
        $otp = SigningCodes::newCode();
        
        // PRE-SAVE: Secure the signature immediately so it survives F5 refreshes
        DB::table('proposals')->where('id', $proposal->id)->update([
            'auth_otp' => SigningCodes::hash($otp), // an HMAC: the code itself is only in the email
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
            // Never log the code itself: anyone who can read the log could sign. The unsent code is cancelled, so a
            // reload shows the signing form again (the signature stays saved) instead of asking for a code that never came.
            Log::error('Proposal signing code email failed', ['proposal_id' => $proposal->id, 'message' => $e->getMessage()]);
            DB::table('proposals')->where('id', $proposal->id)->update(['auth_otp' => null, 'otp_expires_at' => null]);
            $this->addError('signerEmail', __('gateway.proposal.email_failed'));
            $this->dispatch('reinit-canvas');
            return;
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

        // At most 5 wrong codes per proposal (from any device), then a 15-minute wait. The fifth wrong code also
        // cancels the code, so it can't be guessed after the wait: the signer asks for a new one.
        $refused = SigningCodes::check('proposal', $proposal->id, $proposal->auth_otp ? (string) $proposal->auth_otp : null, $proposal->otp_expires_at, (string) $this->otpInput);
        if ($refused) {
            if ($refused[0] === 'cancel') {
                // updated_at kept as it is: the proposal's validity runs from it
                DB::table('proposals')->where('id', $proposal->id)->update(['auth_otp' => null, 'otp_expires_at' => null, 'updated_at' => $proposal->updated_at]);
            }
            $this->addError('otpInput', $refused[0] === 'wrong' ? __('gateway.proposal.code_invalid') : __('gateway.proposal.too_many_attempts', ['wait' => self::minutes($refused[1])]));
            return;
        }

        $organization = DB::table('organizations')->where('id', $proposal->organization_id)->first();
        $ipAddress = Request::ip();
        $timestamp = now();
        
        $finalName = $proposal->signed_by_name; 
        
        // Generate the immutable SHA-256 seal
        $clientHash = 'SHA256-CLIENT-' . strtoupper(hash('sha256', $proposal->id . $finalName . $ipAddress . $timestamp));

        $signed = DB::transaction(function () use ($proposal, $organization, $ipAddress, $timestamp, $clientHash, $finalName) {

            // The opportunity's row first, as the SOW's signing does: a SOW signed meanwhile stops this one
            if ($proposal->opportunity_id) {
                DB::table('opportunities')->where('id', $proposal->opportunity_id)->lockForUpdate()->first();
                if (\App\Support\Opportunities\Sows::signed($proposal->opportunity_id)) {
                    return false;
                }
            }

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

            // The lead becomes a client (Active Client; the first signature sets since when and how: docs/LEADS.md)
            \App\Support\Leads\LeadStatus::markClientFromSigning($organization->id, 'proposal', $proposal->proposal_ref, $proposal->id, $timestamp);

            // 2. Create the project, led by the opportunity's lead (else the proposal's author: docs/PROJECTS.md 9)
            $projectId = (string) Str::uuid();
            $opportunityLead = $proposal->opportunity_id ? DB::table('opportunities')->where('id', $proposal->opportunity_id)->value('lead_user_id') : null;
            DB::table('projects')->insert([
                'id' => $projectId,
                'organization_id' => $organization->id,
                'opportunity_id' => $proposal->opportunity_id,
                'title' => $proposal->title,
                'description' => 'Created from signed proposal ' . $proposal->proposal_ref . '.',
                'status' => 'Scoping',
                'currency' => $proposal->currency,
                'total_budget' => $proposal->subtotal, // before VAT: Finance adds VAT per invoice (docs/PROJECTS.md 9)
                'lead_user_id' => $opportunityLead ?: $proposal->created_by,
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
                    $weight = ((float)($phase['weight'] ?? 1) / 4.0) * 100;
                    DB::table('project_phases')->insert([
                        'id' => (string) Str::uuid(),
                        'project_id' => $projectId,
                        'title' => current(explode(':', $phase['title'] ?? 'Phase ' . ($index + 1))),
                        'description' => ($phase['title'] ?? 'Phase') . ': scope as set out in the proposal.',
                        'phase_number' => $index + 1,
                        'weight_percentage' => $weight,
                        // The milestone's agreed amount (docs/PROJECTS.md 4), so Finance's pre-fill never drifts
                        'amount' => round((float) $proposal->subtotal * $weight / 100, 2),
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

            // 4. Invoice #001, the deposit, posted to the general ledger (App\Support\Finance\DepositInvoice)
            $invNumber = DepositInvoice::raise($projectId, $organization, [
                'subtotal' => $proposal->subtotal,
                // The company HQ signed it for, else the opportunity's billing company (App\Support\Finance\Vat)
                'billing_entity_id' => $proposal->billing_entity_id ?? DB::table('opportunities')->where('id', $proposal->opportunity_id)->value('billing_entity_id'),
                'currency' => $proposal->currency,
                'payment_schedule_type' => $proposal->payment_schedule_type,
                'custom_deposit_percent' => $proposal->custom_deposit_percent ?? null,
                'title' => $proposal->title,
                'reference' => $proposal->proposal_ref,
            ], $proposal->created_by, $timestamp)['number']; // attributed to the admin who drafted it

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

            // 7. The signer becomes the primary portal account and gets the setup link (App\Support\Portal\SignerOnboarding)
            SignerOnboarding::welcome($organization, $this->signerEmail, $proposal->title ?: $proposal->proposal_ref, $timestamp);

            return true;
        });

        if (! $signed) {
            $this->addError('otpInput', __('gateway.proposal.not_ready'));
            return;
        }

        $this->viewState = 'completed';
        $this->dispatch('reinit-deck');
    }

    /** Limiter keys for codes sent and wrong codes (SigningCodes); the email address is hashed so it isn't stored in the cache */
    public static function sendKey(string $proposalId): string
    {
        return SigningCodes::sendKey('proposal', $proposalId);
    }

    public static function sendKeyForEmail(string $email): string
    {
        return SigningCodes::sendKeyForEmail($email);
    }

    public static function checkKey(string $proposalId): string
    {
        return SigningCodes::checkKey('proposal', $proposalId);
    }

    /** "12 minutes" (in the page language): how long until the limiter lets the person try again */
    private static function minutes(int $minutes): string
    {
        return trans_choice('gateway.proposal.wait_minutes', $minutes, ['count' => $minutes]);
    }

    /**
     * Only a proposal HQ has signed and sent (status Pending Auth) can be signed by the client, and never once a
     * statement of work of its opportunity was signed (PR 5): one piece of work is signed once. A Won opportunity alone
     * doesn't stop it: the backfill put each organisation's earlier work into one opportunity, Won when any of it was.
     */
    private function awaitsClientSignature(object $proposal): bool
    {
        return $proposal->status === 'Pending Auth' && filled($proposal->hq_signature_hash)
            && ! ($proposal->opportunity_id && \App\Support\Opportunities\Sows::signed($proposal->opportunity_id));
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