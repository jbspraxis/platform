<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One administrative paper of a JBS Praxis company (Master data › Company documents, docs/COMPANY_DOCUMENTS.md):
 * a certificate, the memorandum and articles, a tax certificate… Its files are versions; archiving hides it from
 * the list and keeps everything.
 */
class CompanyDocument extends Model
{
    use HasUuids;

    protected $fillable = ['legal_entity_id', 'kind', 'title', 'issued_on', 'expires_on', 'remind_days', 'reminded_for', 'notes',
        'current_version_id', 'created_by', 'updated_by', 'archived_at', 'archived_by'];

    protected $casts = [
        'issued_on' => 'date:Y-m-d',
        'expires_on' => 'date:Y-m-d',
        'reminded_for' => 'date:Y-m-d',
        'remind_days' => 'integer',
        'archived_at' => 'datetime',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(CompanyDocumentVersion::class, 'document_id')->orderByDesc('number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(CompanyDocumentVersion::class, 'current_version_id');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
