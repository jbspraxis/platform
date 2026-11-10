<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One intel report on a lead (docs/LEADS.md section 13). The original stays as it came (a private file, or the text
 * pasted in); `structured` is the same report in the fixed layout, as Claude read it and then as a person edited it
 * (App\Support\Leads\LeadIntel). The AI reads only `structured`, and only while use_for_ai is on.
 */
class LeadIntelReport extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['raw_text'];

    protected $casts = [
        'structured' => 'array',
        'structured_at' => 'datetime',
        'use_for_ai' => 'boolean',
        'size' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
