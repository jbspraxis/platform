<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\Site\EnquiryService;
use App\Services\Site\Fields;
use App\Services\Site\LimitReached;

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

    public function mount()
    {
        // The language field starts on the page language (it is hidden in quick mode), so a French visitor gets French email
        $this->language = app()->getLocale() === 'fr' ? 'Français (FR)' : 'English (EN)';
    }

    public function toggleMode()
    {
        $this->isDiagnosticMode = !$this->isDiagnosticMode;
    }

    public function transmitIntelligence()
    {
        // 1. Strict Validation (the same rules as the site API: App\Services\Site\EnquiryService)
        $this->validate(Fields::rulesFor(EnquiryService::rules((bool) $this->isDiagnosticMode, $this->bottleneck), EnquiryService::FIELDS));

        // 2. Save (organisation, contact, CRM note, HQ notification) and send the two emails. The limits
        //    (per visitor, per email, everyone) are the service's, shared with the site API
        try {
            app(EnquiryService::class)->submit(Fields::fromComponent($this, EnquiryService::FIELDS), (bool) $this->isDiagnosticMode, request()->ip());
        } catch (LimitReached $e) {
            $this->addError('email', 'Too many messages from this connection. Please try again later or email info@jbspraxis.com.');
            return;
        }

        // 3. Trigger Success UI
        $this->isSuccess = true;
    }

    public function render()
    {
        return view('livewire.contact-hub')->layout('layouts.app', ['title' => 'JBS PRAXIS | The Conversion Hub']);
    }
}