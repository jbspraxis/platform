<?php

namespace App\Livewire\Hq\Mdm;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

#[Layout('components.layouts.hq')]
#[Title('Legal entities')]
class LegalEntityRegistry extends Component
{
    public $entities = [];
    
    // --- UX STATE ---
    public $editMode = false;
    public $recordId = null;
    public $showSuccessModal = false;
    public $successMessage = '';

    // --- FORM FIELDS ---
    public $region_code = '';
    public $legal_name = '';
    public $registration_number = '';
    public $tax_identification_number = '';
    public $employer_social_security_code = '';
    public $registered_address = '';
    public $contact_email = '';
    public $contact_phone = '';
    public $legal_representative_name = '';
    public $legal_representative_title = '';

    public function mount()
    {
        $this->loadEntities();
    }

    /**
     * Loads the entities with active headcount and monthly pay per country.
     */
    public function loadEntities()
    {
        $rawEntities = DB::table('mdm_legal_entities')->orderBy('region_code', 'asc')->get();

        $this->entities = $rawEntities->map(function ($entity) {
            // Aggregating Human Capital Metrics for this specific region
            $hrMetrics = DB::table('mdm_employees')
                ->where('tax_region', $entity->region_code)
                ->where('is_active', 1)
                ->selectRaw('
                    COUNT(id) as active_headcount, 
                    SUM(base_salary + allowance_housing + allowance_transport + allowance_utility) as monthly_liability,
                    MAX(currency) as local_currency
                ')
                ->first();

            $entity->active_headcount = $hrMetrics->active_headcount ?? 0;
            $entity->monthly_liability = $hrMetrics->monthly_liability ?? 0.00;
            $entity->local_currency = $hrMetrics->local_currency ?? 'USD';

            return $entity;
        })->toArray();
    }

    public function saveEntity()
    {
        // The route needs mdm.view; adding or changing an entity needs mdm.create / mdm.edit
        abort_unless(Auth::user()->can(($this->editMode && $this->recordId) ? 'mdm.edit' : 'mdm.create'), 403);

        sleep(1); // UX buffer

        $this->validate([
            'region_code' => 'required|string|max:10',
            'legal_name' => 'required|string|max:150',
            'registration_number' => 'required|string|max:100',
            'tax_identification_number' => 'required|string|max:100',
            'employer_social_security_code' => 'required|string|max:100',
            'registered_address' => 'required|string',
            'legal_representative_name' => 'required|string|max:100',
            'legal_representative_title' => 'required|string|max:100',
        ]);

        try {
            $data = [
                'region_code' => strtoupper($this->region_code),
                'legal_name' => $this->legal_name,
                'registration_number' => $this->registration_number,
                'tax_identification_number' => $this->tax_identification_number,
                'employer_social_security_code' => $this->employer_social_security_code,
                'registered_address' => $this->registered_address,
                'contact_email' => $this->contact_email,
                'contact_phone' => $this->contact_phone,
                'legal_representative_name' => $this->legal_representative_name,
                'legal_representative_title' => $this->legal_representative_title,
                'updated_at' => now(),
            ];

            if ($this->editMode && $this->recordId) {
                // Prevent changing region code to one that already exists on another record
                $exists = DB::table('mdm_legal_entities')
                    ->where('region_code', $data['region_code'])
                    ->where('id', '!=', $this->recordId)
                    ->exists();
                
                if ($exists) throw new Exception("Country code {$data['region_code']} already belongs to another legal entity. Edit that entity instead.");

                DB::table('mdm_legal_entities')->where('id', $this->recordId)->update($data);
                $this->successMessage = "{$this->legal_name} updated.";
            } else {
                $exists = DB::table('mdm_legal_entities')->where('region_code', $data['region_code'])->exists();
                if ($exists) throw new Exception("There is already a legal entity for {$data['region_code']}. Edit it in the list instead of adding a new one.");

                $data['id'] = Str::uuid()->toString();
                $data['is_active'] = 1;
                $data['created_at'] = now();
                
                DB::table('mdm_legal_entities')->insert($data);
                $this->successMessage = "{$this->legal_name} added to the legal entities.";
            }

            $this->showSuccessModal = true;
            $this->loadEntities();

        } catch (Exception $e) {
            $this->addError('form_error', $e->getMessage());
        }
    }

    public function editEntity($id)
    {
        $record = DB::table('mdm_legal_entities')->where('id', $id)->first();
        if ($record) {
            $this->editMode = true;
            $this->recordId = $record->id;
            
            $this->region_code = $record->region_code;
            $this->legal_name = $record->legal_name;
            $this->registration_number = $record->registration_number;
            $this->tax_identification_number = $record->tax_identification_number;
            $this->employer_social_security_code = $record->employer_social_security_code;
            $this->registered_address = $record->registered_address;
            $this->contact_email = $record->contact_email;
            $this->contact_phone = $record->contact_phone;
            $this->legal_representative_name = $record->legal_representative_name;
            $this->legal_representative_title = $record->legal_representative_title;
        }
    }

    public function toggleStatus($id, $currentStatus)
    {
        abort_unless(Auth::user()->can('mdm.edit'), 403);

        DB::table('mdm_legal_entities')->where('id', $id)->update([
            'is_active' => !$currentStatus, 
            'updated_at' => now()
        ]);
        $this->loadEntities();
    }

    public function resetForm()
    {
        $this->reset([
            'editMode', 'recordId', 'region_code', 'legal_name', 'registration_number', 
            'tax_identification_number', 'employer_social_security_code', 'registered_address', 
            'contact_email', 'contact_phone', 'legal_representative_name', 'legal_representative_title'
        ]);
    }

    public function closeSuccessModal()
    {
        $this->showSuccessModal = false;
        $this->resetForm();
    }

    /** Field names in validation messages: the labels people see, not the property names (docs/BRAND.md sections 5 and 12) */
    protected function validationAttributes(): array
    {
        return [
            'legal_name' => 'registered company name',
            'region_code' => 'country',
            'registration_number' => 'company registration number (RC)',
            'tax_identification_number' => 'tax ID (NIU or TIN)',
            'employer_social_security_code' => 'employer social security number',
            'registered_address' => 'registered office address',
            'legal_representative_name' => 'legal representative’s name',
            'legal_representative_title' => 'legal representative’s job title',
        ];
    }

    public function render()
    {
        return view('livewire.hq.mdm.legal-entity-registry');
    }
}