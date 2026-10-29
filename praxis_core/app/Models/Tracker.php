<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Tracker extends Model
{
    use HasUuids;
    
    protected $fillable = [
        'unique_token', 'pin_code', 'project_name', 'client_name', 
        'client_company', 'status', 'start_date', 'target_end_date',
        'progress_percentage', 'created_by'
    ];

    protected $casts = [
        'start_date' => 'date',
        'target_end_date' => 'date',
        'progress_percentage' => 'decimal:2',
    ];

    public function milestones() 
    { 
        return $this->hasMany(TrackerMilestone::class)->orderBy('sort_order'); 
    }
    
    public function tasks() 
    { 
        return $this->hasManyThrough(TrackerTask::class, TrackerMilestone::class); 
    }
    
    public function messages() 
    { 
        return $this->hasMany(TrackerMessage::class)->orderBy('created_at', 'asc'); 
    }
    
    public function assignedStaff() 
    { 
        return $this->belongsToMany(TrackerUser::class, 'tracker_user_access', 'tracker_id', 'user_id'); 
    }
}