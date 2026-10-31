<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** The AI's questions to the opportunity's lead (PR 3), answered by our team only */
class Questionnaire extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'opportunity_id', 'question_count', 'focus', 'status', 'ai_run_id', 'completed_by', 'completed_at',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = ['question_count' => 'integer', 'completed_at' => 'datetime'];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuestionnaireQuestion::class)->orderBy('position');
    }
}
