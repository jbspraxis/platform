<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TrackerMessage extends Model
{
    use HasUuids;
    
    protected $fillable = [
        'tracker_id', 'task_id', 'parent_message_id', 'sender_type', 
        'sender_user_id', 'body', 'media_path', 'media_type'
    ];

    public function tracker() 
    { 
        return $this->belongsTo(Tracker::class); 
    }
    
    public function task() 
    { 
        return $this->belongsTo(TrackerTask::class, 'task_id'); 
    }
    
    // Links to the staff/consultant who sent it (if sender_type is 'staff')
    public function sender() 
    { 
        return $this->belongsTo(TrackerUser::class, 'sender_user_id'); 
    }
    
    // Allows nested replies within the chat
    public function parentMessage() 
    { 
        return $this->belongsTo(TrackerMessage::class, 'parent_message_id'); 
    }
}