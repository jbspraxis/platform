<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One AI job (App\Support\Ai\Ai): its purpose, model, progress, tokens, cost and the validated output. Screens
 * poll status and progress; a failed run carries a message fit to show the person who asked.
 */
class AiRun extends Model
{
    use HasUuids;

    protected $fillable = [
        'purpose', 'provider', 'model', 'subject_type', 'subject_id', 'status', 'progress', 'input', 'input_tokens', 'output_tokens',
        'cache_read_tokens', 'cache_write_tokens', 'cost_usd', 'stop_reason', 'output', 'error', 'started_at', 'finished_at', 'requested_by',
    ];

    protected $hidden = ['input'];

    protected $casts = [
        'input' => 'array',
        'output' => 'array',
        'progress' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cache_read_tokens' => 'integer',
        'cache_write_tokens' => 'integer',
        'cost_usd' => 'float',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['done', 'failed'], true);
    }

    public function succeeded(): bool
    {
        return $this->status === 'done';
    }

    /** What went wrong, in the current language (error holds a key from lang/{en,fr}/ai.php) */
    public function errorMessage(): ?string
    {
        if ($this->error === null) {
            return null;
        }

        return str_starts_with($this->error, 'ai.errors.') ? __($this->error) : $this->error;
    }
}
