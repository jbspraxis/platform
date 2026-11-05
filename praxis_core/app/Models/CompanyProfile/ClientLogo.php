<?php

namespace App\Models\CompanyProfile;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One client logo in the library (docs/COMPANY_PROFILE.md section 2). The rules live in
 * App\Support\CompanyProfile\LogoLibrary; this is only the row and the three files it points at.
 *
 * A logo is retired, never deleted: the profile versions that carried it stay true.
 */
class ClientLogo extends Model
{
    use HasUuids;

    protected $table = 'company_profile_logos';

    protected $guarded = ['id'];

    protected $casts = [
        'position' => 'integer',
        'on_profile' => 'boolean',
        'on_deck' => 'boolean',
        'on_website' => 'boolean',
        'width' => 'integer',
        'height' => 'integer',
        'retired_at' => 'datetime',
    ];

    /** Where each use looks: profile, deck, website */
    public const USES = ['profile' => 'on_profile', 'deck' => 'on_deck', 'website' => 'on_website'];

    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNull('retired_at')->orderBy('position')->orderBy('name');
    }

    public function scopeRetired(Builder $query): Builder
    {
        return $query->whereNotNull('retired_at')->orderBy('name');
    }

    /** Only a logo that has somewhere to show it is of any use to that place */
    public function scopeShownOn(Builder $query, string $use): Builder
    {
        return $query->where(self::USES[$use] ?? 'on_profile', true);
    }

    public function isRetired(): bool
    {
        return $this->retired_at !== null;
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
