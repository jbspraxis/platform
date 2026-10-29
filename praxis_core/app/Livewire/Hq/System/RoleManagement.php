<?php

namespace App\Livewire\Hq\System;

use App\Models\User;
use App\Support\Access;
use App\Support\MailLocale;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Exception;

/**
 * Accounts: staff (users, organisation JBS Praxis, one or more staff roles) and client
 * portal accounts (client_users, any other organisation, one client role) from one screen.
 *
 * access.view lists accounts; access.create invites; access.edit changes, suspends and
 * re-sends invites. Only an owner can give or take the Owner role or change an owner's
 * account, nobody else can hand out a role with permissions they don't hold themselves,
 * and the last active owner can't be suspended or lose the role.
 */
#[Layout('components.layouts.hq')]
class RoleManagement extends Component
{
    // --- DATAGRID & UI STATE ---
    public $activeTab = 'CREATE'; // Tabs: CREATE, INTERNAL, CLIENTS
    public $users = [];
    public $staffRoles = [];
    public $clientRoles = [];
    public $organizations = [];

    #[Locked]
    public $jbsOrgId = null;
    public $showSuccessModal = false;
    public $successMessage = '';

    // --- EDIT STATE ---
    public $editMode = false;
    #[Locked]
    public $editingUserId = null;
    #[Locked]
    public $editingIsClient = false;

    // --- FORM STATE: USER IDENTITY ---
    public $first_name = '';
    public $last_name = '';
    public $email = '';
    public $phone = '';
    public $job_title = '';

    // --- FORM STATE: ACCESS ---
    public $role_ids = [];          // staff: one or more staff roles
    public $client_role_id = '';    // client: one portal role
    public $organization_id = '';

    // --- FORM STATE: INLINE ORG CREATION ---
    public $new_org_name = '';
    public $new_org_industry = '';
    public $new_org_country = 'Nigeria';

    public function mount()
    {
        abort_unless(Auth::user()?->can('access.view'), 403);
        $this->resolveJbsPraxisId();
        $this->loadDropdownData();
        $this->activeTab = Auth::user()->can('access.create') ? 'CREATE' : 'INTERNAL';
        $this->loadLedger();
    }

    /**
     * Identifies the core JBS Praxis organization to separate Internal vs Client ledgers.
     */
    private function resolveJbsPraxisId()
    {
        $this->jbsOrgId = DB::table('organizations')
            ->where('name', 'JBS Praxis')
            ->value('id');
    }

    public function loadDropdownData()
    {
        $this->staffRoles = DB::table('roles')->where('audience', 'staff')->whereNull('deleted_at')->orderByDesc('is_protected')->orderBy('id')->get();
        $this->clientRoles = DB::table('roles')->where('audience', 'client')->whereNull('deleted_at')->orderBy('id')->get();
        $this->organizations = DB::table('organizations')->orderBy('name', 'asc')->get();
    }

    public function setTab($tab)
    {
        if (!in_array($tab, ['CREATE', 'INTERNAL', 'CLIENTS'], true)) return;
        if ($tab === 'CREATE' && !Auth::user()->can('access.create')) return;
        $this->activeTab = $tab;
        $this->resetForm();
        $this->loadLedger();
    }

    /** A staff account belongs to JBS Praxis; any other organisation means a client account. */
    public function isClientForm(): bool
    {
        return $this->organization_id !== '' && $this->organization_id !== $this->jbsOrgId;
    }

