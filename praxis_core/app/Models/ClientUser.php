<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Someone outside JBS Praxis: a website lead (no password, cannot sign in) or a client
 * portal account (invited, sets a password). Signs in on the portal with the 'client'
 * guard; staff accounts are App\Models\User.
 */
class ClientUser extends Authenticatable
{
    use HasUuids, Notifiable, SoftDeletes;

    protected $table = 'client_users';

    protected $fillable = [
        'organization_id',
        'role_id',
        'restricted_project_id',
        'is_primary_client',
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'job_title',
        'invitation_token',
        'is_subscribed',
        'status',
        'lifecycle_stage',
        'source',
        'last_login_at',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'invitation_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'is_primary_client' => 'boolean',
            'is_subscribed' => 'boolean',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /** Portal role (audience 'client'); the primary account always counts as Client Admin. */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
