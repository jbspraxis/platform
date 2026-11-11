<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\WithGatewayLanguage;
use App\Mail\SigningCode;
use App\Support\Access;
use App\Support\MailLocale;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Carbon\Carbon;
use Illuminate\Support\Str;

#[Layout('components.layouts.portal')]
class SignoffGateway extends Component
{
    use WithGatewayLanguage;

    // Locked: the browser cannot change which signoff this is, skip a step, or read the OTP.
    #[Locked]
    public $token;
    #[Locked]
    public $signoff;
    #[Locked]
    public $metrics = [];

    // UI State Management
    #[Locked]
    public $viewState = 'review'; // review, otp_verification, signature_canvas, completed
    public $otpInput = '';

    // Signature Capture State
    public $signerName = '';
    public $signerTitle = '';
    public $signerCompany = '';
    public $signatureBase64 = ''; // Holds the drawn or typed SVG/PNG data

    // Validation messages in the page language (lang/<locale>/gateway.php)
    protected function messages(): array
    {
        return (array) __('gateway.validation.signoff');
    }

    public function mount($token)
    {
        $this->token = $token;

        // The page speaks the organisation's language unless the person already chose one
        $this->useGatewayLanguage(DB::table('project_signoffs')
            ->join('projects', 'project_signoffs.project_id', '=', 'projects.id')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->where('project_signoffs.token', $token)
            ->value('organizations.preferred_language'));

        $this->loadDocument();
    }

