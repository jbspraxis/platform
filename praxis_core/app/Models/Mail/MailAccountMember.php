<?php

namespace App\Models\Mail;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A person's access to a shared mailbox: read, send (also reply, file and delete) or manage (also people and password). */
class MailAccountMember extends Model
{
    public const LEVELS = ['read', 'send', 'manage'];

    public const LEVEL_LABELS = ['read' => 'Read', 'send' => 'Read and send', 'manage' => 'Manager'];

    protected $guarded = ['id'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
