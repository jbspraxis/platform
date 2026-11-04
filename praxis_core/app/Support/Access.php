<?php

namespace App\Support;

use App\Models\ClientUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Who may do what (config/access.php).
 *
 *   Access::allows($user, 'finance.view')            staff (User)
 *   Access::allows($client, 'portal.billing.create') client (ClientUser)
 *   $user->can('finance.view'), @can('finance.view'), route middleware can:finance.view
 *
 * Staff permissions are the union of their roles; the protected Owner role has all of
 * them. A client has one role; an organisation's primary account is always Client Admin.
 * Results are cached for the request; call Access::flush() after changing roles.
 */
class Access
{
    public const CLIENT_PREFIX = 'portal.';

    private static array $cache = [];

    /** Every permission in the catalogue: slug => [audience, module, action, name]. */
    public static function catalogue(): array
    {
        $out = [];
        foreach (config('access.modules') as $audience => $modules) {
            foreach ($modules as $module => $def) {
                foreach ($def['actions'] as $action) {
                    $slug = self::slug($audience, $module, $action);
                    $out[$slug] = [
                        'audience' => $audience,
                        'module' => $module,
                        'action' => $action,
                        'name' => $def['label'] . ': ' . config("access.actions.{$action}", ucfirst($action)),
                        // A module the owner keeps to themselves is never swept up by a starter role's "*"
                        'wildcard' => ($def['wildcard'] ?? true) !== false,
                    ];
                }
            }
        }

        return $out;
    }

    public static function slug(string $audience, string $module, string $action): string
    {
        return ($audience === 'client' ? self::CLIENT_PREFIX : '') . "{$module}.{$action}";
    }

    public static function isPermission(string $ability): bool
    {
        return isset(self::catalogue()[$ability]);
    }

    public static function allows($user, string $permission): bool
    {
        if (! $user || ! self::isPermission($permission)) {
            return false;
        }

        $granted = self::permissionsFor($user);

        return in_array('*', $granted, true) || in_array($permission, $granted, true);
    }

    /** Permission slugs the account has ('*' = all of its audience). */
    public static function permissionsFor($user): array
    {
        if ($user instanceof User) {
            if ($user->status !== 'Active') {
                return [];
            }
            $key = 'staff:' . $user->id;
            if (! isset(self::$cache[$key])) {
                $roleIds = DB::table('staff_roles')->where('user_id', $user->id)->pluck('role_id');
                self::$cache[$key] = self::grantedTo($roleIds->all(), 'staff');
            }

            return self::$cache[$key];
        }

        if ($user instanceof ClientUser) {
            if ($user->status !== 'Active') {
                return [];
            }
            $key = 'client:' . $user->id;
            if (! isset(self::$cache[$key])) {
                $roleId = $user->is_primary_client ? self::clientAdminRoleId() : $user->role_id;
                self::$cache[$key] = $roleId ? self::grantedTo([$roleId], 'client') : [];
            }

            return self::$cache[$key];
        }

        return [];
    }

    private static function grantedTo(array $roleIds, string $audience): array
    {
        if (! $roleIds) {
            return [];
        }

        $roles = DB::table('roles')->whereIn('id', $roleIds)->where('audience', $audience)->whereNull('deleted_at')->get();
        if ($roles->contains(fn ($r) => (bool) $r->is_protected)) {
            return ['*'];
        }

        return DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->whereIn('role_permissions.role_id', $roles->pluck('id'))
            ->where('permissions.audience', $audience)
            ->distinct()
            ->pluck('permissions.slug')
            ->all();
    }

    public static function clientAdminRoleId(): ?int
    {
        return self::$cache['client-admin-role'] ??= DB::table('roles')
            ->where('audience', 'client')->where('slug', config('access.client_admin_role'))->value('id');
    }

    public static function isOwner(User $user): bool
    {
        return DB::table('staff_roles')->join('roles', 'roles.id', '=', 'staff_roles.role_id')
            ->where('staff_roles.user_id', $user->id)->where('roles.is_protected', 1)->exists();
    }

    /** Ids of active staff who have $permission (owners always), e.g. to send a notification. */
    public static function staffWith(string $permission): array
    {
        $entry = self::catalogue()[$permission] ?? null;
        if (! $entry || $entry['audience'] !== 'staff') {
            return [];
        }

        $roleIds = DB::table('roles')->where('audience', 'staff')->whereNull('deleted_at')
            ->where(function ($q) use ($permission) {
                $q->where('is_protected', 1)->orWhereIn('id', DB::table('role_permissions')
                    ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                    ->where('permissions.slug', $permission)->select('role_permissions.role_id'));
            })->pluck('id');

        return DB::table('users')->join('staff_roles', 'staff_roles.user_id', '=', 'users.id')
            ->whereIn('staff_roles.role_id', $roleIds)->where('users.status', 'Active')
            ->distinct()->pluck('users.id')->all();
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    /** Write the catalogue to the permissions table (adds new ones, renames, removes retired ones). */
    public static function sync(): array
    {
        $catalogue = self::catalogue();
        foreach ($catalogue as $slug => $p) {
            DB::table('permissions')->updateOrInsert(['slug' => $slug], [
                'name' => $p['name'], 'audience' => $p['audience'], 'module' => $p['module'], 'action' => $p['action'],
            ]);
        }
        $removed = DB::table('permissions')->whereNotIn('slug', array_keys($catalogue))->delete();
        self::flush();

        return ['permissions' => count($catalogue), 'removed' => $removed];
    }

    /** Create the starter roles that don't exist yet; existing roles are left as the owner set them. */
    public static function seedStarterRoles(): array
    {
        $created = [];
        $permissionIds = DB::table('permissions')->pluck('id', 'slug');

        foreach (config('access.starter_roles') as $audience => $roles) {
            foreach ($roles as $slug => $def) {
                if (DB::table('roles')->where('audience', $audience)->where('slug', $slug)->exists()) {
                    continue;
                }
                $roleId = DB::table('roles')->insertGetId([
                    'name' => $def['name'], 'slug' => $slug, 'audience' => $audience,
                    'description' => $def['description'] ?? null, 'is_protected' => ! empty($def['protected']),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $rows = [];
                foreach (self::expandGrant($def['grant'], $audience) as $perm) {
                    if (isset($permissionIds[$perm])) {
                        $rows[] = ['role_id' => $roleId, 'permission_id' => $permissionIds[$perm]];
                    }
                }
                DB::table('role_permissions')->insertOrIgnore($rows);
                $created[] = "{$audience}:{$slug}";
            }
        }
        self::flush();

        return $created;
    }

    /**
     * "finance.*", "*.view", "*" -> full slugs for the audience. A module marked 'wildcard' => false in
     * config/access.php is only granted when a pattern names it ("letters.view"), never by "*": the owner
     * decides who else sees it, on Roles and permissions. The Owner role is protected and holds every
     * permission whatever this says.
     */
    public static function expandGrant(array $patterns, string $audience): array
    {
        $out = [];
        foreach (self::catalogue() as $slug => $p) {
            if ($p['audience'] !== $audience) {
                continue;
            }
            foreach ($patterns as $pattern) {
                [$m, $a] = array_pad(explode('.', $pattern, 2), 2, '*');
                if ($m === '*' && ($p['wildcard'] ?? true) === false) {
                    continue;
                }
                if (($m === '*' || $m === $p['module']) && ($a === '*' || $a === $p['action'])) {
                    $out[] = $slug;
                    break;
                }
            }
        }

        return $out;
    }
}
