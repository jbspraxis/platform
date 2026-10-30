<?php

namespace App\Models\Mail;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An attachment's details; the file itself stays on the server (App\Support\Mail\MailFetch::attachment()). */
class MailAttachment extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_inline' => 'boolean', 'size' => 'integer'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class, 'message_id');
    }
}
