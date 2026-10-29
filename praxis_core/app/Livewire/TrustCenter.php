<?php

namespace App\Livewire;

use Livewire\Component;

class TrustCenter extends Component
{
    public function render()
    {
        return view('livewire.trust-center')->layout('layouts.app', ['title' => 'JBS PRAXIS | Trust & Compliance Center']);
    }
}