<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Services\Site\CareersService;
use App\Services\Site\Fields;
use App\Services\Site\LimitReached;

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
        // 1. Validation: the same rules as the site API (App\Services\Site\CareersService), with this page's messages
        $this->validate(
            Fields::rulesFor(CareersService::rules(), CareersService::FIELDS),
            Fields::messagesFor(CareersService::messages(), CareersService::FIELDS)
        );

        // 2. Save the application and store the CV on the private disk (a repeat from the same email updates it);
        //    the limits are the service's, shared with the site API
        try {
            app(CareersService::class)->submit(Fields::fromComponent($this, CareersService::FIELDS), $this->resume ?: null, request()->ip());
        } catch (LimitReached $e) {
            $this->addError('email', 'You’ve sent several applications today. Try again tomorrow, or email info@jbspraxis.com.');
            return;
        }

        // 3. System Reset & Trigger Cinematic UI Reveal
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