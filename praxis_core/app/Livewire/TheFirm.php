<?php

namespace App\Livewire;

use Livewire\Component;

class TheFirm extends Component
{
    public function render()
    {
        return view('livewire.the-firm')->layout('layouts.app', ['title' => 'JBS PRAXIS | The Firm']);
    }
}