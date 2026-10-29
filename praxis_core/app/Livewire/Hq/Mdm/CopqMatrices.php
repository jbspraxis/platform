<?php

namespace App\Livewire\Hq\Mdm;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

#[Layout('components.layouts.hq')]
class CopqMatrices extends Component
{
    public $activeTab = 'industries'; // 'industries', 'architectures', or 'bottlenecks'

    // Data Collections
    public $industries = [];
    public $architectures = [];
    public $bottlenecks = [];

    // Modal States
    public $showIndModal = false;
    public $showArchModal = false;
    public $showBnModal = false;

    // --- INDUSTRY FORM STATE ---
    public $indEditMode = false;
    public $indId = null;
    public $industry_name = '';
    public $error_floor = 1.00;
    public $compliance_standard = '';
    public $risk_type = '';
    public $opportunity_focus = '';
    public $value_term = 'capital';
    public $multiplier_term = 'revenue multiplier';
    public $organization_type = 'enterprises';
    public $deficit_context = '';
    public $erosion_target = '';
    public $fallback_diagnostic = '';

    // --- ARCHITECTURE FORM STATE ---
    public $archEditMode = false;
    public $archId = null;
    public $tech_key = '';
    public $display_name = '';
    public $symptom_title = '';
    public $intervention_strategy = '';
    public $error_multiplier = 1.00;

    // --- BOTTLENECK FORM STATE ---
    public $bnEditMode = false;
    public $bnId = null;
    public $bottleneck_name = '';
    public $appraisal_multiplier = 1.00;
    public $failure_multiplier = 1.00;
    public $opportunity_multiplier = 1.00;
    public $risk_weight = 10;
    public $bottleneck_narrative = '';

    public function mount()
    {
        $this->loadData();
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->loadData();
    }

    public function loadData()
    {
        if ($this->activeTab === 'industries') {
            $this->industries = DB::table('mdm_copq_industries')->orderBy('industry_name')->get();
        } elseif ($this->activeTab === 'architectures') {
            $this->architectures = DB::table('mdm_copq_architectures')->orderBy('tech_key')->get();
        } else {
            $this->bottlenecks = DB::table('mdm_copq_bottlenecks')->orderBy('bottleneck_name')->get();
        }
    }

    // ==========================================
    // 1. INDUSTRY MANAGEMENT
    // ==========================================
    public function openIndModal($id = null)
    {
        $this->resetIndForm();
        if ($id) {
            $record = DB::table('mdm_copq_industries')->where('id', $id)->first();
            if ($record) {
                $this->indEditMode = true;
                $this->indId = $record->id;
                $this->industry_name = $record->industry_name;
                $this->error_floor = $record->error_floor;
                $this->compliance_standard = $record->compliance_standard;
                $this->risk_type = $record->risk_type;
                $this->opportunity_focus = $record->opportunity_focus;
                $this->value_term = $record->value_term;
                $this->multiplier_term = $record->multiplier_term;
                $this->organization_type = $record->organization_type;
                $this->deficit_context = $record->deficit_context;
                $this->erosion_target = $record->erosion_target;
                $this->fallback_diagnostic = $record->fallback_diagnostic;
            }
        }
        $this->showIndModal = true;
    }

    public function saveIndustry()
    {
        // The route needs mdm.view; adding or changing a matrix row needs mdm.create / mdm.edit
        abort_unless(Auth::user()->can(($this->indEditMode && $this->indId) ? 'mdm.edit' : 'mdm.create'), 403);

        $this->validate([
            'industry_name' => 'required|string|max:100',
            'error_floor' => 'required|numeric|min:0.1',
            'compliance_standard' => 'required|string|max:150',
            'risk_type' => 'required|string|max:200',
            'opportunity_focus' => 'required|string|max:200',
            'value_term' => 'required|string|max:50',
            'multiplier_term' => 'required|string|max:50',
            'organization_type' => 'required|string|max:50',
            'deficit_context' => 'required|string|max:200',
            'erosion_target' => 'required|string|max:100',
        ]);

        $data = [
            'industry_name' => $this->industry_name, 'error_floor' => $this->error_floor,
            'compliance_standard' => $this->compliance_standard, 'risk_type' => $this->risk_type,
            'opportunity_focus' => $this->opportunity_focus, 'value_term' => $this->value_term,
            'multiplier_term' => $this->multiplier_term, 'organization_type' => $this->organization_type,
            'deficit_context' => $this->deficit_context, 'erosion_target' => $this->erosion_target,
            'fallback_diagnostic' => $this->fallback_diagnostic, 'updated_at' => now()
        ];

        if ($this->indEditMode && $this->indId) {
            DB::table('mdm_copq_industries')->where('id', $this->indId)->update($data);
        } else {
            $data['created_at'] = now();
            DB::table('mdm_copq_industries')->insert($data);
        }

        $this->showIndModal = false;
        $this->loadData();
    }

