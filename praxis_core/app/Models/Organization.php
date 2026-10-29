<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 
        'industry', 
        'turnover_bracket', 
        'status', 
        'country', 
        'city',
        'timezone',
        'preferred_language',
        'budget_estimation',
        'enterprise_stage',
        'tech_reality',
        'governance_status',
        'utm_source',
        'utm_campaign',
        'ai_friction_matrix'
    ];
}