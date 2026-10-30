<?php

namespace App\Models\Mail;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A conversation: messages linked by Message-ID, In-Reply-To and References (App\Support\Mail\Sync\Threader),
 * across folders. Counters are kept by App\Support\Mail\Sync\ThreadCounters. Teamwork columns (status,
 * assigned_*, done_*) and the AI summary belong to PR 4.
 */
class MailThread extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'participants' => 'array',
            'has_attachments' => 'boolean',
            'first_message_at' => 'datetime',
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'assigned_at' => 'datetime',
            'done_at' => 'datetime',
            'ai_summary' => 'array',
            'ai_summary_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'account_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class, 'thread_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(MailThreadNote::class, 'thread_id');
    }
}
