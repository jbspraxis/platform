<?php

namespace App\Livewire\Hq\Hr;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

#[Layout('components.layouts.hq')]
class SignatureCommand extends Component
{
    public $personnel = [];

    public function mount()
    {
        // Fetch active personnel from our new MDM architecture
        $this->personnel = DB::table('mdm_employees as emp')
            ->join('users as u', 'emp.user_id', '=', 'u.id')
            ->where('emp.is_active', 1)
            ->select(
                'u.first_name', 
                'u.last_name', 
                'u.email as company_email', 
                'u.phone',
                'emp.job_classification as job_title'
            )
            ->orderBy('u.first_name', 'asc')
            ->get()
            ->toArray();
    }

    public function render()
    {
        return view('livewire.hq.hr.signature-command');
    }
}