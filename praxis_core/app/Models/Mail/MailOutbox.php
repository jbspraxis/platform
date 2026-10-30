<?php

namespace App\Models\Mail;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mail written in HQ (PR 3): drafts, queued and scheduled sends, and what was sent. `payload` holds to, cc,
 * bcc, subject, html, text, attachments and whatever else the composer needs (docs/MAIL.md, "Outbox").
 * When a sent message's copy comes back from the server, sync sets mail_messages.sent_by from user_id.
 */
class MailOutbox extends Model
{
    use HasUuids;

    protected $table = 'mail_outbox';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'send_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
