<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One question: at least 3 suggested answers (options), the one the AI recommends and why, and the lead's answer.
 * The answer is a suggestion (answer_option) with optional detail (answer_text), or the lead's own words
 * (answer_text alone). The lead's own questions (origin lead) have no suggestions until they ask for some.
 */
class QuestionnaireQuestion extends Model
{
    use HasUuids;

    protected $fillable = [
        'questionnaire_id', 'position', 'question', 'why_it_matters', 'options', 'recommended_option', 'recommendation_reason',
        'answer_option', 'answer_text', 'answered_by', 'answered_at', 'origin', 'ai_run_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'options' => 'array',
        'position' => 'integer',
        'recommended_option' => 'integer',
        'answer_option' => 'integer',
        'answered_at' => 'datetime',
    ];

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class);
    }

    /** The job redrafting it, while it runs (or the failed one, until dismissed): where it stands only (AiRun::LIGHT) */
    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class)->select(AiRun::LIGHT);
    }

    public function isAnswered(): bool
    {
        return $this->answer_option !== null || filled($this->answer_text);
    }
}
