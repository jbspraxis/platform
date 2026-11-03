<div>
    <button type="button" class="btn btn-secondary" wire:click="show"><i data-lucide="settings"></i> Lead settings</button>

    @if ($open)
        <div class="modal-backdrop" style="z-index: 60" wire:keydown.escape.window="close">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="lead-settings-title" style="max-width: 480px">
                <div class="flex items-start justify-between gap-4">
                    <h2 id="lead-settings-title" class="modal-title">Lead settings</h2>
                    <button type="button" wire:click="close" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
                </div>
                <x-hq.picker id="lead-default-owner" label="Website leads go to" model="search" :options="$options" :selected="$selected"
                             choose="choose" remove="change" placeholder="Type a name" :empty="'No one matches ‘:term’.'" :term="$search"
                             :error="$errors->first('defaultOwner')"
                             help="New leads from the contact form, free audit bookings and the cost calculator are given to this person, who gets a notification. Leads you add yourself stay yours." />
                <div class="modal-actions">
                    @if ($hasOwner)
                        <button type="button" class="btn btn-ghost" wire:click="clear" wire:confirm="Stop giving website leads to anyone in particular? New ones will have no owner until someone takes them.">Give them to no one</button>
                    @endif
                    <button type="button" class="btn btn-secondary" wire:click="close">Close</button>
                </div>
            </div>
        </div>
    @endif
</div>
