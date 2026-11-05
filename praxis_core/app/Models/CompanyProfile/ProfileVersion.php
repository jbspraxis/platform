<?php

namespace App\Models\CompanyProfile;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One version of the company profile (docs/COMPANY_PROFILE.md section 3). A version is written as a draft,
 * sent for review and then published; publishing never changes a published version, it supersedes it.
 *
 * @property string $id
 * @property int $number
 * @property string $reference
 * @property string $status
 */
class ProfileVersion extends Model
{
    use HasUuids;

    public const DRAFT = 'draft';

    public const IN_REVIEW = 'in_review';

    public const PUBLISHED = 'published';

    public const SUPERSEDED = 'superseded';

    protected $table = 'company_profile_versions';

    protected $guarded = ['id'];

    protected $casts = [
        'number' => 'integer',
        'submitted_at' => 'datetime',
        'published_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(ProfileSection::class, 'version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** A draft or a version in review is the only thing anyone may edit */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::DRAFT, self::IN_REVIEW], true);
    }

    public function label(): string
    {
        return 'v'.$this->number;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::DRAFT => 'Draft',
            self::IN_REVIEW => 'In review',
            self::PUBLISHED => 'Published',
            default => 'Superseded',
        };
    }
}
