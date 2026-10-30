<?php

namespace App\Services\Site;

use App\Models\PortfolioCaseStudy;
use App\Models\PortfolioQuote;

/**
 * Published case studies and active quotes for GET /portfolio, as the old /portfolio page lists them:
 * case studies oldest first, quotes in random order. No internal ids.
 */
final class PortfolioService
{
    public function payload(): array
    {
        $caseStudies = PortfolioCaseStudy::where('is_published', true)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn ($s) => [
                'client_pseudonym' => $s->client_pseudonym,
                'title' => $s->title,
                'industry' => $s->industry,
                'challenge' => $s->challenge,
                'audit' => $s->audit,
                'architecture_deployed' => $s->architecture_deployed,
                'roi_metrics' => [
                    'metric' => $s->roi_metrics['metric'] ?? null,
                    'description' => $s->roi_metrics['description'] ?? null,
                    'tags' => array_values((array) ($s->roi_metrics['tags'] ?? [])),
                ],
            ])->values()->all();

        $quotes = PortfolioQuote::where('is_active', true)
            ->inRandomOrder()
            ->get()
            ->map(fn ($q) => ['quote' => $q->quote, 'source' => $q->source])
            ->values()->all();

        return ['case_studies' => $caseStudies, 'quotes' => $quotes];
    }
}
