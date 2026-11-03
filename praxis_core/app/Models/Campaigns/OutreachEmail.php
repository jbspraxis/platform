<?php

namespace App\Models\Campaigns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One email to a prospect (docs/CAMPAIGNS.md section 10): the first email or its one follow-up.
 *
 *   writing → draft → approved → sending → sent              (first: approved means waiting for its turn)
 *   writing → draft → approved → scheduled → sending → sent  (follow-up: scheduled once the first has gone)
 *   not_written: Claude couldn't write it (Try again); skipped; bounced; failed (not sent again); cancelled (a
 *   follow-up stopped by a reply, a bounce or an unsubscribe)
 */
class OutreachEmail extends Model
{
    public const STATUSES = [
        'writing' => 'Writing', 'not_written' => 'Not written', 'draft' => 'To review', 'approved' => 'Approved',
        'scheduled' => 'Scheduled', 'sending' => 'Sending', 'sent' => 'Sent', 'skipped' => 'Skipped',
        'bounced' => 'Bounced', 'failed' => 'Not sent', 'cancelled' => 'Cancelled',
    ];

    /** Statuses a person can still change */
    public const REVIEWABLE = ['draft', 'approved', 'not_written', 'skipped'];

    protected $guarded = ['id'];

    protected $casts = [
        'edited' => 'boolean',
        'approved_at' => 'datetime',
        'send_after' => 'datetime',
        'tried_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(OutreachCampaign::class, 'campaign_id');
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(OutreachProspect::class, 'prospect_id');
    }

    public function isFirst(): bool
    {
        return $this->kind === 'first';
    }
}
