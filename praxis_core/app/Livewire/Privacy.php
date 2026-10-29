<?php

namespace App\Livewire;

use Livewire\Component;

class Privacy extends Component
{
    public function render()
    {
        return view('livewire.privacy')->layout('layouts.app', ['title' => 'JBS PRAXIS | Data Sovereignty']);
    }
}