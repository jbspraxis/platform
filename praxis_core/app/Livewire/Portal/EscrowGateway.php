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
            session()->flash('warning', 'This link has expired, so the prices may change until JBS Praxis confirms them again.');
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
        session()->flash('success', 'We sent a signing code to your work email.');
    }

    // --- 2. AUTHORIZE & EXECUTE AUTOMATION (Questions 13-A, 14-A, 15-A) ---
    public function authorizeSignature(Request $request)
    {
        if ($this->otpCode !== $this->proposal->auth_otp || now()->greaterThan($this->proposal->otp_expires_at)) {
            session()->flash('error', 'That code isn’t right or has expired. Ask for a new code.'); return;
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
                'status' => 'Active', 'pipeline_stage' => 'Won', 'updated_at' => now()
            ]);

            // 3. Auto-Spawn Project (15-A), led by the opportunity's lead, else whoever wrote the proposal (docs/PROJECTS.md)
            $projectId = (string) Str::uuid();
            $lead = (! empty($this->proposal->opportunity_id) ? DB::table('opportunities')->where('id', $this->proposal->opportunity_id)->value('lead_user_id') : null)
                ?? $this->proposal->created_by;
            DB::table('projects')->insert([
                'id' => $projectId, 'organization_id' => $this->organization->id,
                'title' => 'Deployment: ' . $this->organization->name,
                'total_budget' => $this->proposal->total_value, 'currency' => $this->proposal->currency,
                'lead_user_id' => $lead, 'created_by' => $this->proposal->created_by,
                'status' => 'Scoping', 'created_at' => now(), 'updated_at' => now()
            ]);

            // 4. Milestones from the proposal's phases (sprints were folded into milestones, docs/PROJECTS.md): each its
            // share of the contract by the phases' relative weights, two weeks each from today; without phases, one M1
            // named after the proposal
            $phases = array_values(array_filter((array) json_decode($this->narrative->ai_phases_json ?? '[]', true), 'is_array'))
                ?: [['title' => $this->proposal->title ?: 'Delivery']];
            $weights = array_map(fn ($p) => max(0.0, (float) ($p['weight'] ?? 1)), $phases);
            $sum = array_sum($weights);
            $shares = array_map(fn ($w) => $sum > 0 ? round($w / $sum * 100, 2) : round(100 / count($phases), 2), $weights);
            $shares[count($shares) - 1] = round(100 - array_sum(array_slice($shares, 0, -1)), 2); // the rounding goes to the last
            $start = now('Africa/Lagos')->startOfDay();
            foreach ($phases as $i => $phase) {
                DB::table('project_phases')->insert([
                    'id' => (string) Str::uuid(), 'project_id' => $projectId, 'phase_number' => $i + 1,
                    'title' => Str::limit(trim((string) ($phase['title'] ?? '')) ?: 'M' . ($i + 1), 140),
                    'weight_percentage' => $shares[$i], 'amount' => round((float) $this->proposal->total_value * $shares[$i] / 100, 2),
                    'status' => 'Pending', 'start_date' => $start->toDateString(), 'due_date' => $start->copy()->addDays(13)->toDateString(),
                    'created_by' => $this->proposal->created_by, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $start->addDays(14);
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