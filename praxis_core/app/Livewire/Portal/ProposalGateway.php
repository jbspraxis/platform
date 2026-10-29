<?php

namespace App\Livewire\Portal;

use App\Support\Access;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

#[Layout('components.layouts.portal')]
class ProposalGateway extends Component
{
    public $token;
    
    // Only interactive inputs remain in public state for security and speed
    public $signerName = '';
    public $signerTitle = '';
    public $signerEmail = '';
    public $signatureBase64 = ''; 
    public $otpInput = '';
    public $viewState = 'review'; // review, otp_verification, completed

    public function mount($token)
    {
        $this->token = $token;
        
        $proposal = DB::table('proposals')->where('token_hash', $this->token)->first();
        if (!$proposal) {
            abort(404, 'CRYPTOGRAPHIC MISMATCH: Secure Link Invalid or Expired.');
        }

        // Temporal Expiration Check
        $expirationDate = Carbon::parse($proposal->updated_at)->addDays($proposal->validity_days);
        if (now()->greaterThan($expirationDate) && !in_array($proposal->status, ['Authorized', 'Converted'])) {
            DB::table('proposals')->where('id', $proposal->id)->update(['status' => 'Expired']);
            abort(403, 'SOW EXPIRED: The validity window for this document has closed. Contact Praxis Command.');
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

        // Vector Person Validation (Strict Email Matching against active nodes)
        $vectorPerson = DB::table('client_users')
            ->where('organization_id', $proposal->organization_id)
            ->where('email', $this->signerEmail)
            ->whereNull('deleted_at')
            ->first();

        if (!$vectorPerson) {
            $this->addError('signerEmail', '> REJECTED EMAIL: Kindly use a JBS recognized signatory email for this enterprise.');
            
            // Dispatch event to re-initialize canvas just in case the DOM shifted
            $this->dispatch('reinit-canvas'); 
            return;
        }

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

        // Dispatch Email
        try {
            Mail::raw("JBS Praxis Command Matrix\n\nYour OTP for cryptographic SOW authorization is: {$otp}\n\nThis code expires in 10 minutes. Do not share this code.", function ($message) {
                $message->to($this->signerEmail)->subject('JBS PRAXIS: Cryptographic Authorization OTP');
            });
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

        // Validate OTP
        if ($proposal->auth_otp !== $this->otpInput || now()->greaterThan($proposal->otp_expires_at)) {
            $this->addError('otpInput', '> AUTHENTICATION FAILED: Invalid or expired OTP.');
            return;
        }

        $organization = DB::table('organizations')->where('id', $proposal->organization_id)->first();
        $ipAddress = Request::ip();
        $timestamp = now();
        
        $finalName = $proposal->signed_by_name; 
        
        // Generate the immutable SHA-256 seal
        $clientHash = 'SHA256-CLIENT-' . strtoupper(hash('sha256', $proposal->id . $finalName . $ipAddress . $timestamp));

        DB::transaction(function () use ($proposal, $organization, $ipAddress, $timestamp, $clientHash, $finalName) {
            
            // 1. Lock the SOW (OTP cleared, Hash applied)
            DB::table('proposals')->where('id', $proposal->id)->update([
                'status' => 'Authorized', 
                'client_signature_hash' => $clientHash,
                'client_ip_address' => $ipAddress,
                'signed_at' => $timestamp,
                'auth_otp' => null,
                'otp_expires_at' => null,
                'updated_at' => $timestamp
            ]);

            DB::table('organizations')->where('id', $organization->id)->update(['status' => 'Active Client', 'updated_at' => $timestamp]);

            // 2. Instantiate Project
            $projectId = (string) Str::uuid();
            DB::table('projects')->insert([
                'id' => $projectId,
                'organization_id' => $organization->id,
                'title' => $proposal->title,
                'description' => 'Automatically generated from SOW: ' . $proposal->proposal_ref,
                'status' => 'Scoping', 
                'currency' => $proposal->currency,
                'total_budget' => $proposal->total_value,
                'created_by' => $proposal->created_by,
                'created_at' => $timestamp,
                'updated_at' => $timestamp
            ]);

            DB::table('proposals')->where('id', $proposal->id)->update(['project_id' => $projectId]);

            // 3. Inject Phases & Budgets
            $narrative = DB::table('proposal_ai_narratives')->where('proposal_id', $proposal->id)->first();
            $phases = $narrative ? json_decode($narrative->ai_phases_json, true) : [];

            if ($phases && is_array($phases)) {
                foreach ($phases as $index => $phase) {
                    DB::table('project_phases')->insert([
                        'id' => (string) Str::uuid(), 
                        'project_id' => $projectId,
                        'title' => current(explode(':', $phase['title'] ?? 'Phase ' . ($index + 1))), 
                        'description' => ($phase['title'] ?? 'Phase') . ' Scope defined in SOW.',
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

            // Immediate 10-Day Guillotine Trigger
            $dueDate = now()->addDays(10);
            
            // Cryptographic Sealing for the Invoice
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
                'description' => "Project Initiation Deposit ({$depositPercentage}%) - " . $proposal->title,
                'metric_of_billing' => 'Fixed Milestone', 
                'quantity' => 1, 
                'unit_price' => $depositSubtotal, 
                'total_price' => $depositSubtotal
            ]);

            // 5. TRIGGER THE IFRS GENERAL LEDGER ENGINE
            \App\Livewire\Hq\Finance\GeneralLedgerManager::recordInvoiceIssued($invoiceId);

            // 6. Notify Command Matrix: the sales team (deal won) and finance (deposit invoice issued)
            $recipients = array_unique(array_merge(Access::staffWith('crm.edit'), Access::staffWith('finance.approve')));
            $notifications = [];
            foreach($recipients as $adminId) {
                $notifications[] = [
                    'id' => (string) Str::uuid(), 'user_id' => $adminId,
                    'title' => 'SOW SECURED & INVOICE ISSUED', 
                    'message' => "Client {$organization->name} bound SOW {$proposal->proposal_ref}. Deposit Invoice {$invNumber} generated and routed to ledger.", 
                    'type' => 'Project', 'created_at' => $timestamp
                ];
            }
            if(!empty($notifications)) DB::table('notifications')->insert($notifications);

            // 7. Client Account Elevation & Magic Link Dispatch
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
                try {
                    Mail::raw("JBS Praxis OS Initialization.\n\nYour SOW is cryptographically secured. Initialize your key to access your Executive Dashboard and Invoice #001:\n\n{$setupUrl}", function ($message) use ($clientUser) {
                        $message->to($clientUser->email)->subject('JBS PRAXIS: Action Required - Initialize Node');
                    });
                } catch (\Exception $e) { 
                    Log::info("Praxis Setup URL for {$clientUser->email}: {$setupUrl}");
                }
            }
        });

        $this->viewState = 'completed';
        $this->dispatch('reinit-deck');
    }

    public function render()
    {
        // Zero-Payload Rendering Engine (Queries run fresh on every render)
        $proposal = DB::table('proposals')->where('token_hash', $this->token)->first();
        $organization = $proposal ? DB::table('organizations')->where('id', $proposal->organization_id)->first() : null;
        $narrative = $proposal ? DB::table('proposal_ai_narratives')->where('proposal_id', $proposal->id)->first() : null;
        $lineItems = $proposal ? DB::table('proposal_line_items')->where('proposal_id', $proposal->id)->orderBy('sort_order')->get() : [];
        $phases = $narrative ? (json_decode($narrative->ai_phases_json, true) ?? []) : [];
        
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
        ]);
    }
}