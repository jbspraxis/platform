<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A proposal. The proposal screens still write the table directly (ProposalBuilder, ProposalGateway); this model
 * is how opportunities read them. signing_mode legacy = signed with the one-time code, as before PR 4.
 */
class Proposal extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['token_hash', 'auth_otp', 'hq_signature_b64', 'client_signature_b64'];

    protected $casts = [
        'sections' => 'array',
        'version' => 'integer',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
        'client_approved_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProposalComment::class);
    }
}
