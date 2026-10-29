<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TrackerMilestone extends Model
{
    use HasUuids;
    
    protected $fillable = [
        'tracker_id', 'title', 'start_date', 'end_date',
        'is_payment_trigger', 'payment_percentage', 'status', 'sort_order'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_payment_trigger' => 'boolean',
        'payment_percentage' => 'integer',
    ];

    public function tracker() 
    { 
        return $this->belongsTo(Tracker::class); 
    }

    public function tasks() 
    { 
        return $this->hasMany(TrackerTask::class, 'milestone_id')->orderBy('sort_order'); 
    }
}