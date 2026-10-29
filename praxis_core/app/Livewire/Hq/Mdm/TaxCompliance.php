<?php

namespace App\Livewire\Hq\Mdm;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

#[Layout('components.layouts.hq')]
#[Title('Tax compliance')]
class TaxCompliance extends Component
{
    public $activeTab = 'tax'; // 'tax' or 'currency'
    
    // Collections
    public $taxes = [];
    public $currencies = [];
    
    // Tax Form State
    public $editMode = false;
    public $recordId = null;
    public $region_name = '';
    public $country_code = '';
    public $vat_rate = 0.00;
    public $tax_logic_type = 'Destination-based';
    public $exchange_rate_peg = 'USD';

    // Currency Form State
    public $currEditMode = false;
    public $currId = null;
    public $currency_code = '';
    public $currency_name = '';
    public $rate_to_usd = 1.000000;
    public $is_manual_override = 0;

    // UX State
    public $showSuccessModal = false;
    public $successMessage = '';

    public function mount()
    {
        $this->loadData();
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetForms();
        $this->loadData();
    }

    public function loadData()
    {
        if ($this->activeTab === 'tax') {
            $this->taxes = DB::table('mdm_tax_compliance')->orderBy('country_code')->get();
        } else {
            $this->currencies = DB::table('mdm_exchange_rates')->orderBy('currency_code')->get();
        }
    }

    // ==========================================
    // 1. TAX COMPLIANCE MANAGEMENT
    // ==========================================
    public function saveTax()
    {
        // The route needs mdm.view; adding or changing tax and currency data needs mdm.create / mdm.edit
        abort_unless(Auth::user()->can(($this->editMode && $this->recordId) ? 'mdm.edit' : 'mdm.create'), 403);

        $this->validate([
            'region_name' => 'required|string|max:100',
            'country_code' => 'required|string|max:10',
            'vat_rate' => 'required|numeric|min:0|max:100',
            'tax_logic_type' => 'required|in:Destination-based,Origin-based,Tax Exempt (B2B),Reverse Charge',
            'exchange_rate_peg' => 'required|string|max:10',
        ]);

        $data = [
            'region_name' => $this->region_name,
            'country_code' => strtoupper($this->country_code),
            'vat_rate' => $this->vat_rate,
            'tax_logic_type' => $this->tax_logic_type,
            'exchange_rate_peg' => strtoupper($this->exchange_rate_peg),
            'updated_at' => now(),
        ];

        if ($this->editMode && $this->recordId) {
            DB::table('mdm_tax_compliance')->where('id', $this->recordId)->update($data);
            $this->successMessage = "VAT rule updated.";
        } else {
            $data['is_active'] = 1;
            $data['created_at'] = now();
            DB::table('mdm_tax_compliance')->insert($data);
            $this->successMessage = "VAT rule added.";
        }

        $this->resetForms();
        $this->loadData();
        $this->showSuccessModal = true;
    }

    public function editTax($id)
    {
        $record = DB::table('mdm_tax_compliance')->where('id', $id)->first();
        if ($record) {
            $this->editMode = true; $this->recordId = $record->id;
            $this->region_name = $record->region_name; $this->country_code = $record->country_code;
            $this->vat_rate = $record->vat_rate; $this->tax_logic_type = $record->tax_logic_type;
            $this->exchange_rate_peg = $record->exchange_rate_peg;
        }
    }

    public function toggleTaxStatus($id, $currentStatus)
    {
        abort_unless(Auth::user()->can('mdm.edit'), 403);

        DB::table('mdm_tax_compliance')->where('id', $id)->update(['is_active' => !$currentStatus, 'updated_at' => now()]);
        $this->loadData();
    }

