<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A point raised in a sprint's handover meeting (docs/OPPORTUNITIES.md section 12, PR 6): drafted by the AI from the
 * transcript (origin ai) or added by the lead, then confirmed (a QA ticket on the project, qa_ticket_id) or left out
 * (dismissed). Only a draft can change.
 */
class MeetingPoint extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'meeting_id', 'transcript_id', 'ai_run_id', 'position', 'title', 'detail', 'raised_by', 'severity', 'task_id', 'status', 'origin',
        'qa_ticket_id', 'decided_by', 'decided_at', 'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'position' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