    public function loadDocument()
    {
        $this->signoff = DB::table('project_signoffs')
            ->join('projects', 'project_signoffs.project_id', '=', 'projects.id')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->where('project_signoffs.token', $this->token)
            // Only what the page shows or signs: never the OTP, and not the signature images (up to 500 KB each),
            // which would travel with every request; the signed page reads the client's in render()
            ->select(
                'project_signoffs.id', 'project_signoffs.project_id', 'project_signoffs.token', 'project_signoffs.status',
                'project_signoffs.executive_summary', 'project_signoffs.roi_metrics_achieved',
                'project_signoffs.signatory_email', 'project_signoffs.signatory_name', 'project_signoffs.signatory_job_title',
                'project_signoffs.signatory_company', 'project_signoffs.signed_at', 'project_signoffs.client_ip_address',
                'project_signoffs.client_signature_hash',
                'projects.title as project_title',
                'organizations.name as client_name'
            )
            ->first();

        if (!$this->signoff) {
            abort(404, __('gateway.signoff.not_found'));
        }

        if ($this->isSealed()) {
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

    // Re-read from the database: a signed or frozen handover can never be signed again
    private function isSealed(): bool
    {
        $status = DB::table('project_signoffs')->where('id', $this->signoff->id)->value('status');
        return in_array($status, ['Signed', 'Frozen']);
    }

    private function otpSessionKey(): string
    {
        return 'signoff_otp_verified_' . $this->signoff->id;
    }

    public function triggerOtp()
    {
        if ($this->isSealed()) {
            $this->viewState = 'completed';
            return;
        }

        // At most 3 codes per signoff and IP every 15 minutes
        $limiterKey = 'signoff-otp-send:' . $this->signoff->id . ':' . request()->ip();
        if (RateLimiter::tooManyAttempts($limiterKey, 3)) {
            session()->flash('error', __('gateway.signoff.too_many_codes'));
            return;
        }
        RateLimiter::hit($limiterKey, 900);

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(10);

        // project_signoffs has no updated_at column (live schema), so none is written here
        DB::table('project_signoffs')->where('id', $this->signoff->id)->update([
            'auth_otp' => $otp,
            'otp_expires_at' => $expiresAt,
            'status' => 'Awaiting Signature',
        ]);

        $email = $this->signoff->signatory_email;

        // A language the signatory picked on the site wins, else the organisation's stored preference
        $language = DB::table('projects')->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->where('projects.id', $this->signoff->project_id)->value('organizations.preferred_language');

        try {
            Mail::to($email)->locale(session()->has('locale') ? MailLocale::forVisitor() : MailLocale::forRecipient($language))
                ->send(new SigningCode($otp, SigningCode::HANDOVER, $this->signoff->project_title));
            session()->flash('success', __('gateway.signoff.code_sent', ['email' => $email]));
        } catch (\Exception $e) {
            Log::error('Signoff OTP email failed', ['signoff_id' => $this->signoff->id, 'message' => $e->getMessage()]);
            session()->flash('error', __('gateway.signoff.email_failed'));
            return;
        }

        $this->viewState = 'otp_verification';
    }

    public function verifyOtp()
    {
        $this->validate([
            'otpInput' => 'required|string|min:6|max:6'
        ]);

        // At most 5 wrong codes per signoff and IP every 15 minutes
        $limiterKey = 'signoff-otp-check:' . $this->signoff->id . ':' . request()->ip();
        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            session()->flash('error', __('gateway.signoff.too_many_attempts'));
            return;
        }

        $currentData = DB::table('project_signoffs')->where('id', $this->signoff->id)->first();

        if (!$currentData->auth_otp || !hash_equals((string) $currentData->auth_otp, (string) $this->otpInput)) {
            RateLimiter::hit($limiterKey, 900);
            session()->flash('error', __('gateway.signoff.code_wrong'));
            return;
        }

        if (Carbon::parse($currentData->otp_expires_at)->isPast()) {
            session()->flash('error', __('gateway.signoff.code_expired'));
            $this->viewState = 'review';
            $this->otpInput = '';
            return;
        }

        // Single use: clear the code, and remember the verification on the server for 30 minutes
        DB::table('project_signoffs')->where('id', $this->signoff->id)->update(['auth_otp' => null]);
        session()->put($this->otpSessionKey(), now()->addMinutes(30)->timestamp);
        RateLimiter::clear($limiterKey);

        session()->flash('success', __('gateway.signoff.code_ok'));
        $this->viewState = 'signature_canvas';
    }

    // --- Signing: records the signature and its SHA-256 fingerprint ---
    public function bindContract()
    {
        if ($this->isSealed()) {
            $this->viewState = 'completed';
            return;
        }

        $verifiedUntil = session()->get($this->otpSessionKey());
        if (!$verifiedUntil || $verifiedUntil < now()->timestamp) {
            session()->flash('error', __('gateway.signoff.verify_first'));
            $this->viewState = 'review';
            return;
        }

        $this->validate([
            'signerName' => 'required|string|max:150',
            'signerTitle' => 'required|string|max:100',
            'signerCompany' => 'required|string|max:150',
            'signatureBase64' => 'required|string|max:500000'
        ]);

        // 1. Record where the signature came from
        $ipAddress = request()->ip();
        $userAgent = request()->userAgent();
        $timestamp = now();

        // 2. The signed content, as one string
        $payloadString = $this->signoff->executive_summary .
                         $this->signoff->roi_metrics_achieved .
                         $this->signatureBase64 .
                         $ipAddress .
                         $timestamp->toDateTimeString();

        // 3. SHA-256 Hash
        $hash = hash('sha256', $payloadString);

        // 4. Lock to Database (only if still unsigned)
        DB::transaction(function () use ($ipAddress, $userAgent, $hash, $timestamp) {
            $updated = DB::table('project_signoffs')
                ->where('id', $this->signoff->id)
                ->whereNotIn('status', ['Signed', 'Frozen'])
                ->update([
                    'signatory_name' => $this->signerName,
                    'signatory_job_title' => $this->signerTitle,
                    'signatory_company' => $this->signerCompany,
                    'client_signature_b64' => $this->signatureBase64,
                    'client_ip_address' => $ipAddress,
                    'client_user_agent' => $userAgent,
                    'client_signature_hash' => $hash,
                    'status' => 'Signed',
                    'signed_at' => $timestamp,
                ]);

            if (!$updated) {
                return;
            }

            // Tell the staff who approve sign-offs
            $notifications = [];
            foreach(Access::staffWith('signoffs.approve') as $adminId) {
                $notifications[] = [
                    'id' => (string) Str::uuid(), 'user_id' => $adminId,
                    'title' => 'Project handover signed off',
                    'message' => "{$this->signerName} signed off the handover of ‘{$this->signoff->project_title}’. The signed record is in Sign-offs.",
                    'type' => 'Project', 'created_at' => now()
                ];
            }
            if(!empty($notifications)) DB::table('notifications')->insert($notifications);
        });

        session()->forget($this->otpSessionKey());

        // 5. Update State (the drawn signature is stored now, so it stops travelling with each request)
        $this->signatureBase64 = '';
        $this->loadDocument(); // Refresh to pull the new status
        $this->viewState = 'completed';
        session()->flash('success', __('gateway.signoff.signed'));
    }

    public function render()
    {
        // The signature image appears only on the signed record's PDF page
        $signatureImage = $this->viewState === 'completed'
            ? DB::table('project_signoffs')->where('id', $this->signoff->id)->value('client_signature_b64')
            : null;

        return view('livewire.portal.signoff-gateway', ['signatureImage' => $signatureImage])
            ->title(__('gateway.signoff.page_title'));
    }
}
