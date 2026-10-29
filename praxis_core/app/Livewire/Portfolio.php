<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\PortfolioCaseStudy;
use App\Models\PortfolioQuote;

class Portfolio extends Component
{
    public function render()
    {
        // 1. Fetch ONLY published case studies, maintaining structural order
        $caseStudies = PortfolioCaseStudy::where('is_published', true)
            ->orderBy('created_at', 'asc') 
            ->get();

        // 2. Fetch active quotes and randomize them for dynamic page loads
        $quotes = PortfolioQuote::where('is_active', true)
            ->inRandomOrder()
            ->get();

        return view('livewire.portfolio', [
            'caseStudies' => $caseStudies,
            'quotes' => $quotes
        ])->layout('layouts.app', ['title' => 'JBS PRAXIS | Sanitized Portfolio']);
    }
}