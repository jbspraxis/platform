<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TrackerUser extends Authenticatable
{
    use HasUuids;
    
    protected $table = 'tracker_users';
    
    protected $fillable = [
        'name', 'email', 'password', 'role', 'is_active'
    ];
    
    protected $hidden = [
        'password'
    ];

    public function trackers()
    {
        return $this->belongsToMany(Tracker::class, 'tracker_user_access', 'user_id', 'tracker_id');
    }

    public function assignedTasks()
    {
        return $this->hasMany(TrackerTask::class, 'assigned_to');
    }

    public function messages()
    {
        return $this->hasMany(TrackerMessage::class, 'sender_user_id');
    }
}