<?php

namespace App\Models\Campaigns;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One broadcast (HQ › Campaigns › Broadcasts, docs/CAMPAIGNS.md): its layout, its English and French versions
 * (content), the segment it goes to and where sending stands. Rows from before 3 Nov 2026 have no layout: their
 * body_html is the email, in one language.
 */
class Broadcast extends Model
{
    use HasUuids;

    protected $table = 'broadcast_campaigns';

    /** Draft → (Scheduled →) Sending → Completed; a scheduled one can go back to Draft */
    public const STATUSES = ['Draft', 'Scheduled', 'Sending', 'Completed', 'Cancelled'];

    /** 'both': each person gets their own language; 'en' or 'fr': everyone gets that version */
    public const LANGUAGES = ['both' => 'English and French', 'en' => 'English only', 'fr' => 'French only'];

    protected $fillable = [
        'subject', 'layout', 'body_html', 'content', 'greeting', 'language', 'segment_id', 'audience', 'status', 'total_recipients',
        'estimated_completion_time', 'scheduled_at', 'started_at', 'completed_at', 'created_by', 'updated_by', 'sent_by',
    ];

    protected $casts = [
        'content' => 'array',
        'audience' => 'array',
        'greeting' => 'boolean',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'estimated_completion_time' => 'datetime',
    ];

    protected $attributes = ['status' => 'Draft', 'language' => 'both', 'greeting' => true, 'subject' => ''];

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class, 'segment_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BroadcastLog::class, 'campaign_id');
    }

    public function isLegacy(): bool
    {
        return $this->layout === null;
    }

    public function isEditable(): bool
    {
        return $this->status === 'Draft';
    }

    /** The languages it is written in: ['en', 'fr'], ['en'] or ['fr'] */
    public function locales(): array
    {
        return in_array($this->language, ['en', 'fr'], true) ? [$this->language] : ['en', 'fr'];
    }

    /** The version a person with this language gets */
    public function localeFor(string $locale): string
    {
        return in_array($locale, $this->locales(), true) ? $locale : $this->locales()[0];
    }

    public function version(string $locale): array
    {
        return (array) (($this->content ?? [])[$locale] ?? []);
    }

    /** The subject in its first language, for lists */
    public function title(): string
    {
        $subject = trim((string) ($this->version($this->locales()[0])['subject'] ?? ''));

        return $subject !== '' ? $subject : (trim((string) $this->subject) !== '' ? $this->subject : 'Untitled broadcast');
    }
}
