<?php

namespace App\Models\Letters;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One formal document on the company letterhead (docs/DOCUMENTS.md). A draft can be changed; once it is issued the
 * PDF and its hash are stored and never change again, and the only things that can happen to it are revoke and
 * replace. App\Support\Letters\Letters holds the rules.
 */
class LetterDocument extends Model
{
    use HasUuids;

    public const DRAFT = 'draft';

    public const ISSUED = 'issued';

    /** An agreement that JBS Praxis has issued and the other party has been asked to countersign */
    public const SENT_FOR_SIGNATURE = 'sent_for_signature';

    /** Every counterparty has signed; the countersigned PDF is stored beside the issued one */
    public const FULLY_SIGNED = 'fully_signed';

    public const REVOKED = 'revoked';

    public const REPLACED = 'replaced';

    protected $guarded = ['id'];

    protected $hidden = ['verify_token'];

    protected $casts = [
        'fields' => 'array',
        'year' => 'integer',
        'sequence' => 'integer',
        'show_qr' => 'boolean',
        'copy_public' => 'boolean',
        'document_date' => 'date',
        'issued_at' => 'datetime',
        'fully_signed_at' => 'datetime',
        'revoked_at' => 'datetime',
        'file_bytes' => 'integer',
        'signed_file_bytes' => 'integer',
    ];

    public function signatures(): HasMany
    {
        return $this->hasMany(LetterDocumentSignature::class, 'document_id')->orderBy('position');
    }

    /** The other signatories (docs/DOCUMENTS.md section 12): one row each, printed in this order */
    public function counterparties(): HasMany
    {
        return $this->hasMany(LetterCounterparty::class, 'document_id')->orderBy('position');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }

    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_id');
    }

    /** It has been filed in Master data › Company documents */
    public function isFiled(): bool
    {
        return $this->company_document_id !== null;
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    /** An agreement: the one kind with a second signature box and online countersigning */
    public function isAgreement(): bool
    {
        return $this->type === 'agreement';
    }

    /** Issued and still good: not revoked, not replaced. An agreement waiting for, or carrying, a countersignature is still good. */
    public function isValid(): bool
    {
        return in_array($this->status, [self::ISSUED, self::SENT_FOR_SIGNATURE, self::FULLY_SIGNED], true);
    }

    /** It has been issued: the PDF and its fingerprint are fixed from here on, whatever happens next */
    public function isIssued(): bool
    {
        return in_array($this->status, [self::ISSUED, self::SENT_FOR_SIGNATURE, self::FULLY_SIGNED], true);
    }

    public function isFullySigned(): bool
    {
        return $this->status === self::FULLY_SIGNED;
    }
}