    public function loadLedger()
    {
        if ($this->activeTab === 'CREATE') {
            return;
        }

        if ($this->activeTab === 'CLIENTS') {
            // Portal accounts only; website leads live in the CRM
            $this->users = DB::table('client_users as u')
                ->leftJoin('organizations as org', 'u.organization_id', '=', 'org.id')
                ->leftJoin('roles as r', 'u.role_id', '=', 'r.id')
                ->whereIn('u.lifecycle_stage', ['Onboarding', 'Active'])
                ->whereNull('u.deleted_at')
                ->select(
                    'u.id', 'u.first_name', 'u.last_name', 'u.email', 'u.lifecycle_stage', 'u.status',
                    'org.name as org_name', 'org.id as org_id',
                    DB::raw("CASE WHEN u.is_primary_client = 1 THEN 'Client Admin (primary)' ELSE COALESCE(r.name, 'No role') END as role_name")
                )
                ->orderBy('u.created_at', 'desc')
                ->get();
            return;
        }

        // Staff; the Editorial author is a system account, not a person
        $this->users = DB::table('users as u')
            ->leftJoin('organizations as org', 'u.organization_id', '=', 'org.id')
            ->where('u.lifecycle_stage', '!=', 'System')
            ->select(
                'u.id', 'u.first_name', 'u.last_name', 'u.email', 'u.lifecycle_stage', 'u.status',
                'org.name as org_name', 'org.id as org_id',
                DB::raw("(SELECT GROUP_CONCAT(r.name ORDER BY r.is_protected DESC, r.id SEPARATOR ', ') FROM staff_roles sr JOIN roles r ON r.id = sr.role_id WHERE sr.user_id = u.id) as role_name")
            )
            ->orderBy('u.created_at', 'desc')
            ->get();
    }

