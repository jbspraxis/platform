<?php

namespace App\Models\Campaigns;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An outreach campaign (HQ › Campaigns › Outreach, docs/CAMPAIGNS.md section 10): a list of researched prospects,
 * whose emails go from the sender's own jbspraxis.com mailbox once a person has approved them.
 */
class OutreachCampaign extends Model
{
    use HasUuids;

    /** active: approved emails go out; paused: nothing goes out until it is resumed; archived: done, kept for its numbers */
    public const STATUSES = ['active' => 'Active', 'paused' => 'Paused', 'archived' => 'Archived'];

    protected $fillable = ['name', 'brief', 'owner_id', 'sender_id', 'status', 'paused_by', 'paused_at'];

    protected $casts = ['paused_at' => 'datetime'];

    protected $attributes = ['status' => 'active'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(OutreachProspect::class, 'campaign_id');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(OutreachEmail::class, 'campaign_id');
    }
}
