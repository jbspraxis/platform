<?php

namespace App\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Component;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use App\Services\Site\CostReportService;
use App\Services\Site\Fields;
use App\Services\Site\LimitReached;
use App\Services\Site\SiteLimits;

class ConversionHub extends Component
{
    // Reference lists loaded on the server. Locked: bots used to overwrite them, which crashed the page.
    #[Locked]
    public $dbIndustries = [];
    #[Locked]
    public $dbArchitectures = [];
    #[Locked]
    public $dbCurrencies = [];
    #[Locked]
    public $dbBottlenecks = [];

    public function mount()
    {
        $reports = app(CostReportService::class);
        $this->dbIndustries = $reports->industries();
        $this->dbArchitectures = $reports->architectures();
        $this->dbCurrencies = $reports->currencies();
        $this->dbBottlenecks = $reports->bottlenecks();
    }

    public function render()
    {
        return view('livewire.conversion-hub')->layout('layouts.app');
    }

    public function generateCopqReport($data)
    {
        if (!is_array($data)) {
            return 'ERROR';
        }

        // The same rules as the site API (App\Services\Site\CostReportService), on the API names
        $input = Fields::fromArray($data, CostReportService::FIELDS);
        if (Validator::make($input, CostReportService::rules())->fails()) {
            return 'ERROR';
        }

        // Each report calls the AI and creates CRM records: the service's limits, shared with the site API
        try {
            return app(CostReportService::class)->generate($input, request()->ip());
        } catch (LimitReached $e) {
            return 'ERROR';
        }
    }

    public function dispatchReportToEmail($token)
    {
        // This page's own guard on its separate email button (per visitor), on top of the 3 emails a day per address
        $rateKey = 'copq-mail:' . SiteLimits::visitor(request()->ip());
        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            return false;
        }
        RateLimiter::hit($rateKey, 3600);

        return app(CostReportService::class)->emailReport(is_string($token) ? $token : '');
    }
}
