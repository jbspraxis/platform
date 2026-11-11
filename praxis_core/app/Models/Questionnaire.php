<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The AI's questions to the opportunity's lead (PR 3), answered by our team only: drafting while the AI writes
 * them, open while the lead answers, complete once locked for the proposal (App\Support\Opportunities\Questionnaires).
 * focus: ['areas' => keys of Questionnaires::FOCUS, 'note' => the lead's own words].
 */
class Questionnaire extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'opportunity_id', 'question_count', 'focus', 'status', 'ai_run_id', 'completed_by', 'completed_at',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = ['question_count' => 'integer', 'focus' => 'array', 'completed_at' => 'datetime'];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuestionnaireQuestion::class)->orderBy('position');
    }

    /** The job drafting the questions: where it stands, without its input or output (AiRun::LIGHT) */
    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class)->select(AiRun::LIGHT);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isComplete(): bool
    {
        return $this->status === 'complete';
    }
}