    public function resetIndForm()
    {
        $this->reset(['indEditMode', 'indId', 'industry_name', 'error_floor', 'compliance_standard', 'risk_type', 'opportunity_focus', 'deficit_context', 'erosion_target', 'fallback_diagnostic']);
        $this->value_term = 'capital'; $this->multiplier_term = 'revenue multiplier'; $this->organization_type = 'enterprises';
    }

    // ==========================================
    // 2. ARCHITECTURE MANAGEMENT
    // ==========================================
    public function openArchModal($id = null)
    {
        $this->resetArchForm();
        if ($id) {
            $record = DB::table('mdm_copq_architectures')->where('id', $id)->first();
            if ($record) {
                $this->archEditMode = true; $this->archId = $record->id;
                $this->tech_key = $record->tech_key; $this->display_name = $record->display_name;
                $this->symptom_title = $record->symptom_title; $this->intervention_strategy = $record->intervention_strategy;
                $this->error_multiplier = $record->error_multiplier;
            }
        }
        $this->showArchModal = true;
    }

    public function saveArchitecture()
    {
        abort_unless(Auth::user()->can(($this->archEditMode && $this->archId) ? 'mdm.edit' : 'mdm.create'), 403);

        $this->validate([
            'tech_key' => 'required|string|max:50', 'display_name' => 'required|string|max:150',
            'symptom_title' => 'required|string|max:150', 'intervention_strategy' => 'required|string',
            'error_multiplier' => 'required|numeric|min:0.1',
        ]);

        $data = [
            'tech_key' => strtolower($this->tech_key), 'display_name' => $this->display_name,
            'symptom_title' => $this->symptom_title, 'intervention_strategy' => $this->intervention_strategy,
            'error_multiplier' => $this->error_multiplier, 'updated_at' => now()
        ];

        if ($this->archEditMode && $this->archId) {
            DB::table('mdm_copq_architectures')->where('id', $this->archId)->update($data);
        } else {
            $data['created_at'] = now();
            DB::table('mdm_copq_architectures')->insert($data);
        }

        $this->showArchModal = false;
        $this->loadData();
    }

    public function resetArchForm()
    {
        $this->reset(['archEditMode', 'archId', 'tech_key', 'display_name', 'symptom_title', 'intervention_strategy']);
        $this->error_multiplier = 1.00;
    }

    // ==========================================
    // 3. BOTTLENECK MANAGEMENT
    // ==========================================
    public function openBnModal($id = null)
    {
        $this->resetBnForm();
        if ($id) {
            $record = DB::table('mdm_copq_bottlenecks')->where('id', $id)->first();
            if ($record) {
                $this->bnEditMode = true; $this->bnId = $record->id;
                $this->bottleneck_name = $record->bottleneck_name; $this->appraisal_multiplier = $record->appraisal_multiplier;
                $this->failure_multiplier = $record->failure_multiplier; $this->opportunity_multiplier = $record->opportunity_multiplier;
                $this->risk_weight = $record->risk_weight; $this->bottleneck_narrative = $record->bottleneck_narrative;
            }
        }
        $this->showBnModal = true;
    }

    public function saveBottleneck()
    {
        abort_unless(Auth::user()->can(($this->bnEditMode && $this->bnId) ? 'mdm.edit' : 'mdm.create'), 403);

        $this->validate([
            'bottleneck_name' => 'required|string|max:100', 'appraisal_multiplier' => 'required|numeric|min:0.1',
            'failure_multiplier' => 'required|numeric|min:0.1', 'opportunity_multiplier' => 'required|numeric|min:0.1',
            'risk_weight' => 'required|integer|min:1|max:100', 'bottleneck_narrative' => 'required|string'
        ]);

        $data = [
            'bottleneck_name' => $this->bottleneck_name, 'appraisal_multiplier' => $this->appraisal_multiplier,
            'failure_multiplier' => $this->failure_multiplier, 'opportunity_multiplier' => $this->opportunity_multiplier,
            'risk_weight' => $this->risk_weight, 'bottleneck_narrative' => $this->bottleneck_narrative, 'updated_at' => now()
        ];

        if ($this->bnEditMode && $this->bnId) {
            DB::table('mdm_copq_bottlenecks')->where('id', $this->bnId)->update($data);
        } else {
            $data['created_at'] = now();
            DB::table('mdm_copq_bottlenecks')->insert($data);
        }

        $this->showBnModal = false;
        $this->loadData();
    }

    public function resetBnForm()
    {
        $this->reset(['bnEditMode', 'bnId', 'bottleneck_name', 'bottleneck_narrative']);
        $this->appraisal_multiplier = 1.00; $this->failure_multiplier = 1.00; $this->opportunity_multiplier = 1.00; $this->risk_weight = 10;
    }

    public function render()
    {
        return view('livewire.hq.mdm.copq-matrices');
    }
}