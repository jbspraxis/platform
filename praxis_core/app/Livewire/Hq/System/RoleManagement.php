<?php

namespace App\Livewire\Hq\System;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Exception;

#[Layout('components.layouts.hq')]
class RoleManagement extends Component
{
    // --- DATAGRID & UI STATE ---
    public $activeTab = 'CREATE'; // Tabs: CREATE, INTERNAL, CLIENTS
    public $users = [];
    public $roles = [];
    public $organizations = [];
    public $permissionsList = []; 
    
    public $jbsOrgId = null;
    public $showSuccessModal = false;
    public $successMessage = '';

    // --- EDIT STATE ---
    public $editMode = false;
    public $editingUserId = null;

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

    public function loadLedger()
    {
        if ($this->activeTab === 'CREATE') {
            return;
        }

        $query = DB::table('users as u')
            ->leftJoin('organizations as org', 'u.organization_id', '=', 'org.id')
            ->leftJoin('roles as r', 'u.role_id', '=', 'r.id')
            ->select(
                'u.id', 'u.first_name', 'u.last_name', 'u.email', 'u.lifecycle_stage', 'u.status',
                'org.name as org_name', 'org.id as org_id',
                'r.name as role_name', 'r.clearance_level'
            )
            ->orderBy('u.created_at', 'desc');

        if ($this->activeTab === 'INTERNAL') {
            $query->where('u.organization_id', $this->jbsOrgId);
        } elseif ($this->activeTab === 'CLIENTS') {
            $query->where('u.organization_id', '!=', $this->jbsOrgId)
                  ->whereNotNull('u.organization_id');
        }

        $this->users = $query->get();
    }

    public function saveUser()
    {
        $this->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            // Dynamically ignore the current user's email if in edit mode
            'email' => 'required|email|unique:users,email,' . ($this->editingUserId ?? 'NULL') . ',id',
            'role_id' => 'required|integer',
            'organization_id' => 'required|string',
        ]);

        try {
            DB::transaction(function () {
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

                if ($this->editMode && $this->editingUserId) {
                    // UPDATE EXISTING USER
                    DB::table('users')->where('id', $this->editingUserId)->update([
                        'organization_id' => $finalOrgId,
                        'role_id' => $this->role_id,
                        'first_name' => $this->first_name,
                        'last_name' => $this->last_name,
                        'email' => $this->email,
                        'phone' => $this->phone,
                        'job_title' => $this->job_title,
                        'updated_at' => now()
                    ]);
                    
                    $this->successMessage = "Operator record and clearance updated successfully.";
                } else {
                    // CREATE NEW USER
                    $userId = Str::uuid()->toString();
                    $invitationToken = Str::random(60);

                    DB::table('users')->insert([
                        'id' => $userId,
                        'organization_id' => $finalOrgId,
                        'role_id' => $this->role_id,
                        'first_name' => $this->first_name,
                        'last_name' => $this->last_name,
                        'email' => $this->email,
                        'phone' => $this->phone,
                        'job_title' => $this->job_title,
                        'password' => '', // Left blank for Zero-Knowledge Init
                        'invitation_token' => $invitationToken,
                        'lifecycle_stage' => 'Onboarding',
                        'status' => 'Active',
                        'is_primary_client' => ($finalOrgId !== $this->jbsOrgId) ? 1 : 0,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    // Dispatch Invitation Email
                    Mail::to($this->email)->send(new \App\Mail\UserInvitation($this->first_name, $invitationToken));
                    
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

    public function editUser($id)
    {
        $user = DB::table('users')->where('id', $id)->first();
        if ($user) {
            $this->editMode = true;
            $this->editingUserId = $user->id;
            $this->first_name = $user->first_name;
            $this->last_name = $user->last_name;
            $this->email = $user->email;
            $this->phone = $user->phone;
            $this->job_title = $user->job_title;
            $this->role_id = $user->role_id;
            $this->organization_id = $user->organization_id;
            
            // Switch view to the form tab directly without calling resetForm()
            $this->activeTab = 'CREATE'; 
        }
    }

    public function resendInvitation($id)
    {
        $user = DB::table('users')->where('id', $id)->first();
        
        if ($user && $user->lifecycle_stage === 'Onboarding') {
            // Generate a fresh token for maximum security
            $token = Str::random(60);
            
            DB::table('users')->where('id', $user->id)->update([
                'invitation_token' => $token,
                'updated_at' => now()
            ]);

            // Transmit the email
            Mail::to($user->email)->send(new \App\Mail\UserInvitation($user->first_name, $token));

            $this->successMessage = "Zero-Knowledge Init re-transmitted to " . strtoupper($user->first_name) . ".";
            $this->showSuccessModal = true;
            $this->loadLedger();
        }
    }

    public function toggleUserStatus($id, $currentStatus)
    {
        $newStatus = ($currentStatus === 'Active') ? 'Suspended' : 'Active';
        DB::table('users')->where('id', $id)->update([
            'status' => $newStatus,
            'updated_at' => now()
        ]);
        $this->loadLedger();
    }

    public function resetForm()
    {
        $this->reset([
            'first_name', 'last_name', 'email', 'phone', 'job_title', 
            'role_id', 'organization_id', 'new_org_name', 'new_org_industry', 'selected_permissions',
            'editMode', 'editingUserId'
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