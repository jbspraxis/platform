<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A staff role (audience 'staff', held through staff_roles) or a client portal role
 * (audience 'client', client_users.role_id). See config/access.php and App\Support\Access.
 */
class Role extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'audience', 'description', 'is_protected'];

    protected function casts(): array
    {
        return ['is_protected' => 'boolean'];
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function staff()
    {
        return $this->belongsToMany(User::class, 'staff_roles');
    }

    public function clients()
    {
        return $this->hasMany(ClientUser::class);
    }
}
