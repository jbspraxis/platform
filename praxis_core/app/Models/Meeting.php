<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A client or internal meeting of an opportunity (docs/OPPORTUNITIES.md section 7), or a lead's meeting with no
 * opportunity yet (opportunity_id null, docs/LEADS.md section 9; organization_id is the lead). starts_at and ends_at
 * are UTC; time_zone says how to show them. Internal meetings and every transcript stay in HQ.
 */
class Meeting extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'opportunity_id', 'phase_id', 'organization_id', 'kind', 'title', 'agenda', 'typed_notes', 'starts_at', 'ends_at', 'time_zone', 'status',
        'appointment_id', 'google_event_id', 'meet_link', 'meet_code', 'location', 'transcript_status', 'transcript_next_try_at', 'transcript_tries',
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

    /** The client organisation: the opportunity's, or the lead's for a lead meeting */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** A lead's meeting, not (yet) part of an opportunity (docs/LEADS.md section 9) */
    public function isLeadMeeting(): bool
    {
        return $this->opportunity_id === null;
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(MeetingAttendee::class);
    }

    public function transcripts(): HasMany
    {
        return $this->hasMany(MeetingTranscript::class)->orderBy('collected_at');
    }

    /** A milestone's handover meeting: the points raised in it, in order (PR 6) */
    public function points(): HasMany
    {
        return $this->hasMany(MeetingPoint::class)->orderBy('position')->orderBy('created_at');
    }

    /** A client meeting that hands over a milestone (phase_id: project_phases are the milestones) */
    public function isHandover(): bool
    {
        return $this->phase_id !== null && $this->kind === 'client';
    }
}
