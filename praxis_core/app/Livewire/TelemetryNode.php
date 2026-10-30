<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\Site\FeedbackService;
use App\Services\Site\LimitReached;

class TelemetryNode extends Component
{
    // These match your wire:models exactly
    public $identity = '';
    public $email = '';
    public $category = '';
    public $severity = 1; // Defaulting to Level 1
    public $description = '';
    
    // UI State
    public $isSuccess = false;

    // Validation Rules: the same as the site API (App\Services\Site\FeedbackService)
    protected function rules()
    {
        return FeedbackService::rules();
    }

    public function transmitTelemetry()
    {
        // 1. Validate the incoming data
        $validatedData = $this->validate();

        // 2. Store the report (the limits are the service's, shared with the site API)
        try {
            app(FeedbackService::class)->submit([
                'identity'    => $this->identity,
                'email'       => $this->email,
                'category'    => $this->category,
                'severity'    => $this->severity,
                'description' => $this->description,
            ], request()->ip());
        } catch (LimitReached $e) {
            $this->addError('description', 'Too many reports from this connection. Please try again later.');
            return;
        }

        // 3. Trigger the Success UI panel
        $this->isSuccess = true;
    }

    public function render()
    {
        return view('livewire.telemetry-node');
    }
}