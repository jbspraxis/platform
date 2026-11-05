<?php

namespace App\Models\Letters;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The other party to an agreement (docs/DOCUMENTS.md section 12): who they are as the document prints them, the
 * private link they open, the one-time code they are emailed, and the signature they draw.
 *
 * One row per signatory, so an agreement between three parties needs no change to anything but the screen. Today
 * HQ writes one. The signature is encrypted at rest, like everybody else's.
 */
class LetterCounterparty extends Model
{
    use HasUuids;

    public const PENDING = 'pending';

    public const SENT = 'sent';

    public const SIGNED = 'signed';

    protected $guarded = ['id'];

    /** Neither the link's token nor the code's HMAC is ever serialised */
    protected $hidden = ['image', 'token', 'otp'];

    protected $casts = [
        'image' => 'encrypted',
        'position' => 'integer',
        'sent_count' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'token_expires_at' => 'datetime',
        'sent_at' => 'datetime',
        'otp_expires_at' => 'datetime',
        'code_verified_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(LetterDocument::class, 'document_id');
    }

    public function hasSigned(): bool
    {
        return $this->status === self::SIGNED;
    }

    /** The link was sent and has not run out */
    public function linkIsLive(): bool
    {
        return $this->status === self::SENT && $this->token !== null
            && $this->token_expires_at !== null && $this->token_expires_at->isFuture();
    }

    public function dataUri(): ?string
    {
        return $this->image ? 'data:image/jpeg;base64,' . $this->image : null;
    }

    /** "Jonathan Ndamzi Destiny, Full-Stack Engineer" — how the email names who the code signs as */
    public function printedAs(): string
    {
        return trim($this->name . ($this->role_title ? ', ' . $this->role_title : ''));
    }
}
