<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** The SOW drafted from the approved proposal (PR 5), signed by the client with the one-time code */
class StatementOfWork extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'statements_of_work';

    protected $guarded = ['id'];

    protected $hidden = ['token_hash', 'auth_otp', 'hq_signature_b64', 'client_signature_b64'];

    protected $casts = [
        'sections' => 'array',
        'version' => 'integer',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
        'otp_expires_at' => 'datetime',
        'hq_signed_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }
}
