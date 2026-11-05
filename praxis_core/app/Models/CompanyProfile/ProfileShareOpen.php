<?php

namespace App\Models\CompanyProfile;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One counted open of a share link (docs/COMPANY_PROFILE.md section 14). The visitor is a hash and the user
 * agent is reduced to a browser and a platform, so the record says that somebody opened it and roughly on
 * what, and nothing that identifies a person.
 */
class ProfileShareOpen extends Model
{
    use HasUuids;

    protected $table = 'company_profile_share_opens';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['created_at' => 'datetime'];

    /** Never let the hash out of HQ by accident */
    protected $hidden = ['visitor'];

    public function link(): BelongsTo
    {
        return $this->belongsTo(ProfileShareLink::class, 'share_link_id');
    }
}
