<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TrackerTask extends Model
{
    use HasUuids;
    
    protected $fillable = [
        'milestone_id', 'team_category', 'assigned_to', 'title', 'description', 
        'due_date', 'status', 'sort_order'
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function assignee() 
    { 
        return $this->belongsTo(TrackerUser::class, 'assigned_to'); 
    }
    
    public function milestone() 
    { 
        return $this->belongsTo(TrackerMilestone::class); 
    }

    public function messages() 
    { 
        return $this->hasMany(TrackerMessage::class, 'task_id'); 
    }
}