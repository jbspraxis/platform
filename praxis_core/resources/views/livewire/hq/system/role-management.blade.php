<div>
    @php
        // Client Portal roles are shown with the names clients see in the portal (lang/en/portal.php, roles); stored names stay as they are.
        // Staff role names are not in that list and are shown as stored.
        $roleLabel = function ($name) {
            $primary = str_ends_with((string) $name, ' (primary)'); // 'Client Admin (primary)' from RoleManagement
            $base = $primary ? substr($name, 0, -strlen(' (primary)')) : (string) $name;
            $label = \Illuminate\Support\Facades\Lang::has('portal.roles.' . $base, 'en') ? __('portal.roles.' . $base, [], 'en') : $base;
            return $label . ($primary ? ' (primary contact)' : '');
        };
    @endphp
    @if($showSuccessModal)
        <div class="modal-backdrop">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="account-saved-title">
                <h2 id="account-saved-title" class="modal-title">Done</h2>
                <p class="muted">{{ $successMessage }}</p>
                <div class="modal-actions">
                    <button type="button" wire:click="closeSuccessModal" class="btn btn-primary">Close</button>
                </div>
            </div>
        </div>
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-title">Accounts</h1>
            <p class="page-subtitle">Invite staff members and Client Portal team members, change their roles, and suspend or restore their access.</p>
        </div>
    </div>

    <x-flash class="mb-6" />

    <div class="tabs" role="tablist">
        @if($canCreate)
            <button type="button" role="tab" wire:click="setTab('CREATE')" class="tab {{ $activeTab === 'CREATE' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'CREATE' ? 'true' : 'false' }}">
                {{ $editMode ? 'Edit account' : 'Add an account' }}
            </button>
        @endif
        <button type="button" role="tab" wire:click="setTab('INTERNAL')" class="tab {{ $activeTab === 'INTERNAL' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'INTERNAL' ? 'true' : 'false' }}">Staff accounts</button>
        <button type="button" role="tab" wire:click="setTab('CLIENTS')" class="tab {{ $activeTab === 'CLIENTS' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'CLIENTS' ? 'true' : 'false' }}">Client Portal accounts</button>
    </div>

    @if($activeTab === 'CREATE')
        <div class="card" style="max-width: 800px;">

            <div class="card-header">
                <h2 class="card-title">{{ $editMode ? 'Edit account' : 'Add an account' }}</h2>
                @if($editMode)
                    <button type="button" wire:click="resetForm" class="btn btn-ghost btn-sm">Cancel</button>
                @endif
            </div>
            @unless($editMode)
                <p class="help" style="margin: -8px 0 16px;">We’ll email the person an invitation to choose a password. Staff sign in to HQ; clients sign in to the Client Portal.</p>
            @endunless

            <form wire:submit.prevent="saveUser" class="flex flex-col gap-6">

                <section class="flex flex-col gap-4" aria-labelledby="acc-person-heading">
                    <h3 id="acc-person-heading" class="label" style="font-weight: 600; margin: 0;">Person</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="field">
                            <label class="label" for="acc-first">First name</label>
                            <input id="acc-first" type="text" wire:model="first_name" required class="input" autocomplete="off">
                            @error('first_name') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="acc-last">Last name</label>
                            <input id="acc-last" type="text" wire:model="last_name" required class="input" autocomplete="off">
                            @error('last_name') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="field">
                            <label class="label" for="acc-email">Email address</label>
                            <input id="acc-email" type="email" wire:model="email" required class="input" placeholder="e.g. name@example.com" autocomplete="off">
                            @error('email') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="acc-phone">Phone (optional)</label>
                            <input id="acc-phone" type="text" wire:model="phone" class="input" placeholder="e.g. +234 800 000 0000">
                        </div>
                        <div class="field">
                            <label class="label" for="acc-job">Job title (optional)</label>
                            <input id="acc-job" type="text" wire:model="job_title" class="input">
                        </div>
                    </div>
                </section>

                <section class="flex flex-col gap-4" style="border-top: 1px solid var(--color-line); padding-top: 20px;" aria-labelledby="acc-access-heading">
                    <h3 id="acc-access-heading" class="label" style="font-weight: 600; margin: 0;">Organisation and role</h3>
                    <div class="grid gap-4 sm:grid-cols-2">

                        <div class="field">
                            <label class="label" for="acc-org">Organisation</label>
                            <select id="acc-org" wire:model.live="organization_id" required class="select">
                                <option value="">Choose an organisation</option>
                                <option value="NEW">Add a new client organisation</option>
                                <optgroup label="Organisations">
                                    @foreach($organizations as $org)
                                        <option value="{{ $org->id }}">{{ $org->name }}{{ $org->id === $jbsOrgId ? ' (staff)' : '' }}</option>
                                    @endforeach
                                </optgroup>
                            </select>
                            <span class="help">Choose JBS Praxis for a staff account. Any other organisation makes a Client Portal account.</span>
                            @error('organization_id') <span class="field-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="field">
                            @if($editingIsClient || $this->isClientForm())
                                {{-- One portal role; the organisation’s first account is its primary account and always Client Admin --}}
                                <label for="client-role" class="label">Client Portal role</label>
                                <select id="client-role" wire:model="client_role_id" class="select" aria-describedby="client-role-help">
                                    <option value="">{{ $roleLabel('Client Member') }} (default)</option>
                                    {{-- The default above is the Team member role: it isn't listed twice --}}
                                    @foreach(collect($clientRoles)->reject(fn ($role) => data_get($role, 'slug') === 'client-member') as $role)
                                        <option value="{{ $role->id }}">{{ $roleLabel($role->name) }}</option>
                                    @endforeach
                                </select>
                                <span id="client-role-help" class="help">The organisation’s first account becomes its primary contact and always has the Administrator role.</span>
                            @else
                                <span class="label" id="staff-roles-label">Staff roles</span>
                                <div role="group" aria-labelledby="staff-roles-label" class="flex flex-col gap-2" style="padding: 12px; border: 1px solid var(--color-line-strong); border-radius: var(--radius-control);">
                                    @foreach($staffRoles as $role)
                                        <label class="flex items-center gap-2" style="cursor: pointer; font-size: 0.875rem;">
                                            <input type="checkbox" wire:model.live="role_ids" value="{{ $role->id }}" class="checkbox">
                                            <span class="text-ink">{{ $role->name }}</span>
                                            @if($role->is_protected) <span class="help">(only an owner can give this role)</span> @endif
                                        </label>
                                    @endforeach
                                </div>
                                <span class="help">Choose one or more. A person with several roles can do everything those roles allow.</span>
                                @error('role_ids') <span class="field-error">{{ $message }}</span> @enderror
                                @if(count($role_ids) > 1)
                                    <div class="field" style="margin-top: 12px;">
                                        <label class="label" for="acc-primary-role">Primary role</label>
                                        <select id="acc-primary-role" class="select" wire:model="primary_role_id">
                                            @foreach($staffRoles as $role)
                                                @if(in_array((string) $role->id, array_map('strval', $role_ids), true))
                                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <span class="help">Shown with their name across HQ. The other roles only add access.</span>
                                    </div>
                                @endif
                            @endif
                        </div>

                    </div>

                    @if($organization_id === 'NEW')
                        <div class="callout">
                            <div class="flex items-center gap-2 text-ink" style="font-weight: 600; margin-bottom: 12px;">
                                <i data-lucide="building-2" style="width: 18px; height: 18px;"></i> New client organisation
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="field">
                                    <label class="label" for="acc-new-org">Organisation name</label>
                                    <input id="acc-new-org" type="text" wire:model="new_org_name" required class="input">
                                    @error('new_org_name') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label class="label" for="acc-new-industry">Industry</label>
                                    <input id="acc-new-industry" type="text" wire:model="new_org_industry" required class="input" placeholder="e.g. Fintech, logistics">
                                    @error('new_org_industry') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    @endif
                </section>

                <div class="flex justify-end" style="border-top: 1px solid var(--color-line); padding-top: 20px;">
                    <button type="submit" wire:loading.attr="disabled" class="btn btn-primary">
                        <span wire:loading.remove wire:target="saveUser" class="flex items-center gap-2">
                            <i data-lucide="{{ $editMode ? 'save' : 'send' }}"></i> {{ $editMode ? 'Save changes' : 'Send invitation' }}
                        </span>
                        <span wire:loading wire:target="saveUser">{{ $editMode ? 'Saving…' : 'Sending invitation…' }}</span>
                    </button>
                </div>

            </form>
        </div>

    @else
        <div class="card" style="padding: 0; overflow: hidden;">

            <div class="card-header" style="padding: 16px 20px 0;">
                <h2 class="card-title">{{ $activeTab === 'INTERNAL' ? 'Staff accounts' : 'Client Portal accounts' }}</h2>
                <span class="help" style="font-variant-numeric: tabular-nums;">{{ count($users) }} {{ count($users) == 1 ? 'account' : 'accounts' }}</span>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="min-width: 220px;">Name and email</th>
                            <th style="min-width: 160px;">Organisation</th>
                            <th style="min-width: 160px;">Role</th>
                            <th>Status</th>
                            <th style="min-width: 280px;"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-hq.avatar :user="$user" :size="32" />
                                        <div style="min-width: 0;">
                                            <div class="text-ink" style="font-weight: 600;">{{ $user->first_name }} {{ $user->last_name }}</div>
                                            <div class="help" style="margin-top: 2px;">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div class="text-ink">{{ $user->org_name ?? 'No organisation' }}</div>
                                    @if($user->org_id === $jbsOrgId)
                                        <span class="pill pill-info" style="margin-top: 4px;">Staff</span>
                                    @else
                                        <span class="pill pill-neutral" style="margin-top: 4px;">Client</span>
                                    @endif
                                </td>

                                <td class="text-ink">{{ $user->role_name ? $roleLabel($user->role_name) : 'No role' }}</td>

                                <td>
                                    @if($user->lifecycle_stage === 'Onboarding')
                                        <span class="pill pill-warning">Invited</span>
                                    @elseif($user->status === 'Active')
                                        <span class="pill pill-success">Active</span>
                                    @else
                                        <span class="pill pill-danger">Suspended</span>
                                    @endif
                                </td>

                                <td style="white-space: nowrap; text-align: right;">
                                @if($canEdit)
                                    @if($user->lifecycle_stage === 'Onboarding')
                                        <button type="button" wire:click="resendInvitation('{{ $user->id }}')" class="btn btn-ghost btn-sm">Resend invitation</button>
                                    @endif

                                    <button type="button" wire:click="editUser('{{ $user->id }}')" class="btn btn-ghost btn-sm">Edit</button>

                                    @if($activeTab === 'CLIENTS' && empty($user->is_primary_client) && $user->status === 'Active')
                                        <button type="button" wire:click="makePrimary('{{ $user->id }}')"
                                                wire:confirm="Make {{ $user->first_name }} {{ $user->last_name }} the primary contact of {{ $user->org_name }}? They become its Client Admin; the current primary contact keeps the Administrator role."
                                                class="btn btn-ghost btn-sm">Make primary contact</button>
                                    @endif

                                    @if($user->status === 'Active')
                                        <button type="button" wire:click="toggleUserStatus('{{ $user->id }}', '{{ $user->status }}')"
                                                wire:confirm="Suspend access for {{ $user->first_name }} {{ $user->last_name }}? They will be signed out and can’t sign in until you restore access."
                                                class="btn btn-ghost btn-sm" style="color: var(--color-danger);">Suspend access</button>
                                    @else
                                        <button type="button" wire:click="toggleUserStatus('{{ $user->id }}', '{{ $user->status }}')" class="btn btn-ghost btn-sm">Restore access</button>
                                    @endif
                                @endif
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <strong>No {{ $activeTab === 'INTERNAL' ? 'staff' : 'Client Portal' }} accounts yet</strong>
                                        @if($canCreate)
                                            Accounts appear here once you send an invitation from Add an account.
                                        @else
                                            Accounts appear here once someone with permission to invite people adds them.
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
