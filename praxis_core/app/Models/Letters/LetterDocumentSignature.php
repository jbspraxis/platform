<?php

namespace App\Models\Letters;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One signature on one document, kept as it was at the moment it was applied (docs/DOCUMENTS.md section 5). */
class LetterDocumentSignature extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['image'];

    protected $casts = [
        'image' => 'encrypted',
        'signed_at' => 'datetime',
        'width' => 'integer',
        'height' => 'integer',
        'position' => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(LetterDocument::class, 'document_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dataUri(): string
    {
        return 'data:image/jpeg;base64,' . $this->image;
    }
}
