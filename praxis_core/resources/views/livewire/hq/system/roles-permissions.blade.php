<div>
    @php
        // Display names for modules whose label in config/access.php still uses retired wording (docs/BRAND.md section 5).
        // The permission keys stay as they are.
        $moduleLabels = [
            'staff' => [
                'dashboard' => 'Overview', 'projects' => 'Projects', 'qa' => 'Quality and issues',
                'kaizen' => 'JBS Praxis Insights articles', 'crm' => 'Leads and proposals', 'finance' => 'Finance',
                'payroll' => 'Payroll and tax', 'hr' => 'Team', 'access' => 'Accounts, roles and permissions', 'system' => 'System health',
            ],
            'client' => [
                'projects' => 'Overview and project progress', 'qa' => 'Quality and issues', 'billing' => 'Invoices and payments',
                'documents' => 'Documents', 'support' => 'Urgent support',
            ],
        ][$audience] ?? [];
        // Client Portal roles are shown with the names clients see in the portal (lang/en/portal.php, roles); stored names stay as they are
        $roleLabel = fn ($name) => ($audience === 'client' && \Illuminate\Support\Facades\Lang::has('portal.roles.' . $name, 'en'))
            ? __('portal.roles.' . $name, [], 'en') : $name;
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">Roles and permissions</h1>
            <p class="page-subtitle">What each role can view, create, edit, delete, approve and export.</p>
        </div>
    </div>

    <div class="tabs" role="tablist">
        <button type="button" role="tab" wire:click="setAudience('staff')" class="tab {{ $audience === 'staff' ? 'is-active' : '' }}" aria-selected="{{ $audience === 'staff' ? 'true' : 'false' }}">Staff roles (HQ)</button>
        <button type="button" role="tab" wire:click="setAudience('client')" class="tab {{ $audience === 'client' ? 'is-active' : '' }}" aria-selected="{{ $audience === 'client' ? 'true' : 'false' }}">Client Portal roles</button>
    </div>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[300px_minmax(0,1fr)]">

        {{-- Role list --}}
        <div class="card" style="padding: 0; overflow: hidden;">
            <nav aria-label="Roles">
                @foreach($roles as $role)
                    <button type="button" wire:click="selectRole({{ $role->id }})"
                            class="block w-full text-left border-b border-line hover:bg-mist {{ $roleId === $role->id ? 'bg-teal-tint' : '' }}"
                            style="padding: 12px 16px; cursor: pointer; {{ $roleId === $role->id ? 'box-shadow: inset 2px 0 0 var(--color-deep-teal);' : '' }}"
                            @if($roleId === $role->id) aria-current="true" @endif>
                        <span class="flex items-center gap-2 text-ink" style="font-weight: 600;">
                            {{ $roleLabel($role->name) }}
                            @if($role->is_protected)
                                <span class="pill pill-warning">Protected</span>
                            @endif
                        </span>
                        <span class="block help" style="margin-top: 2px; font-variant-numeric: tabular-nums;">
                            {{ $role->member_count }} {{ $role->member_count === 1 ? 'account' : 'accounts' }} · {{ $role->permission_count }} {{ $role->permission_count === 1 ? 'permission' : 'permissions' }}
                        </span>
                    </button>
                @endforeach
            </nav>

            @if($canCreate)
                <div style="padding: 12px 16px;">
                    <button type="button" wire:click="newRole" class="btn btn-secondary" style="width: 100%;">
                        <i data-lucide="plus"></i> New {{ $audience === 'client' ? 'Client Portal' : 'staff' }} role
                    </button>
                </div>
            @endif
        </div>

        {{-- Editor --}}
        <div class="card">
            @if($notice)
                <div class="alert alert-success" role="status" style="margin-bottom: 20px;">
                    <i data-lucide="circle-check"></i>
                    <span>{{ $notice }}</span>
                </div>
            @endif

            @if($current?->is_protected)
                <div class="alert alert-info" style="margin-bottom: 20px;">
                    <i data-lucide="lock"></i>
                    <span>The Owner role always has every permission, including modules added later. It can’t be edited or deleted.</span>
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-[1fr_2fr]" style="margin-bottom: 20px;">
                <div class="field">
                    <label class="label" for="role-name">Role name</label>
                    <input id="role-name" type="text" wire:model="name" class="input" @disabled(! $canEdit)>
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="label" for="role-description">Description (optional)</label>
                    <input id="role-description" type="text" wire:model="description" class="input" @disabled(! $canEdit)>
                </div>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Module</th>
                            @foreach($actions as $action => $label)
                                <th style="text-align: center;">{{ $label }}</th>
                            @endforeach
                            @if($canEdit) <th><span class="sr-only">Select all</span></th> @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($modules as $module => $def)
                            @php
                                $moduleLabel = $moduleLabels[$module] ?? str_replace(' & ', ' and ', $def['label']);
                                $moduleSlugs = array_map(fn ($a) => $prefix . $module . '.' . $a, $def['actions']);
                                $allOn = count(array_diff($moduleSlugs, $selected)) === 0;
                            @endphp
                            <tr wire:key="m-{{ $audience }}-{{ $module }}">
                                <td class="text-ink">{{ $moduleLabel }}</td>
                                @foreach($actions as $action => $label)
                                    <td style="text-align: center; vertical-align: middle;">
                                        @if(in_array($action, $def['actions'], true))
                                            <input type="checkbox" wire:model="selected" value="{{ $prefix }}{{ $module }}.{{ $action }}" class="checkbox"
                                                   aria-label="{{ $moduleLabel }}: {{ $label }}" @disabled(! $canEdit) style="width: 16px; height: 16px;">
                                        @else
                                            <span class="muted" aria-hidden="true">–</span><span class="sr-only">Not available</span>
                                        @endif
                                    </td>
                                @endforeach
                                @if($canEdit)
                                    <td style="text-align: right; white-space: nowrap;">
                                        <button type="button" wire:click="toggleModule('{{ $module }}')" class="btn btn-ghost btn-sm" aria-label="{{ $allOn ? 'Clear all' : 'Select all' }}: {{ $moduleLabel }}">{{ $allOn ? 'Clear all' : 'Select all' }}</button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @error('selected') <span class="field-error" style="display: block; margin-top: 8px;">{{ $message }}</span> @enderror

            @if($canEdit || $canDelete)
                <div class="flex flex-wrap justify-between gap-3" style="margin-top: 24px;">
                    @if($canEdit)
                        <button type="button" wire:click="save" class="btn btn-primary">{{ $roleId ? 'Save role' : 'Create role' }}</button>
                    @endif
                    @if($canDelete)
                        <button type="button" wire:click="deleteRole" wire:confirm="Delete the role {{ $roleLabel($current->name) }}? This can’t be undone." class="btn btn-danger">Delete role</button>
                    @endif
                </div>
            @endif

            @if($audience === 'staff' && $current && $members->isNotEmpty())
                <div style="margin-top: 24px; border-top: 1px solid var(--color-line); padding-top: 20px;">
                    <h2 class="card-title" style="font-size: 0.875rem;">Staff with this role</h2>
                    <div class="flex flex-wrap gap-2" style="margin-top: 8px;">
                        @foreach($members as $m)
                            <span class="tag" @if($m->status !== 'Active') style="color: var(--color-danger);" @endif>
                                {{ $m->first_name }} {{ $m->last_name }}{{ $m->status === 'Active' ? '' : ' (suspended)' }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($audience === 'client')
                <p class="help" style="margin: 24px 0 0;">
                    Each client’s primary contact gives Client Portal roles to their team in the portal (Team tab). You can also give them on the Accounts screen.
                    A client organisation’s primary contact always has the Administrator role.
                </p>
            @endif
        </div>
    </div>
</div>
