<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        ];

        if ($this->isDiagnosticMode) {
            $rules['stage'] = 'required|string';
            $rules['bottleneck'] = 'required|string';
            if ($this->bottleneck === 'other') {
                $rules['otherBottleneck'] = 'required|string';
            }
        }

        $this->validate($rules);

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

            // B. Find or Create User
            $existingUser = DB::table('users')->where('email', $emailStr)->first();
            
            if ($existingUser) {
                $userId = $existingUser->id; // Grab existing user ID for the CRM Note
            } else {
                $userId = (string) Str::uuid();
                DB::table('users')->insert([
                    'id' => $userId,
                    'organization_id' => $orgId, // Attaches correctly to new or existing Org
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $emailStr,
                    'password' => Hash::make(Str::random(16)), 
                    'phone' => $this->countryCode . ' ' . $this->phone,
                    'job_title' => $this->title,
                    'status' => 'Active',
                    'lifecycle_stage' => 'Lead Intake',
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
                'note_text' => $noteContent,
                'interaction_type' => 'Internal Note',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Note: AI Lead Scoring Job will be dispatched here later.
        });

        // 3. Trigger Success UI
        $this->isSuccess = true;
    }

    public function render()
    {
        return view('livewire.contact-hub')->layout('layouts.app', ['title' => 'JBS PRAXIS | The Conversion Hub']);
    }
}