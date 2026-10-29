<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasUuids, Notifiable;

    /**
     * "JBS Praxis Editorial": a staff account that cannot sign in, used as the author of
     * website articles and case studies (CoreSeeder creates it).
     */
    public const EDITORIAL_ID = '7e0d1a2b-0000-4000-8000-0000000ed170';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organization_id',
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'job_title',
        'mfa_secret',
        'status',
        'lifecycle_stage',
        'last_login_at', // Required for Security Monitoring
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Virtual attribute to get the full name dynamically for the UI.
     */
    public function getNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Staff roles (several allowed; permissions add up). Checks go through
     * $user->can('module.action'), see App\Support\Access.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'staff_roles')->where('roles.audience', 'staff');
    }

    /** Role names for display, e.g. "Delivery Lead, Engineer". */
    public function getRoleNamesAttribute(): string
    {
        return $this->roles->pluck('name')->implode(', ');
    }
}