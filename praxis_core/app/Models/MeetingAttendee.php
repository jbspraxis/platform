<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Someone invited to a meeting: staff (user_id), a client contact (client_user_id) or any other email (guest) */
class MeetingAttendee extends Model
{
    use HasUuids;

    protected $fillable = ['meeting_id', 'user_id', 'client_user_id', 'email', 'name', 'role', 'response'];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
