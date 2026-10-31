<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One piece of work with a client (docs/OPPORTUNITIES.md): its meetings, transcripts, questionnaire, proposal
 * versions and SOW, until the SOW is signed; then it is Won and points at its project.
 */
class Opportunity extends Model
{
    use HasUuids, SoftDeletes;

    /** In the order work moves through them; the board shows a column for each */
    public const STAGES = ['discovery', 'questionnaire', 'proposal', 'proposal_sent', 'proposal_approved', 'sow', 'sow_sent', 'won', 'lost', 'on_hold'];

    /** Stages that close an opportunity (closed_at is set) */
    public const CLOSED = ['won', 'lost'];

    public const LANGUAGES = ['en', 'fr'];

    protected $fillable = [
        'organization_id', 'title', 'stage', 'lead_user_id', 'language', 'source', 'project_id', 'lost_reason', 'closed_at',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = ['closed_at' => 'datetime'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_user_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class)->orderBy('starts_at');
    }

    public function questionnaires(): HasMany
    {
        return $this->hasMany(Questionnaire::class)->latest();
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class)->orderByDesc('created_at');
    }

    public function statementsOfWork(): HasMany
    {
        return $this->hasMany(StatementOfWork::class)->orderByDesc('version');
    }

    public function projectDrafts(): HasMany
    {
        return $this->hasMany(ProjectDraft::class)->latest();
    }

    public function isClosed(): bool
    {
        return in_array($this->stage, self::CLOSED, true);
    }
}
