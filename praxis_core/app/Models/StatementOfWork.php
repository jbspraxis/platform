<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * The SOW drafted from the approved proposal (PR 5), signed by the client with the one-time code. One row per
 * version: draft (approved once HQ has signed it: approved_at and the HQ signature), sent, changes_requested,
 * signed, or superseded (a later version was sent or signed). App\Support\Opportunities\Sows holds the rules.
 */
class StatementOfWork extends Model
{
    use HasUuids, SoftDeletes;

    public const DRAFT = 'draft';

    public const SENT = 'sent';

    public const CHANGES_REQUESTED = 'changes_requested';

    public const SIGNED = 'signed';

    public const SUPERSEDED = 'superseded';

    /**
     * Every column but the two drawn signatures (base64 PNGs of up to 400 KB each, shown nowhere in HQ: the PDF and the
     * tab show the signatures as text): what Sows::versions reads for the SOW tab
     */
    public const WITHOUT_SIGNATURES = [
        'id', 'opportunity_id', 'proposal_id', 'reference', 'title', 'version', 'previous_version_id', 'sections', 'milestones', 'vat_rate', 'vat_applied',
        'billing_entity_id', 'status', 'ai_run_id', 'validity_days', 'changes_summary', 'approved_by', 'approved_at', 'sent_at', 'recipients', 'recipients_unsent',
        'token_hash', 'auth_otp', 'otp_expires_at', 'hq_signer_name', 'hq_signature_hash', 'hq_ip_address', 'hq_signed_at', 'provider_legal_name',
        'signed_by_name', 'signed_by_title', 'signed_by_email', 'client_signature_hash', 'client_ip_address', 'ip_security_flag', 'signed_at',
        'changes_request', 'changes_requested_by', 'changes_requested_at', 'deposit_invoice_id', 'project_id',
        'created_by', 'updated_by', 'deleted_by', 'created_at', 'updated_at', 'deleted_at',
    ];

    protected $table = 'statements_of_work';

    protected $guarded = ['id'];

    protected $hidden = ['token_hash', 'auth_otp', 'hq_signature_b64', 'client_signature_b64'];

    protected $casts = [
        'sections' => 'array',
        'milestones' => 'array',
        'recipients' => 'array',
        'recipients_unsent' => 'array',
        'version' => 'integer',
        'validity_days' => 'integer',
        'vat_applied' => 'boolean',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
        'otp_expires_at' => 'datetime',
        'hq_signed_at' => 'datetime',
        'signed_at' => 'datetime',
        'changes_requested_at' => 'datetime',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** The job drafting it: where it stands, without its input or output (AiRun::LIGHT; Sows::sync reads the output) */
    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class)->select(AiRun::LIGHT);
    }

    /** A draft nobody has approved (signed for HQ) yet: the only state in which it can be changed */
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