    // ==========================================
    // 2. GLOBAL CURRENCY & EXCHANGE RATES
    // ==========================================
    public function saveCurrency()
    {
        abort_unless(Auth::user()->can(($this->currEditMode && $this->currId) ? 'mdm.edit' : 'mdm.create'), 403);

        $this->validate([
            'currency_code' => 'required|string|max:10',
            'currency_name' => 'required|string|max:100',
            'rate_to_usd' => 'required|numeric|min:0.000001',
        ]);

        $data = [
            'currency_code' => strtoupper($this->currency_code),
            'currency_name' => $this->currency_name,
            'rate_to_usd' => $this->rate_to_usd,
            'is_manual_override' => $this->is_manual_override ? 1 : 0,
            'updated_at' => now(),
        ];

        if ($this->currEditMode && $this->currId) {
            DB::table('mdm_exchange_rates')->where('id', $this->currId)->update($data);
            $this->successMessage = "Exchange rate updated.";
        } else {
            if (DB::table('mdm_exchange_rates')->where('currency_code', strtoupper($this->currency_code))->exists()) {
                $this->addError('currency_code', 'That currency is already in the list. Edit its rate there instead.');
                return;
            }
            $data['created_at'] = now();
            DB::table('mdm_exchange_rates')->insert($data);
            $this->successMessage = "Currency added.";
        }

        $this->resetForms();
        $this->loadData();
        $this->showSuccessModal = true;
    }

    public function editCurrency($id)
    {
        $record = DB::table('mdm_exchange_rates')->where('id', $id)->first();
        if ($record) {
            $this->currEditMode = true; $this->currId = $record->id;
            $this->currency_code = $record->currency_code; $this->currency_name = $record->currency_name;
            $this->rate_to_usd = $record->rate_to_usd; $this->is_manual_override = $record->is_manual_override;
        }
    }

    public function syncRatesFromApi()
    {
        abort_unless(Auth::user()->can('mdm.edit'), 403);

        try {
            // Retrieve API key securely from config (set this in your .env)
            $apiKey = config('services.exchangerate.key'); 
            
            // Call the live API
            $response = Http::get("https://v6.exchangerate-api.com/v6/{$apiKey}/latest/USD");

            if ($response->successful()) {
                $data = $response->json();
                $rates = $data['conversion_rates'] ?? [];

                if (!empty($rates)) {
                    // Fetch only the currencies we actually track and are NOT manually overridden
                    $trackedCurrencies = DB::table('mdm_exchange_rates')
                        ->where('is_manual_override', 0)
                        ->pluck('currency_code');

                    // Batch update the rates for regional currencies (e.g., NGN, XAF, etc.)
                    foreach ($trackedCurrencies as $code) {
                        if (isset($rates[$code])) {
                            DB::table('mdm_exchange_rates')
                                ->where('currency_code', $code)
                                ->update([
                                    'rate_to_usd' => $rates[$code],
                                    'last_synced_at' => now(),
                                    'updated_at' => now()
                                ]);
                        }
                    }

                    $this->successMessage = "Exchange rates updated from the rate service. Fixed rates were not changed.";
                    $this->showSuccessModal = true;
                    $this->loadData();
                } else {
                    $this->addError('api_sync', "We couldn't update the rates because the rate service sent no rates. Try again later, or enter the rates by hand.");
                }
            } else {
                \Illuminate\Support\Facades\Log::warning('MDM Rate Sync Failed: HTTP ' . $response->status());
                $this->addError('api_sync', "We couldn't reach the exchange rate service. Try again in a few minutes, or enter the rates by hand.");
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('MDM Rate Sync Exception: ' . $e->getMessage());
            $this->addError('api_sync', "Something went wrong while updating the rates. Check the rates in the list, then try again or enter them by hand.");
        }
    }

    // ==========================================
    // UTILITIES
    // ==========================================
    public function resetForms()
    {
        $this->reset(['editMode', 'recordId', 'region_name', 'country_code', 'vat_rate', 'exchange_rate_peg']);
        $this->tax_logic_type = 'Destination-based';
        $this->reset(['currEditMode', 'currId', 'currency_code', 'currency_name', 'rate_to_usd', 'is_manual_override']);
    }

    public function closeSuccessModal()
    {
        $this->showSuccessModal = false;
    }

    public function render()
    {
        return view('livewire.hq.mdm.tax-compliance');
    }
}