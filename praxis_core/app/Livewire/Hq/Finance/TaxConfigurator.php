<?php

namespace App\Livewire\Hq\Finance;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Exception;

#[Layout('components.layouts.hq')]
class TaxConfigurator extends Component
{
    public $activeRegion = 'NG'; // Default to Nigeria
    public $config = [];
    public $bands = [];
    public $showSuccessModal = false;
    public $successMessage = '';

    // Form state for creating a new progressive band
    public $newBandName = '';
    public $newBandLower = 0;
    public $newBandUpper = null;
    public $newBandRate = 0;

    public function mount()
    {
        $this->loadConfiguration();
    }

    public function setRegion($region)
    {
        $this->activeRegion = $region;
        $this->loadConfiguration();
    }

    public function loadConfiguration()
    {
        $record = DB::table('tax_configurations')->where('region_code', $this->activeRegion)->first();

        // Auto-initialize a blank configuration if the region is selected for the first time
        if (!$record) {
            $id = Str::uuid()->toString();
            DB::table('tax_configurations')->insert([
                'id' => $id,
                'region_code' => $this->activeRegion,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $record = DB::table('tax_configurations')->where('id', $id)->first();
        }

        $this->config = (array) $record;
        
        $this->bands = DB::table('tax_bands')
            ->where('tax_configuration_id', $this->config['id'])
            ->orderBy('lower_limit', 'asc')
            ->get()
            ->toArray();
    }

    public function saveConfiguration()
    {
        abort_unless(Auth::user()->can('payroll.edit'), 403);

        try {
            $updateData = $this->config;
            unset($updateData['id'], $updateData['region_code'], $updateData['created_at'], $updateData['updated_at']);
            
            // Format numeric values and convert empty strings to null for DB integrity
            foreach($updateData as $key => $val) {
                if ($val === '') {
                    $updateData[$key] = null;
                } else {
                    $updateData[$key] = (float) $val;
                }
            }

            $updateData['updated_at'] = now();

            DB::table('tax_configurations')
                ->where('id', $this->config['id'])
                ->update($updateData);

            $this->successMessage = "Statutory parameters for {$this->activeRegion} successfully updated in the master ledger.";
            $this->showSuccessModal = true;
            $this->loadConfiguration();

        } catch (Exception $e) {
            session()->flash('error', "Ledger Update Failed: " . $e->getMessage());
        }
    }

    public function addTaxBand()
    {
        abort_unless(Auth::user()->can('payroll.create'), 403);

        $this->validate([
            'newBandName' => 'required|string|max:100',
            'newBandLower' => 'required|numeric|min:0',
            'newBandUpper' => 'nullable|numeric|gt:newBandLower',
            'newBandRate' => 'required|numeric|min:0|max:1',
        ]);

        DB::table('tax_bands')->insert([
            'id' => Str::uuid()->toString(),
            'tax_configuration_id' => $this->config['id'],
            'band_name' => $this->newBandName,
            'lower_limit' => $this->newBandLower,
            'upper_limit' => empty($this->newBandUpper) ? null : $this->newBandUpper,
            'tax_rate' => $this->newBandRate,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->reset(['newBandName', 'newBandLower', 'newBandUpper', 'newBandRate']);
        $this->loadConfiguration();
    }

    public function removeTaxBand($bandId)
    {
        abort_unless(Auth::user()->can('payroll.delete'), 403);

        DB::table('tax_bands')->where('id', $bandId)->delete();
        $this->loadConfiguration();
    }

    public function closeSuccessModal()
    {
        $this->showSuccessModal = false;
    }

    public function render()
    {
        return view('livewire.hq.finance.tax-configurator');
    }
}