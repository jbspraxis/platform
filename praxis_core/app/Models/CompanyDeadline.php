<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A date in a company's compliance calendar (Master data › Company documents, docs/COMPANY_DOCUMENTS.md): the CAC
 * annual return, the first accounts, a tax filing. A yearly one, once marked done, adds next year's.
 */
class CompanyDeadline extends Model
{
    use HasUuids;

    public const REPEATS = ['once' => 'One-off', 'yearly' => 'Every year'];

    protected $fillable = ['legal_entity_id', 'title', 'due_on', 'repeats', 'remind_days', 'reminded_for', 'notes', 'document_id',
        'done_at', 'done_by', 'created_by', 'updated_by', 'archived_at', 'archived_by'];

    protected $casts = [
        'due_on' => 'date:Y-m-d',
        'reminded_for' => 'date:Y-m-d',
        'remind_days' => 'integer',
        'done_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    protected $attributes = ['repeats' => 'once'];

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }
}
