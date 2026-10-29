<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One module x action, e.g. finance.view (catalogue in config/access.php). */
class Permission extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'slug', 'audience', 'module', 'action'];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
