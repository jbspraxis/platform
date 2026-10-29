<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\KaizenTelemetry;

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

    // Validation Rules
    protected $rules = [
        'identity'    => 'nullable|string|max:255',
        'email'       => 'nullable|email|max:255',
        'category'    => 'required|string|max:255',
        'severity'    => 'required|integer|in:1,2,3',
        'description' => 'required|string|min:10|max:5000',
    ];

    public function transmitTelemetry()
    {
        // 1. Validate the incoming data
        $validatedData = $this->validate();

        // 5 reports per IP per hour
        $rateKey = 'feedback:' . request()->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rateKey, 5)) {
            $this->addError('description', 'Too many reports from this connection. Please try again later.');
            return;
        }
        \Illuminate\Support\Facades\RateLimiter::hit($rateKey, 3600);

        // 2. Store the data in the PostgreSQL Vault (Database)
        KaizenTelemetry::create([
            'identity'    => $this->identity,
            'email'       => $this->email,
            'category'    => $this->category,
            'severity'    => $this->severity,
            'description' => $this->description,
        ]);

        // 3. Trigger the Success UI panel
        $this->isSuccess = true;
    }

    public function render()
    {
        return view('livewire.telemetry-node');
    }
}