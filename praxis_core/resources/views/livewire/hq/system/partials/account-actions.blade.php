{{-- The actions on one account (Accounts › Staff accounts list, and a client's accounts dialog). Needs $user, $canEdit, $activeTab. --}}
@if($canEdit)
    @if($user->lifecycle_stage === 'Onboarding')
        <button type="button" wire:click="resendInvitation('{{ $user->id }}')" class="btn btn-ghost btn-sm">Resend invitation</button>
    @elseif($user->status === 'Active')
        <button type="button" wire:click="sendPasswordReset('{{ $user->id }}')"
                wire:confirm="Email {{ $user->first_name }} {{ $user->last_name }} a link to choose a new password? It goes to {{ $user->email }} and works for {{ \App\Support\SignIn\PasswordResets::MINUTES }} minutes."
                class="btn btn-ghost btn-sm">Send reset link</button>
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

    {{-- Client accounts only; the primary contact stays until someone else is made primary --}}
    @if($activeTab === 'CLIENTS' && empty($user->is_primary_client))
        <button type="button" wire:click="removeClientAccount('{{ $user->id }}')"
                wire:confirm="Remove {{ $user->first_name }} {{ $user->last_name }} from {{ $user->org_name }}’s Client Portal? They are signed out at once, their invitation and sign-in links stop working, and they leave this list. What they did stays in the history. You can’t undo this here."
                class="btn btn-ghost btn-sm" style="color: var(--color-danger);">Remove account</button>
    @endif
@endif
