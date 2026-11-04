<?php

namespace App\Models\Campaigns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What came back from an outreach email (docs/CAMPAIGNS.md section 12): a reply, a bounce notice or a spam complaint
 * report, found in HQ Mail's copy of the mailboxes. A reply gets Claude's suggested label (ai_label, ai_reason) and a
 * person's (label): nothing irreversible happens on Claude's word alone. A notice is filed by HQ (label bounced or
 * complained, label_by null) because it is a mail server's fact, not a guess.
 */
class OutreachReply extends Model
{
    /** What a person can choose for a reply (Claude suggests one of these) */
    public const LABELS = [
        'interested' => 'Interested', 'not_now' => 'Not now', 'not_interested' => 'Not interested', 'unsubscribe' => 'Unsubscribe',
    ];

    /** Labels a mail server's notice (or a person, for "They call it spam") files */
    public const NOTICES = ['bounced' => 'Bounced', 'complained' => 'Marked as spam'];

    /** Pill tone per label */
    public const TONES = [
        'interested' => 'pill-success', 'not_now' => 'pill-info', 'not_interested' => 'pill-neutral', 'unsubscribe' => 'pill-warning',
        'bounced' => 'pill-danger', 'complained' => 'pill-danger',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'received_at' => 'datetime',
        'label_at' => 'datetime',
        'ai_remind_on' => 'date',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(OutreachCampaign::class, 'campaign_id');
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(OutreachProspect::class, 'prospect_id');
    }

    public function email(): BelongsTo
    {
        return $this->belongsTo(OutreachEmail::class, 'email_id');
    }

    public function isReply(): bool
    {
        return $this->kind === 'reply';
    }

    /** A reply nobody has confirmed yet */
    public function isUnsorted(): bool
    {
        return $this->isReply() && $this->label === null;
    }

    public static function labelName(?string $label): string
    {
        return self::LABELS[$label] ?? self::NOTICES[$label] ?? 'To sort';
    }
}
