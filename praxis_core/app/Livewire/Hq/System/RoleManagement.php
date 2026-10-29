<?php

namespace App\Livewire\Hq\System;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Exception;

/**
 * Staff accounts (users, organisation JBS Praxis, with a role) and client portal
 * accounts (client_users, any other organisation, no staff role) from one screen.
 */
#[Layout('components.layouts.hq')]
class RoleManagement extends Component
{
    // --- DATAGRID & UI STATE ---
    public $activeTab = 'CREATE'; // Tabs: CREATE, INTERNAL, CLIENTS
    public $users = [];
    public $roles = [];
    public $organizations = [];
    public $permissionsList = [];

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

    // --- FORM STATE: ACCESS & LIMITS ---
    public $role_id = '';
    public $organization_id = '';
    public $selected_permissions = [];

    // --- FORM STATE: INLINE ORG CREATION ---
    public $new_org_name = '';
    public $new_org_industry = '';
    public $new_org_country = 'Nigeria';

    public function mount()
    {
        $this->resolveJbsPraxisId();
        $this->loadDropdownData();
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
        $this->roles = DB::table('roles')->orderBy('clearance_level', 'desc')->get();
        $this->organizations = DB::table('organizations')->orderBy('name', 'asc')->get();
    }

    public function setTab($tab)
    {
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
                ->whereIn('u.lifecycle_stage', ['Onboarding', 'Active'])
                ->whereNull('u.deleted_at')
                ->select(
                    'u.id', 'u.first_name', 'u.last_name', 'u.email', 'u.lifecycle_stage', 'u.status',
                    'org.name as org_name', 'org.id as org_id',
                    DB::raw("CASE WHEN u.is_primary_client = 1 THEN 'Primary client' ELSE 'Client member' END as role_name"),
                    DB::raw('NULL as clearance_level')
                )
                ->orderBy('u.created_at', 'desc')
                ->get();
            return;
        }

        // Staff; the Editorial author is a system account, not a person
        $this->users = DB::table('users as u')
            ->leftJoin('organizations as org', 'u.organization_id', '=', 'org.id')
            ->leftJoin('roles as r', 'u.role_id', '=', 'r.id')
            ->where('u.lifecycle_stage', '!=', 'System')
            ->select(
                'u.id', 'u.first_name', 'u.last_name', 'u.email', 'u.lifecycle_stage', 'u.status',
                'org.name as org_name', 'org.id as org_id',
                'r.name as role_name', 'r.clearance_level'
            )
            ->orderBy('u.created_at', 'desc')
            ->get();
    }

    public function saveUser()
    {
        $isClient = $this->editMode ? $this->editingIsClient : $this->isClientForm();
        $table = $isClient ? 'client_users' : 'users';

        $this->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            // Dynamically ignore the current user's email if in edit mode
            'email' => "required|email|unique:{$table},email," . ($this->editingUserId ?? 'NULL') . ',id',
            'role_id' => $isClient ? 'nullable' : 'required|integer',
            'organization_id' => 'required|string',
        ]);

        // An account stays on its side: staff in JBS Praxis, clients in a client organisation
        if ($this->editMode && $isClient === ($this->organization_id === $this->jbsOrgId)) {
            $this->addError('organization_id', $isClient
                ? 'A client account cannot move into JBS Praxis. Create a staff account instead.'
                : 'A staff account stays in JBS Praxis. Create a client account instead.');
            return;
        }

        try {
            DB::transaction(function () use ($isClient, $table) {
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
                if (!$isClient) {
                    $identity['role_id'] = $this->role_id;
                }

                if ($this->editMode && $this->editingUserId) {
                    // UPDATE EXISTING USER
                    DB::table($table)->where('id', $this->editingUserId)->update($identity + ['updated_at' => now()]);

                    $this->successMessage = "Operator record and clearance updated successfully.";
                } else {
                    // CREATE NEW USER: no password until they open the setup link
                    $invitationToken = Str::random(60);
                    $record = $identity + [
                        'id' => Str::uuid()->toString(),
                        'password' => $isClient ? null : '', // staff column is NOT NULL; an empty hash never matches
                        'invitation_token' => $invitationToken,
                        'lifecycle_stage' => 'Onboarding',
                        'status' => 'Active',
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    if ($isClient) {
                        // The organisation's first portal account is its primary (admin) account
                        $record['is_primary_client'] = DB::table('client_users')->where('organization_id', $finalOrgId)
                            ->where('is_primary_client', 1)->whereNull('deleted_at')->doesntExist() ? 1 : 0;
                        $record['source'] = 'hq';
                        $record['created_by'] = auth()->id();
                    }

                    DB::table($table)->insert($record);

                    // Dispatch Invitation Email
                    Mail::to($this->email)->send(new \App\Mail\UserInvitation($this->first_name, $invitationToken, $isClient));

                    $this->successMessage = "User generated and cryptographic invitation transmitted successfully.";
                }
            });

            $this->showSuccessModal = true;
            $this->loadDropdownData(); // Refresh orgs in case a new one was added
            $this->loadLedger();

        } catch (Exception $e) {
            session()->flash('error', "System Matrix Error: " . $e->getMessage());
        }
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
            $this->role_id = $isClient ? '' : $user->role_id;
            $this->organization_id = $user->organization_id;

            // Switch view to the form tab directly without calling resetForm()
            $this->activeTab = 'CREATE';
        }
    }

    public function resendInvitation($id)
    {
        [$user, $isClient] = $this->findAccount($id);

        if ($user && $user->lifecycle_stage === 'Onboarding') {
            // Generate a fresh token for maximum security
            $token = Str::random(60);

            DB::table($isClient ? 'client_users' : 'users')->where('id', $user->id)->update([
                'invitation_token' => $token,
                'updated_at' => now()
            ]);

            // Transmit the email
            Mail::to($user->email)->send(new \App\Mail\UserInvitation($user->first_name, $token, $isClient));

            $this->successMessage = "Zero-Knowledge Init re-transmitted to " . strtoupper($user->first_name) . ".";
            $this->showSuccessModal = true;
            $this->loadLedger();
        }
    }

    public function toggleUserStatus($id, $currentStatus)
    {
        [$user, $isClient] = $this->findAccount($id);
        if (!$user || $user->id === auth()->id()) {
            return;
        }

        $newStatus = ($user->status === 'Active') ? 'Suspended' : 'Active';
        // A new remember token ends 'remember me' logins; open sessions end on the next request
        DB::table($isClient ? 'client_users' : 'users')->where('id', $user->id)->update([
            'status' => $newStatus,
            'remember_token' => Str::random(60),
            'updated_at' => now()
        ]);
        $this->loadLedger();
    }

    public function resetForm()
    {
        $this->reset([
            'first_name', 'last_name', 'email', 'phone', 'job_title',
            'role_id', 'organization_id', 'new_org_name', 'new_org_industry', 'selected_permissions',
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
        return view('livewire.hq.system.role-management');
    }
}
