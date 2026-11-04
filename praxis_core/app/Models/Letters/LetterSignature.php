<?php

namespace App\Models\Letters;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person's own signature, drawn or uploaded once (docs/DOCUMENTS.md section 5). The image is a JPEG as base64,
 * encrypted at rest and never written to a public path; only its owner can apply it, and only with a fresh sign-in.
 */
class LetterSignature extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['image'];

    protected $casts = [
        'image' => 'encrypted',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** What an <img> needs: the JPEG as a data URI */
    public function dataUri(): string
    {
        return 'data:image/jpeg;base64,' . $this->image;
    }
}
