<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\WithGatewayLanguage;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;

#[Layout('components.layouts.portal')]
class ContractVerification extends Component
{
    use WithGatewayLanguage;

    // Locked: set on the server only, so the browser cannot show a document as checked when it is not
    #[Locked]
    public $hash;
    #[Locked]
    public $document;
    #[Locked]
    public $verificationState = 'scanning'; // scanning, valid, invalid

    public function mount($hash)
    {
        $this->hash = $hash;

        // The page speaks the language of the organisation that signed, unless the person already chose one
        $this->useGatewayLanguage(DB::table('project_signoffs')
            ->join('projects', 'project_signoffs.project_id', '=', 'projects.id')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->where('project_signoffs.client_signature_hash', $hash)
            ->value('organizations.preferred_language'));

        // The check itself runs right after the page loads (executeVerificationScan)
    }

    public function executeVerificationScan()
    {
        // Query the database for the exact SHA-256 hash
        $this->document = DB::table('project_signoffs')
            ->join('projects', 'project_signoffs.project_id', '=', 'projects.id')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->where('project_signoffs.client_signature_hash', $this->hash)
            ->whereIn('project_signoffs.status', ['Signed', 'Frozen'])
            ->select(
                'project_signoffs.signed_at',
                'project_signoffs.client_ip_address',
                'project_signoffs.signatory_name',
                'project_signoffs.signatory_job_title',
                'project_signoffs.signatory_company',
                'project_signoffs.client_signature_hash',
                'projects.title as project_title',
                'organizations.name as client_name'
            )
            ->first();

        if ($this->document) {
            $this->verificationState = 'valid';
        } else {
            $this->verificationState = 'invalid';
        }
    }

    public function render()
    {
        return view('livewire.portal.contract-verification')->title(__('gateway.verify.page_title'));
    }
}
