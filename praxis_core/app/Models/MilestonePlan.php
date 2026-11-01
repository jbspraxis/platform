<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One planning request of Plan milestones (PR 5b): a milestone of a running project, or a whole new plan. App\Support\Projects\Planner holds the rules. */
class MilestonePlan extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = ['sources' => 'array', 'output' => 'array', 'pass' => 'integer', 'revision' => 'integer'];

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class);
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(PlanSuggestion::class, 'plan_id')->orderBy('position');
    }

    public function transcriptMeeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'transcript_meeting_id');
    }
}
