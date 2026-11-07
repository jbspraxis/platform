<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str; // PRAXIS OVERRIDE: Required for String manipulation

class KaizenArticle extends Model
{
    use HasUuids;

    protected $fillable = [
        'slug', // PRAXIS OVERRIDE: Exposing the slug to mass assignment
        'category',
        'title',
        'executive_summary',
        'problem_statement',
        'engineered_solution',
        'final_roi',
        'read_time_minutes',
        'is_public',
        'is_internal_sop',
        'status',
        'published_at',
        'created_by',
        'contributor_id',
        'submitted_locale',
        'rejected_reason',
        'rejected_at',
        'updated_by',
        'deleted_by'
    ];

    /**
     * Adds first_name, last_name and job_title of the byline: the public contributor
     * (client_users) when there is one, otherwise the staff author (users).
     */
    public function scopeWithAuthor($query)
    {
        return $query
            ->leftJoin('users as author_staff', 'author_staff.id', '=', 'kaizen_articles.created_by')
            ->leftJoin('client_users as author_contributor', 'author_contributor.id', '=', 'kaizen_articles.contributor_id')
            ->addSelect(
                DB::raw('COALESCE(author_contributor.first_name, author_staff.first_name) as first_name'),
                DB::raw('COALESCE(author_contributor.last_name, author_staff.last_name) as last_name'),
                DB::raw('COALESCE(author_contributor.job_title, author_staff.job_title) as job_title')
            );
    }

    /**
     * The "booted" method of the model.
     * This acts as an automated trigger to mathematically guarantee a semantic URL.
     */
    protected static function booted()
    {
        static::creating(function ($article) {
            // Automatically generate a unique slug if one wasn't provided
            if (empty($article->slug)) {
                // We append a random 5-character string to prevent database collision 
                // in case two architects use the exact same title.
                $article->slug = Str::slug($article->title) . '-' . Str::random(5);
            }
        });
        
        static::updating(function ($article) {
            // Auto-heal the slug if the record is updated but somehow lost its slug
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title) . '-' . Str::random(5);
            }
        });
    }
}