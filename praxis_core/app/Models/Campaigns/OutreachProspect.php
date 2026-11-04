<?php

namespace App\Models\Campaigns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A researched prospect (docs/CAMPAIGNS.md section 10). Never a contact or a subscriber: prospects live only here, so
 * no broadcast can reach them. They become a lead only through C3's Make a lead, which keeps this row (its emails
 * are the history) and links it to the lead (organization_id).
 */
class OutreachProspect extends Model
{
    /** new: nothing sent yet; emailed: the first email went; then replied, bounced or unsubscribed; skipped by a person */
    public const STATUSES = [
        'new' => 'Not emailed', 'emailed' => 'Emailed', 'replied' => 'Replied', 'bounced' => 'Bounced',
        'unsubscribed' => 'Unsubscribed', 'skipped' => 'Skipped',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'website_read_at' => 'datetime',
        'replied_at' => 'datetime',
        'bounced_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'complained_at' => 'datetime',
        'lead_made_at' => 'datetime',
        'remind_on' => 'date',
        'reminded_at' => 'datetime',
    ];

    protected $hidden = ['website_text'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(OutreachCampaign::class, 'campaign_id');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(OutreachEmail::class, 'prospect_id');
    }

    public function firstEmail(): HasOne
    {
        return $this->hasOne(OutreachEmail::class, 'prospect_id')->where('kind', 'first');
    }

    public function followUp(): HasOne
    {
        return $this->hasOne(OutreachEmail::class, 'prospect_id')->where('kind', 'follow_up');
    }

    /** C3: their replies, bounce notices and complaint reports */
    public function replies(): HasMany
    {
        return $this->hasMany(OutreachReply::class, 'prospect_id');
    }

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
