<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One question: at least 3 suggested answers (options), the one the AI recommends and why, and the lead's answer */
class QuestionnaireQuestion extends Model
{
    use HasUuids;

    protected $fillable = [
        'questionnaire_id', 'position', 'question', 'why_it_matters', 'options', 'recommended_option', 'recommendation_reason',
        'answer_option', 'answer_text', 'answered_by', 'answered_at', 'origin', 'created_by', 'updated_by',
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
}
