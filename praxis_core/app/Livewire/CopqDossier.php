<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CopqDossier extends Component
{
    public $token;
    public $assessment;
    public $isExecMode = false;

    public function mount($token, Request $request)
    {
        $this->token = $token;
        if ($request->query('exec') === 'true') {
            $this->isExecMode = true;
        }
        
        $this->assessment = DB::table('copq_assessments')->where('report_token', $this->token)->first();

        if (!$this->assessment) {
            abort(404, 'Dossier not found or clearance revoked.');
        }
    }

    public function render()
    {
        return view('livewire.copq-dossier')->layout('layouts.app'); 
    }
}