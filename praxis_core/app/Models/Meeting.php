<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A client or internal meeting of an opportunity (docs/OPPORTUNITIES.md section 7). starts_at and ends_at are
 * UTC; time_zone says how to show them. Internal meetings and every transcript stay in HQ.
 */
class Meeting extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'opportunity_id', 'sprint_id', 'organization_id', 'kind', 'title', 'agenda', 'starts_at', 'ends_at', 'time_zone', 'status',
        'appointment_id', 'google_event_id', 'meet_link', 'meet_code', 'transcript_status', 'transcript_next_try_at', 'transcript_tries',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'transcript_next_try_at' => 'datetime',
        'transcript_tries' => 'integer',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(MeetingAttendee::class);
    }

    public function transcripts(): HasMany
    {
        return $this->hasMany(MeetingTranscript::class)->orderBy('collected_at');
    }

    /** A sprint's handover meeting: the points raised in it, in order (PR 6) */
    public function points(): HasMany
    {
        return $this->hasMany(MeetingPoint::class)->orderBy('position')->orderBy('created_at');
    }

    public function isHandover(): bool
    {
        return $this->sprint_id !== null && $this->kind === 'client';
    }
}
