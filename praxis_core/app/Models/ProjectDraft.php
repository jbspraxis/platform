<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** The AI's draft of the project once the SOW is signed (PR 5): the lead edits it, then clicks Create project */
class ProjectDraft extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'opportunity_id', 'sow_id', 'payload', 'status', 'revision', 'ai_run_id', 'created_project_id', 'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = ['payload' => 'array', 'revision' => 'integer'];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function sow(): BelongsTo
    {
        return $this->belongsTo(StatementOfWork::class, 'sow_id');
    }

    /** The job drafting it: where it stands, without its input or output (AiRun::LIGHT; ProjectDrafts::sync reads the output) */
    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class)->select(AiRun::LIGHT);
    }
}
