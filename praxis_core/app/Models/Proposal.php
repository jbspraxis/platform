<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A proposal. signing_mode legacy = signed with the one-time code (ProposalBuilder and ProposalGateway write the
 * table directly), as before PR 4. signing_mode approve = proposal v2 (App\Support\Opportunities\Proposals): one
 * row per version of an opportunity's proposal, drafted by AI, approved by the owner or the lead, sent with a PDF,
 * reviewed by the client in the portal (comments by section, approve, ask for changes). Line items for both are
 * in proposal_line_items.
 */
class Proposal extends Model
{
    use HasUuids, SoftDeletes;

    /** A v2 proposal's statuses: Draft (approved_at is set once approved), Sent, then one of the last three */
    public const DRAFT = 'Draft';

    public const SENT = 'Sent';

    public const CHANGES_REQUESTED = 'Changes Requested';

    public const CLIENT_APPROVED = 'Client Approved';

    public const SUPERSEDED = 'Superseded';

    protected $guarded = ['id'];

    protected $hidden = ['token_hash', 'auth_otp', 'hq_signature_b64', 'client_signature_b64'];

    protected $casts = [
        'sections' => 'array',
        'recipients' => 'array',
        'recipients_unsent' => 'array',
        'version' => 'integer',
        'validity_days' => 'integer',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
        'client_approved_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProposalComment::class)->orderBy('created_at')->orderBy('id');
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class);
    }

    public function changesRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'changes_run_id');
    }

    /** Proposal v2: the client reviews and approves it in the portal; the SOW (PR 5) is what gets signed */
    public function isReviewed(): bool
    {
        return $this->signing_mode === 'approve';
    }

    /** A draft nobody has approved yet: the only state in which it can be changed */
    public function isEditable(): bool
    {
        return $this->status === self::DRAFT && $this->approved_at === null;
    }

    /** The day its validity ends (validity_days from when it was sent), or null before it is sent */
    public function validUntil(): ?Carbon
    {
        return $this->sent_at?->copy()->addDays(max(1, (int) $this->validity_days));
    }

    public function hasExpired(): bool
    {
        return $this->status === self::SENT && $this->validUntil()?->isPast() === true;
    }
}
