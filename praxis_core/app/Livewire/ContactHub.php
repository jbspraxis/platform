<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ContactHub extends Component
{
    // Master Toggle
    public $isDiagnosticMode = true;

    // Part 1: Diagnostic Specifics
    public $stage;
    public $bottleneck;
    public $otherBottleneck;
    public $techReality;
    public $govStatus;

    // Part 2: Executive Credentials
    public $fullName;
    public $email;
    public $title;
    public $countryCode = '+234';
    public $phone;
    public $city;
    public $companyName;
    public $language = 'English (EN)';
    public $budget;
    public $message;

    // UI State
    public $isSuccess = false;

    public function toggleMode()
    {
        $this->isDiagnosticMode = !$this->isDiagnosticMode;
    }

    public function transmitIntelligence()
    {
        // 1. Strict Validation
        $rules = [
            'fullName' => 'required|string|max:100',
            'email' => 'required|email|max:100',
            'companyName' => 'required|string|max:150',
            'message' => 'required|string|max:2000',
            'title' => 'nullable|string|max:100',
            'countryCode' => 'nullable|string|max:6',
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
            'language' => 'nullable|string|max:50',
            'budget' => 'nullable|string|max:100',
        ];

        if ($this->isDiagnosticMode) {
            $rules['stage'] = 'required|string|max:100';
            $rules['bottleneck'] = 'required|string|max:100';
            $rules['techReality'] = 'nullable|string|max:100';
            $rules['govStatus'] = 'nullable|string|max:100';
            if ($this->bottleneck === 'other') {
                $rules['otherBottleneck'] = 'required|string|max:255';
            }
        }

        $this->validate($rules);

        // 5 enquiries per IP per hour
        $rateKey = 'contact-form:' . request()->ip();
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $this->addError('email', 'Too many messages from this connection. Please try again later or email info@jbspraxis.com.');
            return;
        }
        RateLimiter::hit($rateKey, 3600);

        // 2. Database Transaction (All or Nothing)
        DB::transaction(function () {
            
            // Extract & Clean Data
            $nameParts = explode(' ', trim($this->fullName), 2);
            $firstName = $nameParts[0];
            $lastName = $nameParts[1] ?? 'Unknown';
            $cleanOrgName = trim($this->companyName);
            $emailStr = strtolower(trim($this->email));
            
            $finalBottleneck = $this->bottleneck === 'other' ? $this->otherBottleneck : $this->bottleneck;

            // A. Find or Create Organization (Case-Insensitive Lock)
            $existingOrg = DB::table('organizations')->whereRaw('LOWER(name) = ?', [strtolower($cleanOrgName)])->first();

            if ($existingOrg) {
                $orgId = $existingOrg->id;
                DB::table('organizations')->where('id', $orgId)->update([
                    'is_duplicate_suspect' => 1,
                    'updated_at' => now()
                ]);
            } else {
                $orgId = (string) Str::uuid();
                DB::table('organizations')->insert([
                    'id' => $orgId,
                    'name' => $cleanOrgName,
                    'status' => 'Lead',
                    'pipeline_stage' => 'Lead Captured',
                    'country' => $this->countryCode,
                    'city' => $this->city,
                    'preferred_language' => $this->language,
                    'budget_estimation' => $this->budget,
                    'enterprise_stage' => $this->isDiagnosticMode ? $this->stage : 'Not Provided',
                    'tech_reality' => $this->isDiagnosticMode ? $this->techReality : 'Not Provided',
                    'governance_status' => $this->isDiagnosticMode ? $this->govStatus : 'Not Provided',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // B. Find or create the contact (a lead: client account with no password, so no portal access)
            $existingUser = DB::table('client_users')->where('email', $emailStr)->first();

            if ($existingUser) {
                $userId = $existingUser->id; // Grab existing user ID for the CRM Note
            } else {
                $userId = (string) Str::uuid();
                DB::table('client_users')->insert([
                    'id' => $userId,
                    'organization_id' => $orgId, // Attaches correctly to new or existing Org
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $emailStr,
                    'phone' => $this->countryCode . ' ' . $this->phone,
                    'job_title' => $this->title,
                    'status' => 'Active',
                    'lifecycle_stage' => 'Lead',
                    'source' => 'contact_form',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // C. Log the exact message and bottleneck as the first CRM Note
            $noteContent = ">> INTAKE MESSAGE:\n" . $this->message;
            if ($this->isDiagnosticMode) {
                $noteContent .= "\n\n>> IDENTIFIED BOTTLENECK:\n" . $finalBottleneck;
            }

            DB::table('crm_notes')->insert([
                'id' => (string) Str::uuid(),
                'organization_id' => $orgId,
                'author_id' => $userId, // Attributing the note securely to the correct user
                'author_type' => 'client',
                'note_text' => $noteContent,
                'interaction_type' => 'Internal Note',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Note: AI Lead Scoring Job will be dispatched here later.

            // D. HQ notification, as the booking form does
            $admins = DB::table('users')->join('roles', 'users.role_id', '=', 'roles.id')->where('roles.clearance_level', '>=', 4)->select('users.id')->get();
            $notifications = [];
            foreach ($admins as $admin) {
                $notifications[] = [
                    'id' => (string) Str::uuid(), 'user_id' => $admin->id,
                    'title' => 'NEW WEBSITE ENQUIRY',
                    'message' => "{$this->fullName} ({$cleanOrgName}) sent a message through the contact form.",
                    'type' => 'System', 'created_at' => now(),
                ];
            }
            if (!empty($notifications)) DB::table('notifications')->insert($notifications);
        });

        // 3. Emails: confirmation to the visitor, alert to the team (failures are logged, the enquiry is already saved)
        $this->sendEnquiryEmails();

        // 4. Trigger Success UI
        $this->isSuccess = true;
    }

    private function sendEnquiryEmails(): void
    {
        $firstName = explode(' ', trim($this->fullName), 2)[0];
        $visitor = strtolower(trim($this->email));
        $team = config('services.leads.alert_email');

        try {
            Mail::raw(
                "Hello {$firstName},\n\n"
                . "Thank you for contacting JBS Praxis. We have received your message and a member of our team will get back to you shortly.\n\n"
                . "If you would like to choose a time now, book a free audit & demo here:\n" . url('/schedule') . "\n\n"
                . "JBS Praxis\ninfo@jbspraxis.com\n" . url('/'),
                function ($m) use ($visitor) {
                    $m->to($visitor)->subject('We have received your message | JBS Praxis');
                }
            );
        } catch (\Throwable $e) {
            Log::error('Contact form: confirmation email failed', ['message' => $e->getMessage()]);
        }

        if (!$team) {
            return;
        }

        $finalBottleneck = $this->bottleneck === 'other' ? $this->otherBottleneck : $this->bottleneck;
        $lines = [
            'Name' => $this->fullName,
            'Email' => $visitor,
            'Job title' => $this->title,
            'Phone' => trim(($this->countryCode ?? '') . ' ' . ($this->phone ?? '')),
            'Company' => $this->companyName,
            'City' => $this->city,
            'Language' => $this->language,
            'Budget' => $this->budget,
            'Stage' => $this->isDiagnosticMode ? $this->stage : null,
            'Main bottleneck' => $this->isDiagnosticMode ? $finalBottleneck : null,
            'Current systems' => $this->isDiagnosticMode ? $this->techReality : null,
            'Governance' => $this->isDiagnosticMode ? $this->govStatus : null,
        ];
        $body = "New enquiry from the jbspraxis.com contact form.\n\n";
        foreach ($lines as $label => $value) {
            if (filled($value)) $body .= "{$label}: {$value}\n";
        }
        $body .= "\nMessage:\n{$this->message}\n\nReply directly to this email to answer the visitor.";

        try {
            Mail::raw($body, function ($m) use ($team, $visitor) {
                $m->to($team)->replyTo($visitor)->subject('New website enquiry: ' . trim($this->companyName));
            });
        } catch (\Throwable $e) {
            Log::error('Contact form: team alert email failed', ['message' => $e->getMessage()]);
        }
    }

    public function render()
    {
        return view('livewire.contact-hub')->layout('layouts.app', ['title' => 'JBS PRAXIS | The Conversion Hub']);
    }
}