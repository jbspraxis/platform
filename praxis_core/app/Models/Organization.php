<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A client organisation. While status is Lead (and once an Active Client) it is also a lead: lead_status, its owner
 * (assigned_to), source, next step and contacts (client_users) are kept by App\Support\Leads (docs/LEADS.md).
 */
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
        'ai_friction_matrix',
        'pipeline_stage',
        'assigned_to',
        'billing_address',
        'tax_identification_number',
        'registration_number',
        'created_by',
        'updated_by',
        // Leads (docs/LEADS.md)
        'lead_status',
        'source',
        'source_detail',
        'next_step',
        'next_step_due',
        'phone',
        'email',
    ];

    protected $casts = [
        'lost_at' => 'datetime',
        'client_since' => 'datetime',
        'next_step_due' => 'date',
        'last_activity_at' => 'datetime',
        'is_duplicate_suspect' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** The lead's contacts (client_users), the main contact first */
    public function contacts(): HasMany
    {
        return $this->hasMany(ClientUser::class)->orderByDesc('is_main_contact')->orderBy('created_at');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class)->orderByDesc('created_at');
    }
}
