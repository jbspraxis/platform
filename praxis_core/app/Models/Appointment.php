<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 
        'host_id', 
        'title', 
        'meeting_link', 
        'start_time', 
        'end_time', 
        'status'
    ];
}