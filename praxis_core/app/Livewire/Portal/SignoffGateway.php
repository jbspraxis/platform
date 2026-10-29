<?php

namespace App\Livewire\Portal;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Illuminate\Support\Str;

#[Layout('components.layouts.portal')]
class SignoffGateway extends Component
{
    public $token;
    public $signoff;
    public $metrics = [];
    
    // UI State Management
    public $viewState = 'review'; // review, otp_verification, signature_canvas, completed
    public $otpInput = '';

    // Signature Capture State
    public $signerName = '';
    public $signerTitle = '';
    public $signerCompany = '';
    public $signatureBase64 = ''; // Holds the drawn or typed SVG/PNG data

    public function mount($token)
    {
        $this->token = $token;
        $this->loadDocument();
    }

    public function loadDocument()
    {
        $this->signoff = DB::table('project_signoffs')
            ->join('projects', 'project_signoffs.project_id', '=', 'projects.id')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->where('project_signoffs.token', $this->token)
            ->select(
                'project_signoffs.*', 
                'projects.title as project_title', 
                'organizations.name as client_name'
            )
            ->first();

        if (!$this->signoff) {
            abort(404, 'CRYPTOGRAPHIC MISMATCH: Secure Link Invalid or Expired.');
        }

        if (in_array($this->signoff->status, ['Signed', 'Frozen'])) {
            $this->viewState = 'completed';
        }

        if ($this->signoff->roi_metrics_achieved) {
            $this->metrics = json_decode($this->signoff->roi_metrics_achieved, true) ?? [];
        }

        // Pre-fill if we already have it
        $this->signerName = $this->signoff->signatory_name ?? '';
        $this->signerTitle = $this->signoff->signatory_job_title ?? '';
        $this->signerCompany = $this->signoff->signatory_company ?? $this->signoff->client_name;
    }

    public function triggerOtp()
    {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(10);

        DB::table('project_signoffs')->where('id', $this->signoff->id)->update([
            'auth_otp' => $otp,
            'otp_expires_at' => $expiresAt,
            'status' => 'Awaiting Signature',
            'updated_at' => now()
        ]);

        $email = $this->signoff->signatory_email;
        
        try {
            Mail::raw("Your highly secure Authorization Code for the JBS Praxis Project Handover is: {$otp}\n\nThis code expires strictly in 10 minutes. Do not share this code.", function ($message) use ($email) {
                $message->to($email)
                        ->subject('JBS PRAXIS: Secure Signoff Authorization Code');
            });
            session()->flash('success', "SECURE OTP DISPATCHED TO: {$email}");
        } catch (\Exception $e) {
            session()->flash('error', "MAIL SERVER ERROR: Could not dispatch email. (Development OTP: {$otp})");
        }

        $this->viewState = 'otp_verification';
    }

    public function verifyOtp()
    {
        $this->validate([
            'otpInput' => 'required|string|min:6|max:6'
        ]);

        $currentData = DB::table('project_signoffs')->where('id', $this->signoff->id)->first();

        if ($currentData->auth_otp !== $this->otpInput) {
            session()->flash('error', 'AUTHENTICATION FAILED: Invalid OTP Code.');
            return;
        }

        if (Carbon::parse($currentData->otp_expires_at)->isPast()) {
            session()->flash('error', 'SESSION TIMEOUT: The 10-minute security window has expired. Please request a new OTP.');
            $this->viewState = 'review';
            $this->otpInput = '';
            return;
        }

        session()->flash('success', 'IDENTITY VERIFIED. CRYPTOGRAPHIC CANVAS UNLOCKED.');
        $this->viewState = 'signature_canvas';
    }

    // --- NEW: THE CRYPTOGRAPHIC BINDING ---
    public function bindContract()
    {
        $this->validate([
            'signerName' => 'required|string|max:150',
            'signerTitle' => 'required|string|max:100',
            'signerCompany' => 'required|string|max:150',
            'signatureBase64' => 'required|string'
        ]);

        // 1. Capture Telemetry
        $ipAddress = request()->ip();
        $userAgent = request()->userAgent();
        $timestamp = now();

        // 2. Generate the Immutable Payload String
        $payloadString = $this->signoff->executive_summary . 
                         $this->signoff->roi_metrics_achieved . 
                         $this->signatureBase64 . 
                         $ipAddress . 
                         $timestamp->toDateTimeString();

        // 3. SHA-256 Hash
        $hash = hash('sha256', $payloadString);

        // 4. Lock to Database
        DB::transaction(function () use ($ipAddress, $userAgent, $hash, $timestamp) {
            DB::table('project_signoffs')->where('id', $this->signoff->id)->update([
                'signatory_name' => $this->signerName,
                'signatory_job_title' => $this->signerTitle,
                'signatory_company' => $this->signerCompany,
                'client_signature_b64' => $this->signatureBase64,
                'client_ip_address' => $ipAddress,
                'client_user_agent' => $userAgent,
                'client_signature_hash' => $hash,
                'status' => 'Signed',
                'signed_at' => $timestamp,
                'updated_at' => $timestamp
            ]);

            // Notify Command Team of successful signoff
            $admins = DB::table('users')->join('roles', 'users.role_id', '=', 'roles.id')
                        ->where('roles.clearance_level', '>=', 4)->select('users.id')->get();
            
            $notifications = [];
            foreach($admins as $admin) {
                $notifications[] = [
                    'id' => (string) Str::uuid(), 'user_id' => $admin->id,
                    'title' => 'CRYPTOGRAPHIC SIGNOFF SECURED', 
                    'message' => "Project '{$this->signoff->project_title}' has been legally signed and hashed by {$this->signerName}.",
                    'type' => 'Project', 'created_at' => now()
                ];
            }
            if(!empty($notifications)) DB::table('notifications')->insert($notifications);
        });

        // 5. Update State
        $this->loadDocument(); // Refresh to pull the new status
        $this->viewState = 'completed';
        session()->flash('success', 'CONTRACT MATHEMATICALLY BOUND AND HASHED.');
    }

    public function render()
    {
        return view('livewire.portal.signoff-gateway');
    }
}