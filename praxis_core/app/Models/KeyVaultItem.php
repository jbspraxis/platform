<?php

namespace App\Models;

use App\Support\KeyVault\Envelope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One secret in the owner's key vault (docs/KEY_VAULT.md), as the owner's browser locked it: never readable here.
 * The id is made by the browser before it encrypts, because the ciphertext is bound to it.
 */
class KeyVaultItem extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** Kept out of arrays and JSON: the envelope leaves the server only after the owner's emailed code */
    protected $hidden = ['kdf_salt', 'iv', 'ciphertext'];

    protected $casts = [
        'format_version' => 'integer',
        'kdf_iterations' => 'integer',
        'last_revealed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function envelope(): Envelope
    {
        return Envelope::fromItem($this);
    }
}
