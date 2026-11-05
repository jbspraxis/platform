<?php

namespace App\Models\CompanyProfile;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One section of one version (docs/COMPANY_PROFILE.md section 4). `structure` holds what is true whatever the
 * language — the ids and order of every list item, the `held` switch on a credential, the `enabled` switch on
 * a company fact, a case story's image — and `text_en` / `text_fr` hold only the words, keyed by those ids.
 *
 * @property string $key
 * @property array $structure
 * @property array $text_en
 * @property array $text_fr
 */
class ProfileSection extends Model
{
    use HasUuids;

    protected $table = 'company_profile_sections';

    protected $guarded = ['id'];

    protected $casts = [
        'structure' => 'array',
        'text_en' => 'array',
        'text_fr' => 'array',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProfileVersion::class, 'version_id');
    }

    public function text(string $locale): array
    {
        return $locale === 'fr' ? ($this->text_fr ?? []) : ($this->text_en ?? []);
    }

    public function setText(string $locale, array $text): void
    {
        $this->{$locale === 'fr' ? 'text_fr' : 'text_en'} = $text;
    }
}
