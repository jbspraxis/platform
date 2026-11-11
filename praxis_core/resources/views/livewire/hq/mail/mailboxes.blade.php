<div class="mb">
    @assets
    <style>
        /* Mail → Mailboxes: only what the shared component classes don't cover. Brand tokens only. */
        .mb { max-width: 960px; }
        .mb-section { margin-top: 32px; }
        .mb-section-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 12px; }
        .mb-section-head h2 { margin: 0; font-size: 1rem; line-height: 1.5rem; font-weight: 600; color: var(--color-ink); }
        .mb-section-head p { margin: 2px 0 0; }
        .mb-rows { display: flex; flex-direction: column; gap: 12px; }
        .mb-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; padding: 16px; border: 1px solid var(--color-line); border-radius: var(--radius-control); background: var(--color-paper); }
        .mb-row.is-highlight { border-color: var(--color-teal); box-shadow: 0 0 0 1px var(--color-teal); }
        .mb-row-main { display: flex; align-items: flex-start; gap: 12px; min-width: 0; flex: 1 1 320px; }
        .mb-row-icon { width: 36px; height: 36px; border-radius: 50%; background: var(--color-teal-tint); color: var(--color-petrol); display: grid; place-items: center; flex-shrink: 0; }
        .mb-row-icon svg { width: 18px; height: 18px; }
        .mb-row-text { min-width: 0; }
        .mb-row-title { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 10px; }
        .mb-row-title strong { color: var(--color-ink); font-weight: 600; }
        .mb-row-title span { overflow-wrap: anywhere; }
        .mb-row-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px; margin-top: 6px; }
        .mb-row-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .mb-danger-text { color: var(--color-danger); }
        .mb-grid { display: grid; gap: 12px 16px; grid-template-columns: minmax(0, 2fr) minmax(0, 0.8fr) minmax(0, 1.2fr); }
        .mb-form { display: flex; flex-direction: column; gap: 16px; }
        .mb-form-2 { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(min(260px, 100%), 1fr)); }
        .mb-toggle { background: none; border: 0; padding: 0; color: var(--color-fg-link); font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .mb-toggle svg { width: 16px; height: 16px; }
        .mb-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
        .mb .modal { max-width: 640px; width: calc(100vw - 32px); max-height: calc(100vh - 48px); overflow-y: auto; }
        .mb-people { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
        .mb-people li { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 10px 0; border-bottom: 1px solid var(--color-line); }
        .mb-people li:last-child { border-bottom: 0; }
        .mb-person { min-width: 0; }
        .mb-person strong { display: block; color: var(--color-ink); font-weight: 600; }
        .mb-person-controls { display: flex; gap: 8px; align-items: center; }
        .mb-person-controls .select { min-width: 160px; }
        .mb-invite { display: grid; gap: 12px; grid-template-columns: minmax(0, 2fr) minmax(0, 1.2fr) auto; align-items: end; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--color-line); }
        .mb-levels { margin: 8px 0 0; padding-left: 18px; }
        .mb-levels li { margin: 2px 0; }
        @media (max-width: 640px) {
            .mb-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
            .mb-grid .field:nth-child(3n + 1) { grid-column: 1 / -1; }
            .mb-invite { grid-template-columns: minmax(0, 1fr); }
            .mb-row-actions { width: 100%; }
        }
    </style>
    @endassets

    <div class="page-header">
        <div>
            <h1 class="page-title">Mailboxes</h1>
            <p class="page-subtitle">Your own mailbox and the shared mailboxes you work in. Mail stays on the mail server; HQ keeps a copy of the last 12 months to show it quickly.</p>
        </div>
        @if ($canAddShared)
            <button type="button" class="btn btn-primary" wire:click="openModal('add-shared')"><i data-lucide="plus"></i> Add a shared mailbox</button>
        @endif
    </div>

    @if ($flash)
        <div class="alert alert-success mb-6" role="status"><i data-lucide="circle-check"></i><span>{{ $flash }}</span></div>
    @endif

    @if ($isOwner)
        @if ($openToEveryone)
            <div class="alert alert-info mb-6" role="note">
                <i data-lucide="info"></i>
                <div class="flex flex-wrap items-center gap-3 justify-between w-full">
                    <span>Mail is open to everyone whose role has the Mail permission.</span>
                    <button type="button" class="btn btn-ghost btn-sm" wire:click="openModal('close-mail')">Limit to Owner accounts</button>
                </div>
            </div>
        @else
            <div class="alert alert-info mb-6" role="note">
                <i data-lucide="eye-off"></i>
                <div class="flex flex-wrap items-center gap-3 justify-between w-full">
                    <span>Only Owner accounts can see Mail while it is being finished. Open it to everyone once reading and sending are live.</span>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="openModal('open-mail')">Open Mail to everyone</button>
                </div>
            </div>
        @endif
    @endif

    {{-- Your mailbox --}}
    <section class="mb-section" aria-labelledby="mb-own">
        <div class="mb-section-head">
            <div>
                <h2 id="mb-own">Your mailbox</h2>
                <p class="help">Only you see it in HQ.</p>
            </div>
        </div>

        @if ($personal)
            <div class="mb-rows">
                @include('livewire.hq.mail.partials.mailbox-row', ['account' => $personal, 'state' => $states[$personal->id], 'highlight' => $box === $personal->id])
            </div>
        @else
            <div class="card">
                <form wire:submit="connect" class="mb-form" novalidate>
                    <p class="muted" style="margin: 0;">Connect your work mailbox with its address and password, as in any mail app. HQ checks both incoming and outgoing mail before saving.</p>
                    <div class="mb-form-2">
                        <div class="field">
                            <label class="label" for="connect-email">Address</label>
                            <input id="connect-email" type="email" class="input" wire:model.live.blur="connectEmail" autocomplete="email" spellcheck="false"
                                   @error('connectEmail') aria-invalid="true" aria-describedby="connect-email-error" @enderror>
                            @error('connectEmail') <p class="field-error" id="connect-email-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="connect-password">Mailbox password</label>
                            <input id="connect-password" type="password" class="input" wire:model="connectPassword" autocomplete="current-password"
                                   @error('connectPassword') aria-invalid="true" aria-describedby="connect-password-error" @enderror>
                            @error('connectPassword') <p class="field-error" id="connect-password-error">{{ $message }}</p>
                            @else <p class="help">The password of the mailbox, not your HQ password.</p> @enderror
                        </div>
                    </div>
                    <div>
                        <button type="button" class="mb-toggle" wire:click="$toggle('connectShowServer')" aria-expanded="{{ $connectShowServer ? 'true' : 'false' }}">
                            <i data-lucide="{{ $connectShowServer ? 'chevron-down' : 'chevron-right' }}"></i> Server settings
                        </button>
                        @if ($connectShowServer || $errors->has('connectServer.*'))
                            <div class="mt-4">@include('livewire.hq.mail.partials.server-fields', ['model' => 'connectServer', 'id' => 'connect'])</div>
                        @endif
                    </div>
                    <div class="mb-actions">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="connect">
                            <i data-lucide="plug"></i>
                            <span wire:loading.remove wire:target="connect">Connect mailbox</span>
                            <span wire:loading wire:target="connect">Checking with the mail server…</span>
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </section>

    {{-- Shared mailboxes --}}
    <section class="mb-section" aria-labelledby="mb-shared">
        <div class="mb-section-head">
            <div>
                <h2 id="mb-shared">Shared mailboxes</h2>
                <p class="help">Team addresses like intelligence@jbspraxis.com. Their managers choose who can read and send from them.</p>
            </div>
        </div>

        @if ($shared->isNotEmpty())
            <div class="mb-rows">
                @foreach ($shared as $account)
                    @include('livewire.hq.mail.partials.mailbox-row', ['account' => $account, 'state' => $states[$account->id], 'highlight' => $box === $account->id])
                @endforeach
            </div>
        @else
            <div class="empty-state">
                <strong>No shared mailboxes yet</strong>
                @if ($canAddShared)
                    Add one with its address and password, then choose who can read and send from it.
                    <div class="mt-4"><button type="button" class="btn btn-secondary" wire:click="openModal('add-shared')"><i data-lucide="plus"></i> Add a shared mailbox</button></div>
                @else
                    Shared mailboxes appear here once a manager gives you access.
                @endif
            </div>
        @endif
    </section>

    {{-- Dialogs: one at a time --}}
    @if ($modal === 'add-shared')
        <div class="modal-backdrop" wire:keydown.escape.window="closeModal">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="add-shared-title">
                <h2 id="add-shared-title" class="modal-title">Add a shared mailbox</h2>
                <form wire:submit="addShared" class="mb-form" novalidate>
                    <div class="field">
                        <label class="label" for="shared-email">Address</label>
                        <input id="shared-email" type="email" class="input" wire:model.live.blur="sharedEmail" placeholder="intelligence@jbspraxis.com" autocomplete="off" spellcheck="false"
                               @error('sharedEmail') aria-invalid="true" aria-describedby="shared-email-error" @enderror>
                        @error('sharedEmail') <p class="field-error" id="shared-email-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="shared-name">Name people see</label>
                        <input id="shared-name" type="text" class="input" wire:model="sharedName" placeholder="JBS Praxis Intelligence" maxlength="120"
                               @error('sharedName') aria-invalid="true" aria-describedby="shared-name-error" @enderror>
                        @error('sharedName') <p class="field-error" id="shared-name-error">{{ $message }}</p>
                        @else <p class="help">Shown in the From line when the team writes from this mailbox. Each person's signature says who wrote.</p> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="shared-password">Mailbox password</label>
                        <input id="shared-password" type="password" class="input" wire:model="sharedPassword" autocomplete="new-password"
                               @error('sharedPassword') aria-invalid="true" aria-describedby="shared-password-error" @enderror>
                        @error('sharedPassword') <p class="field-error" id="shared-password-error">{{ $message }}</p>
                        @else <p class="help">Kept encrypted in HQ. People you invite never see it.</p> @enderror
                    </div>
                    <div>
                        <button type="button" class="mb-toggle" wire:click="$toggle('sharedShowServer')" aria-expanded="{{ $sharedShowServer ? 'true' : 'false' }}">
                            <i data-lucide="{{ $sharedShowServer ? 'chevron-down' : 'chevron-right' }}"></i> Server settings
                        </button>
                        @if ($sharedShowServer || $errors->has('sharedServer.*'))
                            <div class="mt-4">@include('livewire.hq.mail.partials.server-fields', ['model' => 'sharedServer', 'id' => 'shared'])</div>
                        @endif
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-ghost" wire:click="closeModal">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="addShared">
                            <span wire:loading.remove wire:target="addShared">Add mailbox</span>
                            <span wire:loading wire:target="addShared">Checking with the mail server…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($modal === 'password' && $modalAccount)
        <div class="modal-backdrop" wire:keydown.escape.window="closeModal">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="password-title">
                <h2 id="password-title" class="modal-title">Password for {{ $modalAccount->email }}</h2>
                <form wire:submit="savePassword" class="mb-form" novalidate>
                    <p class="muted" style="margin: 0;">Enter the mailbox's current password (after it was changed in cPanel, for example). HQ checks it with the mail server before saving.</p>
                    <div class="field">
                        <label class="label" for="new-password">Mailbox password</label>
                        <input id="new-password" type="password" class="input" wire:model="newPassword" autocomplete="new-password"
                               @error('newPassword') aria-invalid="true" aria-describedby="new-password-error" @enderror>
                        @error('newPassword') <p class="field-error" id="new-password-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <button type="button" class="mb-toggle" wire:click="$toggle('passwordShowServer')" aria-expanded="{{ $passwordShowServer ? 'true' : 'false' }}">
                            <i data-lucide="{{ $passwordShowServer ? 'chevron-down' : 'chevron-right' }}"></i> Server settings
                        </button>
                        @if ($passwordShowServer || $errors->has('passwordServer.*'))
                            <div class="mt-4">@include('livewire.hq.mail.partials.server-fields', ['model' => 'passwordServer', 'id' => 'pw'])</div>
                        @endif
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-ghost" wire:click="closeModal">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="savePassword">
                            <span wire:loading.remove wire:target="savePassword">Save password</span>
                            <span wire:loading wire:target="savePassword">Checking…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($modal === 'rename' && $modalAccount)
        <div class="modal-backdrop" wire:keydown.escape.window="closeModal">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="rename-title">
                <h2 id="rename-title" class="modal-title">Rename {{ $modalAccount->email }}</h2>
                <form wire:submit="saveName" class="mb-form" novalidate>
                    <div class="field">
                        <label class="label" for="rename-name">Name people see</label>
                        <input id="rename-name" type="text" class="input" wire:model="renameName" maxlength="120"
                               @error('renameName') aria-invalid="true" aria-describedby="rename-name-error" @enderror>
                        @error('renameName') <p class="field-error" id="rename-name-error">{{ $message }}</p>
                        @else <p class="help">Shown in the From line of mail sent from this mailbox.</p> @enderror
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-ghost" wire:click="closeModal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save name</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($modal === 'people' && $modalAccount)
        <div class="modal-backdrop" wire:keydown.escape.window="closeModal">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="people-title">
                <h2 id="people-title" class="modal-title">People in {{ $modalAccount->email }}</h2>
                <p class="muted" style="margin: 0;">What each level can do:</p>
                <ul class="mb-levels help">
                    <li><strong>Read</strong>: sees all mail in this mailbox.</li>
                    <li><strong>Read and send</strong>: also replies, sends, files and deletes.</li>
                    <li><strong>Manager</strong>: also invites people, changes their access and updates the password.</li>
                </ul>
                @if ($isOwner) <p class="help mt-1">Owner accounts manage every shared mailbox without being listed.</p> @endif

                @error('members') <div class="alert alert-danger mt-4" role="alert"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div> @enderror

                <ul class="mb-people mt-4" aria-label="People with access">
                    @foreach ($members as $member)
                        <li wire:key="member-{{ $member->id }}">
                            <div class="flex items-center gap-3" style="min-width: 0;">
                                <x-hq.avatar :user="$member->user" :size="32" />
                                <div class="mb-person">
                                    <strong>{{ $member->user?->name ?? 'Former staff member' }} @if ($member->user_id === auth()->id()) <span class="text-muted">(you)</span> @endif</strong>
                                    <span class="help">{{ $member->user?->email }}</span>
                                </div>
                            </div>
                            <div class="mb-person-controls">
                                <label class="sr-only" for="level-{{ $member->id }}">Access for {{ $member->user?->name }}</label>
                                <select id="level-{{ $member->id }}" class="select" wire:model.live="memberLevels.{{ $member->id }}">
                                    @foreach ($levels as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                                </select>
                                <button type="button" class="btn btn-ghost btn-sm mb-danger-text" wire:click="removeMember({{ $member->id }})"
                                        wire:confirm="Remove {{ $member->user?->name ?? 'this person' }}'s access to {{ $modalAccount->email }}?">Remove</button>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <form wire:submit="invite" class="mb-invite" novalidate>
                    <div class="field">
                        <label class="label" for="invite-user">Give access to</label>
                        <select id="invite-user" class="select" wire:model="inviteUser"
                                @error('inviteUser') aria-invalid="true" aria-describedby="invite-user-error" @enderror>
                            <option value="">Choose a person</option>
                            @foreach ($invitable as $person)
                                <option value="{{ $person->id }}">{{ $person->name }} ({{ $person->email }})</option>
                            @endforeach
                        </select>
                        @error('inviteUser') <p class="field-error" id="invite-user-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="invite-level">Access</label>
                        <select id="invite-level" class="select" wire:model="inviteLevel">
                            @foreach ($levels as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><i data-lucide="user-plus"></i> Give access</button>
                </form>
                @if ($invitable->isEmpty())
                    <p class="help mt-1">Everyone who can use Mail already has access. People whose role lacks the Mail permission can't be added.</p>
                @endif

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" wire:click="closeModal">Done</button>
                </div>
            </div>
        </div>
    @endif

    @if ($modal === 'remove' && $modalAccount)
        <div class="modal-backdrop" wire:keydown.escape.window="closeModal">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="remove-title">
                @if ($modalAccount->isShared())
                    <h2 id="remove-title" class="modal-title">Remove {{ $modalAccount->email }} from HQ?</h2>
                    <p class="muted">Everyone loses access to it in HQ, and its notes and assignments here are deleted. The mail itself stays on the mail server, and you can add the mailbox again later.</p>
                @else
                    <h2 id="remove-title" class="modal-title">Disconnect {{ $modalAccount->email }}?</h2>
                    <p class="muted">HQ deletes its copy of your mail. The mail itself stays on the mail server, and you can connect again later.</p>
                @endif
                <div class="modal-actions">
                    <button type="button" class="btn btn-ghost" wire:click="closeModal">Keep it</button>
                    <button type="button" class="btn btn-danger" wire:click="removeMailbox" wire:loading.attr="disabled">
                        {{ $modalAccount->isShared() ? 'Remove ' . $modalAccount->email : 'Disconnect ' . $modalAccount->email }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($modal === 'open-mail')
        <div class="modal-backdrop" wire:keydown.escape.window="closeModal">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="open-mail-title">
                <h2 id="open-mail-title" class="modal-title">Open Mail to everyone?</h2>
                <p class="muted">Everyone whose role has the Mail permission will see Mail in the menu and can connect their own mailbox. Shared mailboxes stay limited to the people their managers chose.</p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-ghost" wire:click="closeModal">Not yet</button>
                    <button type="button" class="btn btn-primary" wire:click="setOpenTo('everyone')">Open Mail to everyone</button>
                </div>
            </div>
        </div>
    @endif

    @if ($modal === 'close-mail')
        <div class="modal-backdrop" wire:keydown.escape.window="closeModal">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="close-mail-title">
                <h2 id="close-mail-title" class="modal-title">Limit Mail to Owner accounts?</h2>
                <p class="muted">Everyone else loses Mail from the menu until you open it again. Their mailboxes stay connected.</p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-ghost" wire:click="closeModal">Keep it open</button>
                    <button type="button" class="btn btn-danger" wire:click="setOpenTo('owner')">Limit to Owner accounts</button>
                </div>
            </div>
        </div>
    @endif
</div>
