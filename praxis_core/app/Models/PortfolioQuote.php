<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioQuote extends Model
{
    protected $table = 'portfolio_quotes';
    
    // Ensure the ID is treated as a string since we used UUIDs
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $casts = [
        'is_active' => 'boolean',
    ];
}