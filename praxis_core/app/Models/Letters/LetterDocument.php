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
        'revoked_at' => 'datetime',
        'file_bytes' => 'integer',
    ];

    public function signatures(): HasMany
    {
        return $this->hasMany(LetterDocumentSignature::class, 'document_id')->orderBy('position');
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

    /** Issued and still good: not revoked, not replaced */
    public function isValid(): bool
    {
        return $this->status === self::ISSUED;
    }
}
