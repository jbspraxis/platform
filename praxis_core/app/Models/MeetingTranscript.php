<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * What was said in a meeting, with the AI's internal notes and the client minutes. Personal data (NDPA, Cameroon's
 * data protection law): only staff with opportunities.transcripts (or the lead) read the full text; clients only
 * ever see the minutes they are sent.
 */
class MeetingTranscript extends Model
{
    use HasUuids, SoftDeletes;

    /** Every column but the text and its lines (up to 500,000 characters each): what a list of meetings needs */
    public const WITHOUT_TEXT = ['id', 'meeting_id', 'source', 'google_doc_id', 'language', 'notes', 'minutes', 'minutes_sent_at', 'minutes_unsent',
        'collected_at', 'created_by', 'updated_by', 'deleted_by', 'created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'meeting_id', 'source', 'google_doc_id', 'language', 'text', 'entries', 'notes', 'minutes', 'minutes_sent_at', 'minutes_unsent', 'collected_at',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'entries' => 'array',
        'notes' => 'array',
        'minutes_sent_at' => 'datetime',
        'minutes_unsent' => 'array',
        'collected_at' => 'datetime',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
