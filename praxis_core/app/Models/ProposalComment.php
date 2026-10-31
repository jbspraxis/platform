<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A comment on one section of a proposal, from the client in the portal or from staff (PR 4) */
class ProposalComment extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'proposal_id', 'section_key', 'author_type', 'author_name', 'author_email', 'body', 'resolved_by', 'resolved_at',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = ['resolved_at' => 'datetime'];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }
}
