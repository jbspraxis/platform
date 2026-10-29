<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Careers extends Component
{
    use WithFileUploads;

    // 1. Intelligence State
    public $fullName;
    public $email;
    public $countryCode = '+234';
    public $phone;
    public $city;
    
    public $primaryWeapon = '';
    public $otherWeapon;
    public $secondarySkillset;
    public $linkedinUrl;
    public $portfolioUrl;
    public $praxisAnswer;
    
    // 2. Asset State
    public $resume;

    // 3. System State
    public $isSuccess = false;

    public function transmitDossier()
    {
        // 1. Strict Validation Matrix
        $this->validate([
            'fullName'          => 'required|string|max:100',
            'email'             => 'required|email|max:100',
            'countryCode'       => 'required|string|max:10',
            'phone'             => 'required|string|max:20',
            'city'              => 'required|string|max:255', // Updated to match new DB schema
            'primaryWeapon'     => 'required|string',
            'otherWeapon'       => 'required_if:primaryWeapon,other|max:100',
            'secondarySkillset' => 'required|string|max:255',
            'linkedinUrl'       => 'required|url|max:255',
            'portfolioUrl'      => 'nullable|url|max:255',
            'praxisAnswer'      => 'required|string', // Removed max:1000 since DB is type TEXT
            'resume'            => 'required|file|mimes:pdf,doc,docx|max:5120', 
        ], [
            'resume.required'   => 'ASSET UPLOAD REQUIRED: You must attach a dossier (PDF/DOC).',
            'phone.required'    => 'COMM CHANNEL REQUIRED: Enter a valid phone number.',
            'city.required'     => 'GEO-LOCATION REQUIRED: Select your current city.',
            'otherWeapon.required_if' => 'SPECIFICATION REQUIRED: Define your unlisted discipline.',
        ]);

        // 2. Data Normalization
        $finalWeapon = $this->primaryWeapon === 'other' ? $this->otherWeapon : $this->primaryWeapon;
        $formattedPhone = $this->countryCode . ' ' . ltrim($this->phone, '0');

        // 3. Secure Database Transaction
        DB::transaction(function () use ($finalWeapon, $formattedPhone) {
            $talentId = Str::uuid();

            // Write to Talent Pool
            DB::table('talent_pool')->insert([
                'id'                 => $talentId,
                'full_name'          => $this->fullName,
                'email'              => $this->email,
                'phone'              => $formattedPhone,
                'city'               => $this->city,
                'primary_weapon'     => $finalWeapon,
                'secondary_skillset' => $this->secondarySkillset,
                'linkedin_url'       => $this->linkedinUrl,
                'portfolio_url'      => $this->portfolioUrl,
                'praxis_answer'      => $this->praxisAnswer,
                'status'             => 'Pending', // Matches your exact ENUM
                // created_by, updated_by, deleted_by remain NULL as this is a public form
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            // 4. Secure Asset Processing
            if ($this->resume) {
                // EXCTRACT METADATA FIRST (Before the file is moved)
                $originalName = $this->resume->getClientOriginalName();
                $mimeType = $this->resume->getMimeType();
                $fileSize = $this->resume->getSize();

                // NOW MOVE THE FILE
                $path = $this->resume->store('vault/dossiers', 'local');
                
                // WRITE TO DATABASE
                DB::table('media_assets')->insert([
                    'id'          => Str::uuid(),
                    'talent_id'   => $talentId, 
                    'file_name'   => $originalName,
                    'file_path'   => $path,
                    'mime_type'   => $mimeType,
                    'size_bytes'  => $fileSize,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        });

        // 5. System Reset & Trigger Cinematic UI Reveal
        $this->resetExcept('isSuccess');
        $this->isSuccess = true;
        
        // Tells the frontend to trigger the smooth scroll & reveal animation
        $this->dispatch('dossier-transmitted'); 
    }

    public function render()
    {
        return view('livewire.careers')->layout('layouts.app', ['title' => 'JBS PRAXIS | Join the Architecture']);
    }
}