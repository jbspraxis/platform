<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioCaseStudy extends Model
{
    protected $table = 'portfolio_case_studies';
    
    // This ensure the JSON column converts to an array we can loop through in Blade
    protected $casts = [
        'roi_metrics' => 'array',
        'is_published' => 'boolean',
    ];
}