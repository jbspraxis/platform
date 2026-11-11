<?php

namespace App\Livewire\Hq\System;

use App\Models\User;
use App\Support\Access;
use App\Support\MailLocale;
use App\Support\SignIn\PasswordResets;
use App\Support\SignIn\SignIn;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
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
 * re-sends invites. Only an owner can give or take the Owner role or change an owner’s
 * account, nobody else can hand out a role with permissions they don’t hold themselves,
 * and the last active owner can’t be suspended or lose the role.
 */
#[Layout('components.layouts.hq')]
#[Title('Accounts')]
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

    /** Client Portal accounts lists one row per client; this is the client whose people are open in the dialog */
    public ?string $openClientId = null;

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
    public $primary_role_id = '';   // staff: the one HQ shows; the others only add access
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
     * Finds the JBS Praxis organisation, which separates staff accounts from client accounts.
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
        $this->openClientId = null;
        // A half-filled new account survives a look at the lists; an edit doesn't follow you out
        if ($this->editMode) {
            $this->resetForm();
        }
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
                    'u.id', 'u.first_name', 'u.last_name', 'u.email', 'u.lifecycle_stage', 'u.status', 'u.is_primary_client', 'u.last_login_at',
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
                'u.id', 'u.first_name', 'u.last_name', 'u.email', 'u.lifecycle_stage', 'u.status', 'u.avatar_path',
                'org.name as org_name', 'org.id as org_id',
                // Primary role first; the rest only add access
                DB::raw("(SELECT GROUP_CONCAT(r.name ORDER BY sr.is_primary DESC, r.is_protected DESC, r.id SEPARATOR ', ') FROM staff_roles sr JOIN roles r ON r.id = sr.role_id WHERE sr.user_id = u.id) as role_name")
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
            // Dynamically ignore the current user’s email if in edit mode
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
                        'assigned_to' => Auth::id(),
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
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

                    $this->successMessage = "Account updated.";
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
                        // The organisation’s first portal account is its primary (admin) account
                        $isPrimary = DB::table('client_users')->where('organization_id', $finalOrgId)
                            ->where('is_primary_client', 1)->whereNull('deleted_at')->doesntExist();
                        $record['is_primary_client'] = $isPrimary ? 1 : 0;
                        $record['role_id'] = $isPrimary ? Access::clientAdminRoleId() : $clientRoleId;
                        $record['source'] = 'hq';
                        $record['created_by'] = Auth::id();
                    }

                    DB::table($table)->insert($record);

                    // Send the invitation email
                    Mail::to($this->email)->locale($this->invitationLocale($isClient, $finalOrgId))
                        ->send(new \App\Mail\UserInvitation($this->first_name, $invitationToken, $isClient));

                    $this->successMessage = "Account created. We’ve sent {$this->first_name} an invitation to set a password.";
                }

                if (!$isClient) {
                    $primary = $this->primaryRoleId();
                    DB::table('staff_roles')->where('user_id', $userId)->delete();
                    DB::table('staff_roles')->insert(collect($this->role_ids)->unique()->map(fn ($r) => [
                        'user_id' => $userId, 'role_id' => (int) $r, 'is_primary' => (int) $r === $primary, 'created_at' => now(),
                    ])->values()->all());
                }
            });

            Access::flush();
            $this->showSuccessModal = true;
            $this->loadDropdownData(); // Refresh orgs in case a new one was added
            $this->loadLedger();

        } catch (Exception $e) {
            report($e);
            session()->flash('error', "We couldn’t save the account, and nothing was changed. Check the details and try again.");
        }
    }

    /**
     * Checks the staff roles being given. Only an owner may give or remove the Owner role or
     * change an owner’s account; others may only give roles whose permissions they hold;
     * the last active owner keeps the role.
     */
    private function staffRolesAllowed(): bool
    {
        $me = Auth::user();
        $iAmOwner = Access::isOwner($me);
        $roles = DB::table('roles')->where('audience', 'staff')->whereNull('deleted_at')->whereIn('id', $this->role_ids)->get();

        if ($roles->count() !== count(array_unique($this->role_ids))) {
            $this->addError('role_ids', 'One of the roles no longer exists. Reload the page and choose the roles again.');
            return false;
        }

        $target = $this->editMode ? User::find($this->editingUserId) : null;
        $targetIsOwner = $target && Access::isOwner($target);
        $givesOwner = $roles->contains(fn ($r) => (bool) $r->is_protected);

        if (!$iAmOwner && ($givesOwner || $targetIsOwner)) {
            $this->addError('role_ids', 'Only an owner can give the Owner role or change an owner’s account.');
            return false;
        }

        if ($target && $this->outranksMe($target)) {
            $this->addError('role_ids', 'You can’t change an account that has more access than yours.');
            return false;
        }

        if (!$iAmOwner) {
            $previous = $target ? DB::table('staff_roles')->where('user_id', $target->id)->pluck('role_id')->all() : [];
            foreach ($roles->whereNotIn('id', $previous) as $role) {
                $missing = DB::table('role_permissions')->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                    ->where('role_permissions.role_id', $role->id)->pluck('permissions.slug')
                    ->reject(fn ($p) => $me->can($p));
                if ($missing->isNotEmpty()) {
                    $this->addError('role_ids', "You can’t give the role {$role->name}: it has permissions you don’t have.");
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

    /**
     * The primary role among those ticked: the one chosen, else the first that isn't the protected Owner role
     * (the role a person does, rather than the access they hold).
     */
    private function primaryRoleId(): int
    {
        $ticked = collect($this->role_ids)->map(fn ($r) => (int) $r)->unique();
        if ($ticked->contains((int) $this->primary_role_id)) {
            return (int) $this->primary_role_id;
        }

        return (int) (DB::table('roles')->where('audience', 'staff')->whereIn('id', $ticked->all())
            ->orderBy('is_protected')->orderBy('id')->value('id') ?? $ticked->first());
    }

    /** Keep the primary role one of the ticked roles while the form changes */
    public function updatedRoleIds(): void
    {
        $this->primary_role_id = $this->role_ids ? (string) $this->primaryRoleId() : '';
    }

    /** True when $target (staff) holds a permission the signed-in non-owner doesn’t. */
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

    /** Invitation language: staff get English; a client gets their organisation’s preferred language (else English). */
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
            $marked = $isClient ? null : DB::table('staff_roles')->where('user_id', $user->id)->where('is_primary', 1)->value('role_id');
            $this->primary_role_id = $isClient ? '' : (string) ($marked ?: ($this->role_ids ? $this->primaryRoleId() : ''));
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

            // Send the email
            Mail::to($user->email)->locale($this->invitationLocale($isClient, $user->organization_id))
                ->send(new \App\Mail\UserInvitation($user->first_name, $token, $isClient));

            $this->successMessage = "Invitation sent again to {$user->first_name} at {$user->email}. The earlier link no longer works.";
            $this->showSuccessModal = true;
            $this->loadLedger();
        }
    }

    /**
     * Emails an active account a link to choose a new password (docs/SIGN_IN.md section 8). The link goes to the
     * account's own address, never to the person sending it. Not for an account with more access than yours.
     */
    public function sendPasswordReset($id)
    {
        abort_unless(Auth::user()->can('access.edit'), 403);

        [$user, $isClient] = $this->findAccount($id);
        $guard = $isClient ? SignIn::CLIENT : SignIn::HQ;
        $account = $user && $user->lifecycle_stage !== 'Onboarding' ? SignIn::account($guard, $user->id) : null;
        if (!$account) {
            return;
        }
        if (!$isClient && $this->outranksMe($account)) {
            session()->flash('error', 'You can’t send a reset link to an account that has more access than yours.');
            return;
        }

        try {
            PasswordResets::send($guard, $account, Auth::id());
        } catch (Exception $e) {
            report($e);
            session()->flash('error', "We couldn’t send the email to {$account->email}. Try again in a few minutes.");
            return;
        }
        DB::table('system_audit_logs')->insert([
            'user_id' => Auth::id(), 'action' => 'PASSWORD_RESET_SENT',
            'description' => "Sent {$account->first_name} {$account->last_name} a password reset link.",
            'target_table' => $isClient ? 'client_users' : 'users', 'target_id' => $account->id,
            'ip_address' => request()?->ip(), 'created_at' => now(),
        ]);

        $this->successMessage = "We’ve sent {$account->first_name} a link to choose a new password at {$account->email}. It works for "
            . PasswordResets::MINUTES . ' minutes. Their current password keeps working until they choose a new one.';
        $this->showSuccessModal = true;
    }

    /**
     * The organisation's primary contact (its Client Admin, who invites colleagues in the portal) moves to this account:
     * the first account invited becomes primary, so this puts it right when the wrong person was invited first.
     * The previous primary contact keeps the Administrator role.
     */
    public function makePrimary($id)
    {
        abort_unless(Auth::user()->can('access.edit'), 403);

        [$user, $isClient] = $this->findAccount($id);
        if (!$user || !$isClient || $user->is_primary_client || $user->status !== 'Active') {
            return;
        }
        $before = DB::table('client_users')->where('organization_id', $user->organization_id)->where('is_primary_client', 1)->whereNull('deleted_at')->value('id');
        DB::transaction(function () use ($user) {
            DB::table('client_users')->where('organization_id', $user->organization_id)->where('is_primary_client', 1)
                ->update(['is_primary_client' => 0, 'updated_at' => now()]);
            DB::table('client_users')->where('id', $user->id)
                ->update(['is_primary_client' => 1, 'role_id' => Access::clientAdminRoleId(), 'updated_at' => now()]);
        });
        DB::table('system_audit_logs')->insert([
            'user_id' => Auth::id(), 'action' => 'CLIENT_PRIMARY_CHANGED',
            'description' => "Made {$user->first_name} {$user->last_name} the primary contact of their organisation.",
            'target_table' => 'client_users', 'target_id' => $user->id,
            'old_values' => json_encode(['primary' => $before]), 'new_values' => json_encode(['primary' => $user->id]),
            'ip_address' => request()?->ip(), 'created_at' => now(),
        ]);

        Access::flush();
        $this->successMessage = "{$user->first_name} {$user->last_name} is now the primary contact. The previous one keeps the Administrator role.";
        $this->showSuccessModal = true;
        $this->loadLedger();
    }

    public function toggleUserStatus($id, $currentStatus)
    {
        abort_unless(Auth::user()->can('access.edit'), 403);

        [$user, $isClient] = $this->findAccount($id);
        if (!$user || $user->id === Auth::id()) {
            return;
        }

        if (!$isClient && $this->outranksMe(User::find($user->id))) {
            session()->flash('error', 'You can’t suspend an account that has more access than yours.');
            return;
        }
        if (!$isClient && Access::isOwner(User::find($user->id))) {
            if ($user->status === 'Active' && $this->activeOwnerCount() <= 1) {
                session()->flash('error', "This is the only active owner, so the account can’t be suspended. Make someone else an owner first.");
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
            'role_ids', 'primary_role_id', 'client_role_id', 'organization_id', 'new_org_name', 'new_org_industry',
            'editMode', 'editingUserId', 'editingIsClient'
        ]);
    }

    /** Opens the dialog with every Client Portal account of one client (only a client on the list) */
    public function openClient(string $orgId): void
    {
        $this->openClientId = $this->activeTab === 'CLIENTS'
            && collect($this->users)->contains(fn ($u) => (string) data_get($u, 'org_id') === $orgId) ? $orgId : null;
    }

    public function closeClient(): void
    {
        $this->openClientId = null;
    }

    /**
     * The Client Portal list, one row per client: its accounts, its Client Admin (primary contact), how many are
     * invited or suspended, and when anyone last signed in. Sorted by client name.
     */
    public function clientRows(): \Illuminate\Support\Collection
    {
        return collect($this->users)->groupBy(fn ($u) => (string) data_get($u, 'org_id'))->map(function ($people, $orgId) {
            $primary = $people->first(fn ($u) => ! empty(data_get($u, 'is_primary_client')));
            $last = $people->map(fn ($u) => data_get($u, 'last_login_at'))->filter()->max();

            return (object) [
                'id' => (string) $orgId,
                'name' => data_get($people->first(), 'org_name') ?: 'No organisation',
                'people' => $people->values(),
                'count' => $people->count(),
                'invited' => $people->filter(fn ($u) => data_get($u, 'lifecycle_stage') === 'Onboarding')->count(),
                'suspended' => $people->filter(fn ($u) => data_get($u, 'lifecycle_stage') !== 'Onboarding' && data_get($u, 'status') !== 'Active')->count(),
                'primary' => $primary,
                'last_login_at' => $last,
            ];
        })->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
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
        $clients = $this->activeTab === 'CLIENTS' ? $this->clientRows() : collect();

        return view('livewire.hq.system.role-management', [
            'canCreate' => Auth::user()->can('access.create'),
            'canEdit' => Auth::user()->can('access.edit'),
            'clients' => $clients,
            'openClient' => $this->openClientId === null ? null : $clients->firstWhere('id', $this->openClientId),
        ]);
    }
}
