<div>
    <style>
        :root {
            --erp-bg-main: #0A1128;
            --erp-border-dark: rgba(255,255,255,0.1);
            --erp-teal: #00E5FF;
            --erp-muted: #8A9BB2;
            --erp-white: #FFFFFF;
            --erp-yellow: #FFBD2E;
            --erp-red: #FF3366;
            --erp-green: #00FF66;
        }
        .cyber-input { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: var(--erp-white); padding: 12px 15px; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; border-radius: 2px; outline: none; }
        .cyber-input:focus { border-color: var(--erp-teal); background: rgba(0, 229, 255, 0.05); }
        .cyber-input:disabled { opacity: 0.6; }
        .btn-action { background: transparent; border: 1px solid var(--erp-teal); color: var(--erp-teal); padding: 10px 18px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; cursor: pointer; border-radius: 2px; }
        .btn-action:hover { background: var(--erp-teal); color: var(--erp-bg-main); }
        .btn-danger { border-color: var(--erp-red); color: var(--erp-red); }
        .btn-danger:hover { background: var(--erp-red); color: var(--erp-bg-main); }
        .nav-pill { padding: 8px 20px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; border-radius: 2px; cursor: pointer; border: 1px solid transparent; background: transparent; color: var(--erp-muted); }
        .nav-pill.active { background: rgba(0, 229, 255, 0.1); border-color: var(--erp-teal); color: var(--erp-teal); font-weight: bold; }
        .panel { background: rgba(10, 17, 40, 0.6); border: 1px solid var(--erp-border-dark); border-radius: 4px; }
        .role-row { display: block; width: 100%; text-align: left; padding: 14px 16px; background: transparent; border: 0; border-bottom: 1px solid rgba(255,255,255,0.04); cursor: pointer; color: var(--erp-white); }
        .role-row:hover { background: rgba(0, 229, 255, 0.03); }
        .role-row.active { background: rgba(0, 229, 255, 0.08); box-shadow: inset 3px 0 0 var(--erp-teal); }
        .label { display: block; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-teal); margin-bottom: 6px; font-weight: bold; }
        .matrix { width: 100%; border-collapse: collapse; }
        .matrix th { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); text-transform: uppercase; padding: 10px 8px; border-bottom: 1px solid var(--erp-border-dark); }
        .matrix td { padding: 10px 8px; border-bottom: 1px solid rgba(255,255,255,0.03); text-align: center; }
        .matrix td.module { text-align: left; color: var(--erp-white); font-size: 0.85rem; }
        .matrix input[type=checkbox] { width: 16px; height: 16px; accent-color: #00E5FF; cursor: pointer; }
        .matrix input[type=checkbox]:disabled { cursor: not-allowed; }
        .tag { display: inline-block; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; padding: 2px 6px; border-radius: 2px; }
        .error { color: var(--erp-red); font-family: 'Roboto Mono', monospace; font-size: 0.7rem; margin-top: 6px; display: block; }
        @media (max-width: 900px) { .rp-grid { grid-template-columns: 1fr !important; } .matrix-wrap { overflow-x: auto; } }
    </style>

    <div style="margin-bottom: 25px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end; gap: 15px; flex-wrap: wrap;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.2rem; color: var(--erp-white); letter-spacing: 1px;">ROLES & PERMISSIONS</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted);">What each role may view, create, edit, delete, approve and export.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button wire:click="setAudience('staff')" class="nav-pill {{ $audience === 'staff' ? 'active' : '' }}">STAFF ROLES (HQ)</button>
            <button wire:click="setAudience('client')" class="nav-pill {{ $audience === 'client' ? 'active' : '' }}">CLIENT ROLES (PORTAL)</button>
        </div>
    </div>

    <div class="rp-grid" style="display: grid; grid-template-columns: 300px 1fr; gap: 25px; align-items: start;">

        {{-- Role list --}}
        <div class="panel">
            @foreach($roles as $role)
                <button wire:click="selectRole({{ $role->id }})" class="role-row {{ $roleId === $role->id ? 'active' : '' }}">
                    <div style="font-weight: bold; font-size: 0.9rem; display: flex; align-items: center; gap: 8px;">
                        {{ $role->name }}
                        @if($role->is_protected)
                            <span class="tag" style="background: rgba(255,189,46,0.1); border: 1px solid var(--erp-yellow); color: var(--erp-yellow);">PROTECTED</span>
                        @endif
                    </div>
                    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-top: 4px;">
                        {{ $role->member_count }} {{ $role->member_count === 1 ? 'account' : 'accounts' }} · {{ $role->permission_count }} permissions
                    </div>
                </button>
            @endforeach

            @if($canCreate)
                <div style="padding: 14px 16px;">
                    <button wire:click="newRole" class="btn-action" style="width: 100%;">+ NEW {{ $audience === 'client' ? 'CLIENT' : 'STAFF' }} ROLE</button>
                </div>
            @endif
        </div>

        {{-- Editor --}}
        <div class="panel" style="padding: 25px;">
            @if($notice)
                <div style="background: rgba(0,255,102,0.08); border: 1px solid var(--erp-green); color: var(--erp-green); font-family: 'Roboto Mono', monospace; font-size: 0.75rem; padding: 10px 14px; margin-bottom: 20px; border-radius: 2px;">{{ $notice }}</div>
            @endif

            @if($current?->is_protected)
                <div style="background: rgba(255,189,46,0.06); border: 1px dashed var(--erp-yellow); color: var(--erp-yellow); font-size: 0.8rem; padding: 12px 14px; margin-bottom: 20px; border-radius: 2px;">
                    The Owner role always has every permission, including modules added later. It cannot be edited or deleted.
                </div>
            @endif

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 15px; margin-bottom: 20px;">
                <div>
                    <label class="label" for="role-name">Role name</label>
                    <input id="role-name" type="text" wire:model="name" class="cyber-input" @disabled(! $canEdit)>
                    @error('name') <span class="error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="label" for="role-description">Description</label>
                    <input id="role-description" type="text" wire:model="description" class="cyber-input" @disabled(! $canEdit)>
                </div>
            </div>

            <div class="matrix-wrap">
                <table class="matrix">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Module</th>
                            @foreach($actions as $action => $label)
                                <th>{{ $label }}</th>
                            @endforeach
                            @if($canEdit) <th></th> @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($modules as $module => $def)
                            <tr wire:key="m-{{ $audience }}-{{ $module }}">
                                <td class="module">{{ $def['label'] }}</td>
                                @foreach($actions as $action => $label)
                                    <td>
                                        @if(in_array($action, $def['actions'], true))
                                            <input type="checkbox" wire:model="selected" value="{{ $prefix }}{{ $module }}.{{ $action }}"
                                                   aria-label="{{ $def['label'] }}: {{ $label }}" @disabled(! $canEdit)>
                                        @else
                                            <span style="color: rgba(255,255,255,0.12);">·</span>
                                        @endif
                                    </td>
                                @endforeach
                                @if($canEdit)
                                    <td><button type="button" wire:click="toggleModule('{{ $module }}')" class="tag" style="background: transparent; border: 1px solid var(--erp-border-dark); color: var(--erp-muted); cursor: pointer;">ALL</button></td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @error('selected') <span class="error">{{ $message }}</span> @enderror

            @if($canEdit || $canDelete)
                <div style="display: flex; justify-content: space-between; gap: 10px; margin-top: 25px; flex-wrap: wrap;">
                    @if($canEdit)
                        <button wire:click="save" class="btn-action">{{ $roleId ? 'SAVE ROLE' : 'CREATE ROLE' }}</button>
                    @endif
                    @if($canDelete)
                        <button wire:click="deleteRole" wire:confirm="Delete the role '{{ $current->name }}'? This cannot be undone." class="btn-action btn-danger">DELETE ROLE</button>
                    @endif
                </div>
            @endif

            @if($audience === 'staff' && $current && $members->isNotEmpty())
                <div style="margin-top: 30px; border-top: 1px solid var(--erp-border-dark); padding-top: 20px;">
                    <span class="label">People with this role</span>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px;">
                        @foreach($members as $m)
                            <span class="tag" style="background: rgba(255,255,255,0.04); border: 1px solid var(--erp-border-dark); color: {{ $m->status === 'Active' ? 'var(--erp-white)' : 'var(--erp-muted)' }}; font-size: 0.7rem; padding: 4px 8px;">
                                {{ $m->first_name }} {{ $m->last_name }}{{ $m->status === 'Active' ? '' : ' (suspended)' }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($audience === 'client')
                <p style="margin-top: 25px; font-size: 0.75rem; color: var(--erp-muted); line-height: 1.6;">
                    Client roles are assigned by each client's primary account in the portal (Team tab), or by HQ on the Accounts screen.
                    A client organisation's primary account always has Client Admin.
                </p>
            @endif
        </div>
    </div>
</div>
