<?php

namespace App\Livewire\Hq\System;

use App\Models\Role;
use App\Support\Access;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Roles & Permissions: staff roles (HQ) and client portal roles, each a set of
 * module x action permissions from config/access.php.
 *
 * - access.view sees everything read-only; access.create / edit / delete change roles.
 * - The Owner role is protected: always every permission, never edited or deleted.
 * - Nobody but an owner can grant or remove a staff permission they don't hold themselves,
 *   so access.edit can't be used to climb.
 * - A role still assigned to someone can't be deleted; Client Admin can't be deleted
 *   because every client organisation's primary account relies on it.
 */
#[Layout('components.layouts.hq')]
#[Title('Roles and permissions')]
class RolesPermissions extends Component
{
    #[Locked]
    public string $audience = 'staff';

    #[Locked]
    public ?int $roleId = null;

    public string $name = '';
    public string $description = '';
    public array $selected = [];
    public string $notice = '';

    public function mount()
    {
        abort_unless(Auth::user()?->can('access.view'), 403);
        $this->selectFirst();
    }

    public function setAudience(string $audience)
    {
        if (! in_array($audience, ['staff', 'client'], true)) {
            return;
        }
        $this->audience = $audience;
        $this->resetErrorBag();
        $this->selectFirst();
    }

    private function selectFirst(): void
    {
        $id = Role::where('audience', $this->audience)->orderBy('id')->value('id');
        $id ? $this->selectRole($id) : $this->newRole();
    }

    public function selectRole($id)
    {
        $role = Role::where('audience', $this->audience)->findOrFail($id);

        $this->roleId = $role->id;
        $this->name = $role->name;
        $this->description = $role->description ?? '';
        $this->selected = $role->is_protected
            ? array_keys($this->catalogue())
            : $role->permissions()->where('permissions.audience', $this->audience)->pluck('slug')->all();
        $this->notice = '';
        $this->resetErrorBag();
    }

    public function newRole()
    {
        if (! Auth::user()->can('access.create')) {
            return;
        }
        $this->roleId = null;
        $this->name = '';
        $this->description = '';
        $this->selected = [];
        $this->notice = '';
        $this->resetErrorBag();
    }

    /** Tick or untick every action of one module. */
    public function toggleModule(string $module)
    {
        $slugs = array_keys(array_filter($this->catalogue(), fn ($p) => $p['module'] === $module));
        $this->selected = count(array_diff($slugs, $this->selected)) === 0
            ? array_values(array_diff($this->selected, $slugs))
            : array_values(array_unique(array_merge($this->selected, $slugs)));
    }

    public function save()
    {
        $user = Auth::user();
        $creating = $this->roleId === null;
        abort_unless($user->can($creating ? 'access.create' : 'access.edit'), 403);

        $role = $creating ? null : Role::where('audience', $this->audience)->findOrFail($this->roleId);
        if ($role?->is_protected) {
            $this->addError('name', 'The Owner role always has every permission and cannot be changed.');
            return;
        }

        $this->validate([
            'name' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
        ]);

        $slug = Str::slug($this->name) ?: 'role';
        $taken = Role::withTrashed()->where('audience', $this->audience)->where('slug', $slug)
            ->when($role, fn ($q) => $q->where('id', '!=', $role->id))->exists();
        if ($taken && ($creating || $role->slug !== $slug)) {
            $this->addError('name', 'Another role already has this name.');
            return;
        }

        $wanted = array_values(array_intersect($this->selected, array_keys($this->catalogue())));

        if ($this->audience === 'staff' && ! Access::isOwner($user)) {
            $before = $role ? $role->permissions()->pluck('slug')->all() : [];
            $changed = array_merge(array_diff($wanted, $before), array_diff($before, $wanted));
            $notHeld = array_values(array_filter($changed, fn ($p) => ! $user->can($p)));
            if ($notHeld) {
                $names = array_map(fn ($p) => $this->catalogue()[$p]['name'] ?? $p, $notHeld);
                $this->addError('selected', 'You can only grant or remove permissions you have yourself. Not allowed: ' . implode('; ', $names) . '.');
                return;
            }
        }

        DB::transaction(function () use ($role, $slug, $wanted, $creating) {
            if ($creating) {
                $role = Role::create([
                    'name' => $this->name, 'slug' => $slug, 'audience' => $this->audience,
                    'description' => $this->description ?: null, 'is_protected' => false,
                ]);
                DB::table('roles')->where('id', $role->id)->update(['created_by' => Auth::id()]);
            } else {
                // The slug is the role's stable key (starter roles are found by it), so renaming keeps it
                $role->update(['name' => $this->name, 'description' => $this->description ?: null]);
                DB::table('roles')->where('id', $role->id)->update(['updated_by' => Auth::id()]);
            }

            $ids = DB::table('permissions')->whereIn('slug', $wanted)->where('audience', $this->audience)->pluck('id');
            DB::table('role_permissions')->where('role_id', $role->id)->delete();
            DB::table('role_permissions')->insert($ids->map(fn ($id) => ['role_id' => $role->id, 'permission_id' => $id])->all());

            $this->roleId = $role->id;
        });

        Access::flush();
        $this->selectRole($this->roleId);
        $this->notice = $creating ? 'Role created.' : 'Role saved.';
    }

