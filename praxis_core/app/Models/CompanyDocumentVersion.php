<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One file of a company document: the first upload is version 1, each replacement the next number. The file sits on
 * the private disk; Claude's reading of it is an ai_runs row (purpose company_document_read), and nothing it
 * suggests reaches HQ until a person applies it (applied: what changed, reviewed_at/by: who decided).
 */
class CompanyDocumentVersion extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['document_id', 'number', 'path', 'original_name', 'mime', 'size', 'sha256', 'uploaded_by',
        'ai_run_id', 'read_error', 'applied', 'reviewed_at', 'reviewed_by'];

    protected $casts = [
        'number' => 'integer',
        'size' => 'integer',
        'applied' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(CompanyDocument::class, 'document_id');
    }

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** PDFs and images open in the browser; anything else (a .docx) downloads */
    public function opensInline(): bool
    {
        return in_array($this->mime, ['application/pdf', 'image/jpeg', 'image/png'], true);
    }
}
