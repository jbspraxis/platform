<?php

namespace App\Models\Mail;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * HQ's copy of one message in one folder (the same message in two folders is two rows with the same
 * message_id_hash). Bodies: body_state cached → body_text/body_html; otherwise read them with
 * App\Support\Mail\MailFetch::body(). The HTML is the sender's, unsanitised: never print it without the
 * reader's sanitiser (PR 2).
 */
class MailMessage extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['body_text', 'body_html'];

    protected function casts(): array
    {
        return [
            'to' => 'array',
            'cc' => 'array',
            'bcc' => 'array',
            'reply_to' => 'array',
            'parts' => 'array',
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
            'seen_at' => 'datetime',
            'is_seen' => 'boolean',
            'is_flagged' => 'boolean',
            'is_answered' => 'boolean',
            'is_draft' => 'boolean',
            'has_attachments' => 'boolean',
            'is_outbound' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'account_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MailFolder::class, 'folder_id');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MailThread::class, 'thread_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MailAttachment::class, 'message_id');
    }

    public function seenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seen_by');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /** Message-IDs this message answers, oldest first (References, then In-Reply-To; without brackets) */
    public function referenceIds(): array
    {
        $ids = preg_split('/\s+/', trim((string) $this->references)) ?: [];
        if ($this->in_reply_to) {
            $ids[] = $this->in_reply_to;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /** How Message-IDs are matched: lower case, no angle brackets, sha1 */
    public static function hashId(?string $messageId): ?string
    {
        $messageId = trim((string) $messageId, " \t<>");

        return $messageId === '' ? null : sha1(strtolower($messageId));
    }
}
