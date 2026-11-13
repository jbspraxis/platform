<section class="pj-section" aria-labelledby="ms-deps-title-{{ $phaseId }}">
    @php
        $tone = ['needed' => 'pill-neutral', 'requested' => 'pill-info', 'sent' => 'pill-warning', 'received' => 'pill-success', 'waived' => 'pill-neutral'];
        $day = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('j M Y') : null;
    @endphp
    <div class="card-header" style="margin-bottom: 10px">
        <h3 id="ms-deps-title-{{ $phaseId }}" style="margin: 0">{{ __('projects.deps.title') }}</h3>
        @if ($canManage && $editing === null)
            <button type="button" class="btn btn-ghost btn-sm" wire:click="startAdd"><i data-lucide="plus"></i> {{ __('projects.deps.add') }}</button>
        @endif
    </div>

    @if ($summary->total > 0)
        <p class="help" style="margin: 0 0 10px">{{ __('projects.deps.summary', ['done' => $summary->done, 'total' => $summary->total]) }}</p>
    @endif
    @if ($summary->extension > 0)
        <div class="alert alert-warning mb-3" role="note"><i data-lucide="triangle-alert"></i>
            <span>{{ trans_choice('projects.deps.extension', $summary->extension, ['days' => $summary->extension, 'late' => $summary->late]) }}</span>
        </div>
    @endif

    @if ($editing !== null)
        <form wire:submit="save" class="flex flex-col gap-3" style="margin-bottom: 14px" novalidate>
            <div class="pj-inline">
                <div class="field" style="flex: 0 0 6rem">
                    <label class="label" for="dep-code-{{ $phaseId }}">{{ __('projects.deps.group_code') }}</label>
                    <input id="dep-code-{{ $phaseId }}" type="text" class="input" maxlength="12" wire:model="groupCode" placeholder="1.A">
                </div>
                <div class="field" style="flex: 1 1 auto">
                    <label class="label" for="dep-group-{{ $phaseId }}">{{ __('projects.deps.group_title') }}</label>
                    <input id="dep-group-{{ $phaseId }}" type="text" class="input" maxlength="150" wire:model="groupTitle" placeholder="{{ __('projects.deps.group_placeholder') }}">
                </div>
            </div>
            <div class="field">
                <label class="label" for="dep-title-{{ $phaseId }}">{{ __('projects.deps.item') }}</label>
                <textarea id="dep-title-{{ $phaseId }}" class="textarea" rows="2" maxlength="2000" wire:model="title" @error('title') aria-invalid="true" @enderror></textarea>
                @error('title') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="pj-inline">
                <div class="field" style="flex: 0 0 11rem">
                    <label class="label" for="dep-due-{{ $phaseId }}">{{ __('projects.deps.due') }}</label>
                    <input id="dep-due-{{ $phaseId }}" type="date" class="input" wire:model="dueDate">
                    @error('dueDate') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field" style="flex: 1 1 auto">
                    <label class="label" for="dep-note-{{ $phaseId }}">{{ __('projects.deps.note') }}</label>
                    <input id="dep-note-{{ $phaseId }}" type="text" class="input" maxlength="5000" wire:model="note">
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">{{ $editing === 0 ? __('projects.deps.add') : __('projects.deps.save') }}</button>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="cancel">{{ __('projects.deps.cancel') }}</button>
            </div>
        </form>
    @endif

    @if ($summary->total === 0 && $editing === null)
        <p class="help" style="margin: 0">{{ __('projects.deps.none') }}</p>
    @endif

    @foreach ($groups as $group => $items)
        @if ($group !== '')
            <p class="label" style="margin: 12px 0 6px">{{ $group }}</p>
        @endif
        <ul class="pj-waits">
            @foreach ($items as $i)
                <li wire:key="dep-{{ $i->id }}" style="align-items: flex-start; flex-wrap: wrap; gap: 6px">
                    <span style="flex: 1 1 16rem; min-width: 0">
                        <span style="display: block">{{ $i->title }}</span>
                        <span class="help" style="display: block">
                            @if ($i->due_date) {{ __('projects.deps.needed_by', ['date' => $day($i->due_date)]) }} @endif
                            @if ($i->status === 'received' && $i->received_at) · {{ __('projects.deps.received_on', ['date' => $day($i->received_at)]) }} @endif
                            @if ($i->note) · {{ $i->note }} @endif
                        </span>
                        @if ($i->client_note || $i->file_path)
                            <span class="help" style="display: block">
                                {{ __('projects.deps.client_says') }} {{ $i->client_note }}
                                @if ($i->file_path)
                                    <a href="{{ route('hq.file', $i->file_path) }}" target="_blank" rel="noopener">{{ $i->file_name ?: __('projects.deps.file') }}</a>
                                @endif
                            </span>
                        @endif
                    </span>
                    @if ($i->late_days > 0)
                        <span class="pill pill-danger">{{ trans_choice('projects.deps.late', $i->late_days, ['days' => $i->late_days]) }}</span>
                    @endif
                    @if ($canManage)
                        <label class="sr-only" for="dep-status-{{ $i->id }}">{{ __('projects.deps.status_label') }}</label>
                        <select id="dep-status-{{ $i->id }}" class="select" style="width: auto; min-width: 8.5rem; height: 30px; padding-top: 2px; padding-bottom: 2px"
                                wire:change="setStatus({{ $i->id }}, $event.target.value)">
                            @foreach (\App\Support\Projects\ClientDependencies::STATUSES as $s)
                                <option value="{{ $s }}" @selected($i->status === $s)>{{ __('projects.deps.statuses.' . $s) }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="pj-icon-btn is-small" wire:click="startEdit({{ $i->id }})" aria-label="{{ __('projects.deps.edit') }}"><i data-lucide="pencil"></i></button>
                        <button type="button" class="pj-icon-btn is-small" wire:click="remove({{ $i->id }})" wire:confirm="{{ __('projects.deps.remove_confirm') }}" aria-label="{{ __('projects.deps.remove') }}"><i data-lucide="trash-2"></i></button>
                    @else
                        <span class="pill {{ $tone[$i->status] ?? 'pill-neutral' }}">{{ __('projects.deps.statuses.' . $i->status) }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endforeach
</section>