    public function deleteRole()
    {
        abort_unless(Auth::user()->can('access.delete'), 403);

        $role = Role::where('audience', $this->audience)->findOrFail($this->roleId);
        if ($role->is_protected) {
            $this->addError('name', 'The Owner role cannot be deleted.');
            return;
        }
        if ($role->audience === 'client' && $role->slug === config('access.client_admin_role')) {
            $this->addError('name', 'Client Admin cannot be deleted: every client organisation\'s primary account uses it.');
            return;
        }
        $inUse = $this->memberCount($role);
        if ($inUse > 0) {
            $this->addError('name', "This role is held by {$inUse} " . ($inUse === 1 ? 'account' : 'accounts') . '. Give them another role first.');
            return;
        }

        DB::transaction(function () use ($role) {
            DB::table('role_permissions')->where('role_id', $role->id)->delete();
            $role->forceDelete();
        });

        Access::flush();
        $this->selectFirst();
        $this->notice = 'Role deleted.';
    }

    /** This audience's permissions: slug => [module, action, name]. */
    private function catalogue(): array
    {
        return array_filter(Access::catalogue(), fn ($p) => $p['audience'] === $this->audience);
    }

    private function memberCount($role): int
    {
        if ($role->audience === 'staff') {
            return DB::table('staff_roles')->join('users', 'users.id', '=', 'staff_roles.user_id')
                ->where('staff_roles.role_id', $role->id)->count();
        }

        return DB::table('client_users')->whereNull('deleted_at')
            ->where(function ($q) use ($role) {
                $q->where('role_id', $role->id);
                if ($role->slug === config('access.client_admin_role')) {
                    $q->orWhere('is_primary_client', 1);
                }
            })->count();
    }

    public function render()
    {
        $roles = Role::where('audience', $this->audience)->orderByDesc('is_protected')->orderBy('id')->get()
            ->map(function ($role) {
                $role->member_count = $this->memberCount($role);
                $role->permission_count = $role->is_protected ? count($this->catalogue()) : $role->permissions()->count();
                return $role;
            });

        $current = $this->roleId ? $roles->firstWhere('id', $this->roleId) : null;

        $members = collect();
        if ($current && $this->audience === 'staff') {
            $members = DB::table('users')->join('staff_roles', 'staff_roles.user_id', '=', 'users.id')
                ->where('staff_roles.role_id', $current->id)->orderBy('users.first_name')
                ->select('users.first_name', 'users.last_name', 'users.status')->get();
        }

        $user = Auth::user();

        return view('livewire.hq.system.roles-permissions', [
            'roles' => $roles,
            'current' => $current,
            'members' => $members,
            'modules' => config("access.modules.{$this->audience}"),
            'actions' => config('access.actions'),
            'prefix' => $this->audience === 'client' ? Access::CLIENT_PREFIX : '',
            'canCreate' => $user->can('access.create'),
            'canEdit' => $user->can($this->roleId ? 'access.edit' : 'access.create') && ! ($current?->is_protected),
            'canDelete' => $user->can('access.delete') && $current && ! $current->is_protected,
        ]);
    }
}
