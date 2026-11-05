<?php

namespace App\Models\CompanyProfile;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One tracked share link (docs/COMPANY_PROFILE.md section 14). The rules live in
 * App\Support\CompanyProfile\Shares; this is the row and what it is worth right now.
 */
class ProfileShareLink extends Model
{
    use HasUuids;

    protected $table = 'company_profile_share_links';

    protected $guarded = ['id'];

    protected $hidden = ['token'];

    protected $casts = [
        'opens' => 'integer',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_opened_at' => 'datetime',
    ];

    /** What the link is worth right now: 'ok', 'revoked' or 'expired' */
    public function state(): string
    {
        if ($this->revoked_at !== null) {
            return 'revoked';
        }
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return 'expired';
        }

        return 'ok';
    }

    public function isOpen(): bool
    {
        return $this->state() === 'ok';
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProfileVersion::class, 'version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function opens(): HasMany
    {
        return $this->hasMany(ProfileShareOpen::class, 'share_link_id')->latest('created_at');
    }

    /** "Ada Okafor, Foxtrot Foods" — who the link was made for, as the list and the bell say it */
    public function forWhom(): string
    {
        $parts = array_filter([trim((string) $this->recipient_name), trim((string) $this->recipient_company)]);

        return $parts === [] ? 'no one in particular' : implode(', ', $parts);
    }
}
