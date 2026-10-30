<?php

namespace App\Models\Mail;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A folder on the mail server. `path` is the server's own name (use it in IMAP), `name` the readable one. */
class MailFolder extends Model
{
    /** Display order of the standard folders; others follow by name */
    public const ROLE_ORDER = ['inbox' => 0, 'drafts' => 1, 'sent' => 2, 'archive' => 3, 'spam' => 4, 'trash' => 5, 'other' => 6];

    public const ROLE_LABELS = ['inbox' => 'Inbox', 'drafts' => 'Drafts', 'sent' => 'Sent', 'archive' => 'Archive', 'spam' => 'Spam', 'trash' => 'Trash'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'selectable' => 'boolean',
            'initial_synced_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'account_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class, 'folder_id');
    }

    public function label(): string
    {
        return self::ROLE_LABELS[$this->role] ?? $this->name;
    }
}
