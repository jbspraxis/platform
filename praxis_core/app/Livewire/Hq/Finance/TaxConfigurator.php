<?php

namespace App\Livewire\Hq\Finance;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Exception;

#[Layout('components.layouts.hq')]
class TaxConfigurator extends Component
{
    /** Countries a tax configuration can exist for (the region_code enum). */
    private const REGIONS = ['NG', 'CM', 'KE', 'GLOBAL'];

    /** Fields people may edit; anything else in $config is ignored on save. */
    private const EDITABLE = [
        'minimum_wage', 'pension_employee_rate', 'pension_employer_rate', 'pension_cap_amount',
        'health_employee_rate', 'health_employer_rate', 'housing_fund_rate', 'training_fund_rate',
        'fixed_abatement', 'proportional_abatement_rate', 'cra_fixed_amount', 'cra_proportional_rate',
        'minimum_tax_rate',
    ];

    public $activeRegion = 'NG'; // Default to Nigeria
    public $config = [];
    #[Locked]
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
        if (! in_array($region, self::REGIONS, true)) {
            return;
        }

        $this->activeRegion = $region;
        $this->loadConfiguration();
    }

    /**
     * Reads the configuration for the active country. Viewing never writes:
     * a country without a saved configuration shows an empty form, and the
     * row is created only when someone saves it or adds a tax band.
     */
    public function loadConfiguration()
    {
        $record = in_array($this->activeRegion, self::REGIONS, true)
            ? DB::table('tax_configurations')->where('region_code', $this->activeRegion)->first()
            : null;

        if ($record) {
            $this->config = (array) $record;
            $this->bands = DB::table('tax_bands')
                ->where('tax_configuration_id', $record->id)
                ->orderBy('lower_limit', 'asc')
                ->get()
                ->toArray();
            return;
        }

        // Not saved yet: an unsaved form with the database defaults
        $this->config = ['id' => null, 'region_code' => $this->activeRegion];
        foreach (self::EDITABLE as $field) {
            $this->config[$field] = $field === 'pension_cap_amount' ? null : '0';
        }
        $this->bands = [];
    }

    /** The saved configuration row for the active country, created on first save. */
    private function savedConfigurationId(): ?string
    {
        if (! in_array($this->activeRegion, self::REGIONS, true)) {
            return null;
        }

        $existing = DB::table('tax_configurations')->where('region_code', $this->activeRegion)->value('id');
        if ($existing) {
            return $existing;
        }

        $id = Str::uuid()->toString();
        DB::table('tax_configurations')->insert([
            'id' => $id,
            'region_code' => $this->activeRegion,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return $id;
    }

    public function saveConfiguration()
    {
        abort_unless(Auth::user()->can('payroll.edit'), 403);

        if (! in_array($this->activeRegion, self::REGIONS, true)) {
            session()->flash('error', "Choose a country before you save its tax rules.");
            return;
        }

        try {
            $before = DB::table('tax_configurations')->where('region_code', $this->activeRegion)->first();
            $updateData = [];
            foreach (self::EDITABLE as $field) {
                if (! array_key_exists($field, (array) $this->config)) {
                    continue;
                }
                $val = $this->config[$field];
                // Format numeric values and convert empty strings to null for DB integrity
                $updateData[$field] = ($val === '' || $val === null) ? null : (float) $val;
            }

            $updateData['updated_at'] = now();

            // Create the row on first save; roll it back if the values can’t be saved
            DB::transaction(function () use ($updateData, $before) {
                $configId = $this->savedConfigurationId();
                DB::table('tax_configurations')
                    ->where('id', $configId)
                    ->update($updateData + ['updated_by' => Auth::id()]);

                // Only the rates that changed, as they were and as they are now
                $old = $new = [];
                foreach (array_diff_key($updateData, ['updated_at' => true]) as $field => $value) {
                    $was = isset($before->$field) ? (float) $before->$field : null;
                    if ($was !== $value) {
                        $old[$field] = $was;
                        $new[$field] = $value;
                    }
                }
                $this->logTaxChange('TAX_RULES_SAVED', $configId, "Saved the tax rules for {$this->activeRegion}.", $before ? $old : null, $new);
            });

            $regionNames = ['NG' => 'Nigeria', 'CM' => 'Cameroon', 'KE' => 'Kenya', 'GLOBAL' => 'other countries'];
            $this->successMessage = "Tax rules for " . ($regionNames[$this->activeRegion] ?? $this->activeRegion) . " saved. The next payroll run uses them.";
            $this->showSuccessModal = true;
            $this->loadConfiguration();

        } catch (Exception $e) {
            report($e);
            session()->flash('error', "We couldn’t save the tax rules. Check that every field except the pension cap has a number, then save again.");
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
        ], [], [
            'newBandName' => 'band name',
            'newBandLower' => 'lower limit',
            'newBandUpper' => 'upper limit',
            'newBandRate' => 'tax rate',
        ]);

        // Adding a band is an explicit save, so the configuration row may be created here
        $configId = $this->savedConfigurationId();
        if (! $configId) {
            session()->flash('error', "Choose a country before you add a tax band.");
            return;
        }

        $bandId = Str::uuid()->toString();
        DB::table('tax_bands')->insert([
            'id' => $bandId,
            'tax_configuration_id' => $configId,
            'band_name' => $this->newBandName,
            'lower_limit' => $this->newBandLower,
            'upper_limit' => empty($this->newBandUpper) ? null : $this->newBandUpper,
            'tax_rate' => $this->newBandRate,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        $this->logTaxChange('TAX_BAND_ADDED', $configId, "Added the tax band ‘{$this->newBandName}’ for {$this->activeRegion}.", null, [
            'band_id' => $bandId, 'lower_limit' => $this->newBandLower, 'upper_limit' => empty($this->newBandUpper) ? null : $this->newBandUpper, 'tax_rate' => $this->newBandRate,
        ]);

        $this->reset(['newBandName', 'newBandLower', 'newBandUpper', 'newBandRate']);
        $this->loadConfiguration();
    }

    public function removeTaxBand($bandId)
    {
        abort_unless(Auth::user()->can('payroll.delete'), 403);

        // Only a band of the country on screen (the id comes from the browser)
        $band = DB::table('tax_bands')
            ->join('tax_configurations', 'tax_configurations.id', '=', 'tax_bands.tax_configuration_id')
            ->where('tax_bands.id', $bandId)
            ->where('tax_configurations.region_code', $this->activeRegion)
            ->select('tax_bands.*')
            ->first();

        if ($band) {
            DB::table('tax_bands')->where('id', $band->id)->delete();
            $this->logTaxChange('TAX_BAND_REMOVED', $band->tax_configuration_id, "Removed the tax band ‘{$band->band_name}’ for {$this->activeRegion}.", [
                'band_id' => $band->id, 'lower_limit' => $band->lower_limit, 'upper_limit' => $band->upper_limit, 'tax_rate' => $band->tax_rate,
            ], null);
        }
        $this->loadConfiguration();
    }

    /** Tax rules decide every payslip, so each change goes to the audit log */
    private function logTaxChange(string $action, string $configId, string $description, ?array $old, ?array $new): void
    {
        DB::table('system_audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => $description,
            'target_table' => 'tax_configurations',
            'target_id' => $configId,
            'old_values' => $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE),
            'new_values' => $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE),
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
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
