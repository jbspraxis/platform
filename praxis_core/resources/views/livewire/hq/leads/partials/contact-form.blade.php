{{-- Add or change one contact on the lead page ($portal: a portal account keeps its name, email and phone) --}}
<form wire:submit="saveContact" class="ld-form ld-contact-edit" novalidate aria-label="{{ $title }}">
    @if ($portal)
        <p class="help" style="margin: 0">{{ $contact['name'] }} has a Client Portal account: their name, email and phone change in the portal. Here you set their job title and role in the deal.</p>
    @else
        <div class="ld-row">
            <div class="field">
                <label class="label" for="ld-c-name">Name</label>
                <input id="ld-c-name" type="text" class="input" maxlength="100" wire:model="contact.name" autocomplete="off" @error('contact.name') aria-invalid="true" aria-describedby="ld-c-name-error" @enderror>
                @error('contact.name') <span class="field-error" id="ld-c-name-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="label" for="ld-c-email">Email <span class="ld-optional">(optional)</span></label>
                <input id="ld-c-email" type="email" class="input" maxlength="100" wire:model="contact.email" placeholder="e.g. name@company.com" autocomplete="off" @error('contact.email') aria-invalid="true" aria-describedby="ld-c-email-error" @enderror>
                @error('contact.email') <span class="field-error" id="ld-c-email-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="label" for="ld-c-phone">Phone <span class="ld-optional">(optional)</span></label>
                <input id="ld-c-phone" type="tel" class="input" maxlength="30" wire:model="contact.phone" placeholder="e.g. +234 800 000 0000" autocomplete="off" @error('contact.phone') aria-invalid="true" aria-describedby="ld-c-phone-error" @enderror>
                @error('contact.phone') <span class="field-error" id="ld-c-phone-error">{{ $message }}</span> @enderror
            </div>
        </div>
    @endif
    <div class="ld-row">
        <div class="field">
            <label class="label" for="ld-c-title">Job title <span class="ld-optional">(optional)</span></label>
            <input id="ld-c-title" type="text" class="input" maxlength="100" wire:model="contact.job_title" autocomplete="off">
            @error('contact.job_title') <span class="field-error">{{ $message }}</span> @enderror
        </div>
        <div class="field">
            <label class="label" for="ld-c-role">Role in the deal <span class="ld-optional">(optional)</span></label>
            <select id="ld-c-role" class="select" wire:model="contact.deal_role">
                <option value="">Not known yet</option>
                @foreach (\App\Support\Leads\Contacts::DEAL_ROLES as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('contact.deal_role') <span class="field-error">{{ $message }}</span> @enderror
        </div>
    </div>
    <label class="ld-radios"><span><input type="checkbox" class="checkbox" wire:model="contact.main"> Main contact</span></label>
    @error('contact.contact') <span class="field-error">{{ $message }}</span> @enderror
    <div class="ld-actions">
        <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="saveContact"><i data-lucide="save"></i> {{ $contactEditing === 'new' ? 'Add contact' : 'Save contact' }}</button>
        <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelContact">Cancel</button>
    </div>
</form>
