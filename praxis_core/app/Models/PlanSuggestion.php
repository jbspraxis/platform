<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** What the planner proposed for a milestone (PR 5b): a new task, an edit to an existing task, or a note on one */
class PlanSuggestion extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $casts = ['decided_at' => 'datetime', 'position' => 'integer'];
}
