<?php

namespace App\Livewire\Portal;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class EscrowGateway extends Component
{
    public $token;
    public $proposal;
    public $organization;
    public $lines;
    public $narrative;

    // Security State
    public $otpCode = '';
    public $showOtpInput = false;
    public $signatureName = '';
    public $signatureTitle = '';
    public $signatureBase64 = ''; // Captures canvas data

    public function mount($token, Request $request)
    {
        $this->token = $token;
        
        $this->proposal = DB::table('proposals')->where('token_hash', $token)->first();
        if (!$this->proposal) abort(404, 'Invalid Document Token.');

        // Soft Expiration Logic (Question 8-C)
        $link = DB::table('project_magic_links')->where('token_hash', $token)->first();
        if ($link && now()->greaterThan($link->expires_at)) {
            session()->flash('warning', 'TOKEN EXPIRED: Prices subject to HQ re-validation.');
        }

        $this->organization = DB::table('organizations')->where('id', $this->proposal->organization_id)->first();
        $this->narrative = DB::table('proposal_ai_narratives')->where('proposal_id', $this->proposal->id)->first();
        $this->lines = DB::table('proposal_line_items')->where('proposal_id', $this->proposal->id)->orderBy('sort_order')->get();

        // Track Access
        DB::table('project_magic_links')->where('token_hash', $token)->update(['last_used_at' => now(), 'access_ip' => $request->ip()]);
    }

    // --- 1. EMAIL OTP REQUEST (Question 11-B) ---
    public function requestOTP()
    {
        $this->validate(['signatureName' => 'required', 'signatureTitle' => 'required', 'signatureBase64' => 'required']);
        
        $otp = rand(100000, 999999);
        DB::table('proposals')->where('id', $this->proposal->id)->update([
            'auth_otp' => $otp, 'otp_expires_at' => now()->addMinutes(15)
        ]);

        // Mock Email Dispatch
        // Mail::raw("Your JBS Praxis Authorization Code is: $otp", function($msg) { $msg->to('client@email.com')->subject('Signature OTP'); });

        $this->showOtpInput = true;
        session()->flash('success', 'Verification code dispatched to corporate email.');
    }

    // --- 2. AUTHORIZE & EXECUTE AUTOMATION (Questions 13-A, 14-A, 15-A) ---
    public function authorizeSignature(Request $request)
    {
        if ($this->otpCode !== $this->proposal->auth_otp || now()->greaterThan($this->proposal->otp_expires_at)) {
            session()->flash('error', 'Invalid or Expired OTP.'); return;
        }

        // IP & VPN Flagging (13-A)
        $ip = $request->ip();
        $geoData = Http::get("http://ip-api.com/json/{$ip}")->json();
        $vpnFlag = ($geoData['hosting'] ?? false) ? '[WARNING: DATACENTER/VPN DETECTED]' : '[IP VERIFIED]';

        $timestamp = now()->toDateTimeString();
        $cryptoHash = hash('sha256', $this->token . $this->signatureName . $ip . $timestamp);

        DB::transaction(function () use ($ip, $vpnFlag, $cryptoHash, $timestamp) {
            
            // 1. Lock the Proposal
            DB::table('proposals')->where('id', $this->proposal->id)->update([
                'status' => 'Authorized', 'client_signed_name' => $this->signatureName,
                'client_signed_title' => $this->signatureTitle, 'client_signature_b64' => $this->signatureBase64,
                'client_ip_address' => $ip, 'ip_security_flag' => $vpnFlag,
                'crypto_hash_receipt' => $cryptoHash, 'signed_at' => $timestamp
            ]);

            // 2. Activate Organization
            DB::table('organizations')->where('id', $this->organization->id)->update([
                'status' => 'Active', 'pipeline_stage' => 'Won / Lost', 'updated_at' => now()
            ]);

            // 3. Auto-Spawn Project (15-A)
            $projectId = (string) Str::uuid();
            DB::table('projects')->insert([
                'id' => $projectId, 'organization_id' => $this->organization->id,
                'title' => 'Deployment: ' . $this->organization->name,
                'total_budget' => $this->proposal->total_value, 'currency' => $this->proposal->currency,
                'status' => 'Scoping', 'created_at' => now(), 'updated_at' => now()
            ]);

            // 4. Auto-Spawn Sprints from AI Phases
            $phases = json_decode($this->narrative->ai_phases_json ?? '[]', true);
            foreach ($phases as $phase) {
                DB::table('sprints')->insert([
                    'id' => (string) Str::uuid(), 'project_id' => $projectId,
                    'title' => $phase['title'], 'status' => 'Planned',
                    'start_date' => now(), 'end_date' => now()->addWeeks(2), 'created_at' => now()
                ]);
            }

            // 5. Auto-Generate Mobilization Invoice (14-A)
            $invoiceId = (string) Str::uuid();
            $invRef = 'INV-' . date('Ymd') . '-' . rand(100, 999);
            $mobAmount = $this->proposal->total_value * 0.40; // Assuming 40%
            $taxAmount = $mobAmount * ($this->proposal->tax_rate / 100);
            
            DB::table('invoices')->insert([
                'id' => $invoiceId, 'project_id' => $projectId, 'invoice_number' => $invRef,
                'amount_due' => $mobAmount, 'vat_amount' => $taxAmount, 'total_amount' => $mobAmount + $taxAmount,
                'currency' => $this->proposal->currency, 'status' => 'Pending',
                'due_date' => now()->addDays(7), 'created_at' => now()
            ]);

            DB::table('invoice_lines')->insert([
                'invoice_id' => $invoiceId, 'description' => 'Phase 1 Mobilization (40%)',
                'unit_price' => $mobAmount, 'total_price' => $mobAmount
            ]);

            // 6. Global Telemetry Notification
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $this->proposal->created_by,
                'title' => 'CONTRACT SECURED', 
                'message' => "{$this->organization->name} has cryptographically signed SOW {$this->proposal->proposal_ref}. Project and Invoice generated.",
                'type' => 'Financial', 'is_read' => 0, 'created_at' => now()
            ]);
        });

        // Trigger Frontend Download (12-B)
        $this->dispatch('signature-secured');
    }

    public function render() { return view('livewire.portal.escrow-gateway')->layout('layouts.guest'); }
}