    public function saveUser()
    {
        abort_unless(Auth::user()->can($this->editMode ? 'access.edit' : 'access.create'), 403);

        $isClient = $this->editMode ? $this->editingIsClient : $this->isClientForm();
        $table = $isClient ? 'client_users' : 'users';

        $this->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            // Dynamically ignore the current user's email if in edit mode
            'email' => "required|email|unique:{$table},email," . ($this->editingUserId ?? 'NULL') . ',id',
            'role_ids' => $isClient ? 'nullable|array' : 'required|array|min:1',
            'role_ids.*' => 'integer',
            'client_role_id' => 'nullable|integer',
            'organization_id' => 'required|string',
        ], ['role_ids.required' => 'Give the staff member at least one role.']);

        // An account stays on its side: staff in JBS Praxis, clients in a client organisation
        if ($this->editMode && $isClient === ($this->organization_id === $this->jbsOrgId)) {
            $this->addError('organization_id', $isClient
                ? 'A client account cannot move into JBS Praxis. Create a staff account instead.'
                : 'A staff account stays in JBS Praxis. Create a client account instead.');
            return;
        }

        if (!$isClient && !$this->staffRolesAllowed()) {
            return;
        }
        $clientRoleId = $isClient ? $this->validClientRoleId() : null;

        try {
            DB::transaction(function () use ($isClient, $table, $clientRoleId) {
                $finalOrgId = $this->organization_id;

                // 1. Handle Inline Organization Creation
                if ($this->organization_id === 'NEW') {
                    $this->validate([
                        'new_org_name' => 'required|string|max:150',
                        'new_org_industry' => 'required|string|max:100',
                    ]);

                    $finalOrgId = Str::uuid()->toString();

                    DB::table('organizations')->insert([
                        'id' => $finalOrgId,
                        'name' => $this->new_org_name,
                        'industry' => $this->new_org_industry,
                        'country' => $this->new_org_country,
                        'status' => 'Lead',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                $identity = [
                    'organization_id' => $finalOrgId,
                    'first_name' => $this->first_name,
                    'last_name' => $this->last_name,
                    'email' => $this->email,
                    'phone' => $this->phone,
                    'job_title' => $this->job_title,
                ];

                if ($this->editMode && $this->editingUserId) {
                    // UPDATE EXISTING USER (a primary client account keeps Client Admin)
                    if ($isClient) {
                        $isPrimary = (bool) DB::table('client_users')->where('id', $this->editingUserId)->value('is_primary_client');
                        $identity['role_id'] = $isPrimary ? Access::clientAdminRoleId() : $clientRoleId;
                    }
                    DB::table($table)->where('id', $this->editingUserId)->update($identity + ['updated_at' => now()]);
                    $userId = $this->editingUserId;

                    $this->successMessage = "Operator record and access updated successfully.";
                } else {
                    // CREATE NEW USER: no password until they open the setup link
                    $userId = Str::uuid()->toString();
                    $invitationToken = Str::random(60);
                    $record = $identity + [
                        'id' => $userId,
                        'password' => $isClient ? null : '', // staff column is NOT NULL; an empty hash never matches
                        'invitation_token' => $invitationToken,
                        'lifecycle_stage' => 'Onboarding',
                        'status' => 'Active',
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    if ($isClient) {
                        // The organisation's first portal account is its primary (admin) account
                        $isPrimary = DB::table('client_users')->where('organization_id', $finalOrgId)
                            ->where('is_primary_client', 1)->whereNull('deleted_at')->doesntExist();
                        $record['is_primary_client'] = $isPrimary ? 1 : 0;
                        $record['role_id'] = $isPrimary ? Access::clientAdminRoleId() : $clientRoleId;
                        $record['source'] = 'hq';
                        $record['created_by'] = Auth::id();
                    }

                    DB::table($table)->insert($record);

                    // Dispatch Invitation Email
                    Mail::to($this->email)->locale($this->invitationLocale($isClient, $finalOrgId))
                        ->send(new \App\Mail\UserInvitation($this->first_name, $invitationToken, $isClient));

                    $this->successMessage = "User generated and cryptographic invitation transmitted successfully.";
                }

                if (!$isClient) {
                    DB::table('staff_roles')->where('user_id', $userId)->delete();
                    DB::table('staff_roles')->insert(collect($this->role_ids)->unique()->map(fn ($r) => ['user_id' => $userId, 'role_id' => (int) $r, 'created_at' => now()])->all());
                }
            });

            Access::flush();
            $this->showSuccessModal = true;
            $this->loadDropdownData(); // Refresh orgs in case a new one was added
            $this->loadLedger();

        } catch (Exception $e) {
            session()->flash('error', "System Matrix Error: " . $e->getMessage());
        }
    }

    /**
     * Checks the staff roles being given. Only an owner may give or remove the Owner role or
     * change an owner's account; others may only give roles whose permissions they hold;
     * the last active owner keeps the role.
     */
    private function staffRolesAllowed(): bool
    {
        $me = Auth::user();
        $iAmOwner = Access::isOwner($me);
        $roles = DB::table('roles')->where('audience', 'staff')->whereNull('deleted_at')->whereIn('id', $this->role_ids)->get();

        if ($roles->count() !== count(array_unique($this->role_ids))) {
            $this->addError('role_ids', 'Unknown role.');
            return false;
        }

        $target = $this->editMode ? User::find($this->editingUserId) : null;
        $targetIsOwner = $target && Access::isOwner($target);
        $givesOwner = $roles->contains(fn ($r) => (bool) $r->is_protected);

        if (!$iAmOwner && ($givesOwner || $targetIsOwner)) {
            $this->addError('role_ids', 'Only an owner can give the Owner role or change an owner\'s account.');
            return false;
        }

        if ($target && $this->outranksMe($target)) {
            $this->addError('role_ids', 'You can\'t change an account that has more access than yours.');
            return false;
        }

        if (!$iAmOwner) {
            $previous = $target ? DB::table('staff_roles')->where('user_id', $target->id)->pluck('role_id')->all() : [];
            foreach ($roles->whereNotIn('id', $previous) as $role) {
                $missing = DB::table('role_permissions')->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                    ->where('role_permissions.role_id', $role->id)->pluck('permissions.slug')
                    ->reject(fn ($p) => $me->can($p));
                if ($missing->isNotEmpty()) {
                    $this->addError('role_ids', "You can't give the role {$role->name}: it has permissions you don't have.");
                    return false;
                }
            }
        }

        if ($targetIsOwner && !$givesOwner && $this->activeOwnerCount() <= 1) {
            $this->addError('role_ids', 'This is the only active owner. Make someone else an owner first.');
            return false;
        }

        return true;
    }

    /** True when $target (staff) holds a permission the signed-in non-owner doesn't. */
    private function outranksMe(User $target): bool
    {
        $me = Auth::user();
        if (Access::isOwner($me)) {
            return false;
        }
        $theirs = Access::permissionsFor($target);

        return in_array('*', $theirs, true) || collect($theirs)->contains(fn ($p) => !$me->can($p));
    }

    private function validClientRoleId(): ?int
    {
        $id = (int) $this->client_role_id;
        if ($id && DB::table('roles')->where('id', $id)->where('audience', 'client')->whereNull('deleted_at')->exists()) {
            return $id;
        }

        // Default for a new team member
        return DB::table('roles')->where('audience', 'client')->where('slug', 'client-member')->value('id');
    }

    private function activeOwnerCount(): int
    {
        return DB::table('staff_roles')->join('roles', 'roles.id', '=', 'staff_roles.role_id')
            ->join('users', 'users.id', '=', 'staff_roles.user_id')
            ->where('roles.is_protected', 1)->where('users.status', 'Active')
            ->distinct()->count('users.id');
    }

    /** Invitation language: staff get English; a client gets their organisation's preferred language (else English). */
    private function invitationLocale(bool $isClient, $organizationId): string
    {
        return MailLocale::forRecipient($isClient
            ? DB::table('organizations')->where('id', $organizationId)->value('preferred_language')
            : null);
    }

    /** Finds an account on either side: [row, isClient]. */
    private function findAccount($id): array
    {
        $user = DB::table('users')->where('id', $id)->first();
        if ($user) {
            return [$user, false];
        }

        return [DB::table('client_users')->where('id', $id)->whereNull('deleted_at')->first(), true];
    }

    public function editUser($id)
    {
        abort_unless(Auth::user()->can('access.edit'), 403);

        [$user, $isClient] = $this->findAccount($id);
        if ($user) {
            $this->editMode = true;
            $this->editingUserId = $user->id;
            $this->editingIsClient = $isClient;
            $this->first_name = $user->first_name;
            $this->last_name = $user->last_name;
            $this->email = $user->email;
            $this->phone = $user->phone;
            $this->job_title = $user->job_title;
            $this->role_ids = $isClient ? [] : DB::table('staff_roles')->where('user_id', $user->id)->pluck('role_id')->map(fn ($r) => (string) $r)->all();
            $this->client_role_id = $isClient ? (string) ($user->role_id ?? '') : '';
            $this->organization_id = $user->organization_id;

            // Switch view to the form tab directly without calling resetForm()
            $this->activeTab = 'CREATE';
        }
    }

    public function resendInvitation($id)
    {
        abort_unless(Auth::user()->can('access.edit'), 403);

        [$user, $isClient] = $this->findAccount($id);

        if ($user && $user->lifecycle_stage === 'Onboarding') {
            // Generate a fresh token for maximum security
            $token = Str::random(60);

            DB::table($isClient ? 'client_users' : 'users')->where('id', $user->id)->update([
                'invitation_token' => $token,
                'updated_at' => now()
            ]);

            // Transmit the email
            Mail::to($user->email)->locale($this->invitationLocale($isClient, $user->organization_id))
                ->send(new \App\Mail\UserInvitation($user->first_name, $token, $isClient));

            $this->successMessage = "Zero-Knowledge Init re-transmitted to " . strtoupper($user->first_name) . ".";
            $this->showSuccessModal = true;
            $this->loadLedger();
        }
    }

    public function toggleUserStatus($id, $currentStatus)
    {
        abort_unless(Auth::user()->can('access.edit'), 403);

        [$user, $isClient] = $this->findAccount($id);
        if (!$user || $user->id === Auth::id()) {
            return;
        }

        if (!$isClient && $this->outranksMe(User::find($user->id))) {
            session()->flash('error', 'You can\'t suspend an account that has more access than yours.');
            return;
        }
        if (!$isClient && Access::isOwner(User::find($user->id))) {
            if ($user->status === 'Active' && $this->activeOwnerCount() <= 1) {
                session()->flash('error', 'This is the only active owner and cannot be suspended.');
                return;
            }
        }

        $newStatus = ($user->status === 'Active') ? 'Suspended' : 'Active';
        // A new remember token ends 'remember me' logins; open sessions end on the next request
        DB::table($isClient ? 'client_users' : 'users')->where('id', $user->id)->update([
            'status' => $newStatus,
            'remember_token' => Str::random(60),
            'updated_at' => now()
        ]);
        Access::flush();
        $this->loadLedger();
    }

    public function resetForm()
    {
        $this->reset([
            'first_name', 'last_name', 'email', 'phone', 'job_title',
            'role_ids', 'client_role_id', 'organization_id', 'new_org_name', 'new_org_industry',
            'editMode', 'editingUserId', 'editingIsClient'
        ]);
    }

    public function closeSuccessModal()
    {
        $this->showSuccessModal = false;
        if ($this->activeTab === 'CREATE') {
            $this->resetForm();
        }
    }

    public function render()
    {
        return view('livewire.hq.system.role-management', [
            'canCreate' => Auth::user()->can('access.create'),
            'canEdit' => Auth::user()->can('access.edit'),
        ]);
    }
}
