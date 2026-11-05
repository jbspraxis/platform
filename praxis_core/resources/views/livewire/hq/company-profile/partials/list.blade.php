{{--
    One list inside a section (docs/COMPANY_PROFILE.md section 5). Used for every list and for the lists
    inside their items, so credentials inside a team and problems inside a group look and behave the same.

    $list      the list's name              $spec   its definition from App\Support\CompanyProfile\Sections
    $items     the structure's items        $op     the path the component's add/remove/move take
    $sBase     path into $structure         $tBase  path into $en / $fr (both end at the list)
    $editable  whether anything may change  $lang   en, fr or both
--}}
@php
    $isString = $spec['string'] ?? false;
    $singular = $spec['singular'] ?? 'item';
    $langs = $lang === 'both' ? ['en' => 'English', 'fr' => 'French'] : [$lang => $lang === 'fr' ? 'French' : 'English'];
@endphp

<div class="cp-list">
    <div class="cp-list-head">
        <h3 class="cp-list-title">{{ $spec['label'] }}</h3>
        @if ($editable)
            <button type="button" class="btn btn-sm btn-quiet" wire:click="addItem('{{ $op }}')">
                <i data-lucide="plus"></i> Add {{ $singular }}
            </button>
        @endif
    </div>

    @forelse ($items as $i => $item)
        @php $id = $item['id']; @endphp
        <div class="cp-item" wire:key="item-{{ $id }}">
            <div class="cp-item-bar">
                <span class="cp-item-no">{{ $singular }} {{ $i + 1 }}</span>
                <div class="cp-item-tools">
                    @foreach ($spec['shared'] ?? [] as $field => $fieldSpec)
                        @if (($fieldSpec['type'] ?? 'line') === 'switch')
                            <label class="cp-switch">
                                <input type="checkbox" wire:model.live="{{ $sBase }}.{{ $i }}.shared.{{ $field }}"
                                       @disabled(! $editable)>
                                <span>{{ $fieldSpec['label'] }}</span>
                            </label>
                        @endif
                    @endforeach
                    @if ($editable)
                        <button type="button" class="btn btn-icon btn-quiet" title="Move up" aria-label="Move {{ $singular }} {{ $i + 1 }} up"
                                wire:click="moveItem('{{ $op }}', '{{ $id }}', -1)" @disabled($i === 0)><i data-lucide="arrow-up"></i></button>
                        <button type="button" class="btn btn-icon btn-quiet" title="Move down" aria-label="Move {{ $singular }} {{ $i + 1 }} down"
                                wire:click="moveItem('{{ $op }}', '{{ $id }}', 1)" @disabled($i === count($items) - 1)><i data-lucide="arrow-down"></i></button>
                        <button type="button" class="btn btn-icon btn-quiet cp-remove" title="Remove" aria-label="Remove {{ $singular }} {{ $i + 1 }}"
                                wire:click="removeItem('{{ $op }}', '{{ $id }}')"
                                wire:confirm="Remove this {{ $singular }} from both languages?"><i data-lucide="trash-2"></i></button>
                    @endif
                </div>
            </div>

            @if ($isString)
                <div class="cp-langs">
                    @foreach ($langs as $code => $label)
                        <label class="field">
                            <span class="field-label">{{ $label }}</span>
                            @if ($spec['long'] ?? false)
                                <textarea class="input" rows="3" wire:model.live.blur="{{ $code }}.{{ $tBase }}.{{ $id }}" @disabled(! $editable)></textarea>
                            @else
                                <input type="text" class="input" wire:model.live.blur="{{ $code }}.{{ $tBase }}.{{ $id }}" @disabled(! $editable)>
                            @endif
                        </label>
                    @endforeach
                </div>
            @else
                @foreach ($spec['text'] ?? [] as $field => $fieldSpec)
                    <div class="cp-field">
                        <span class="cp-field-label">{{ $fieldSpec['label'] }}</span>
                        <div class="cp-langs">
                            @foreach ($langs as $code => $label)
                                <label class="field">
                                    <span class="field-label">{{ $label }}</span>
                                    @if ($fieldSpec['long'] ?? false)
                                        <textarea class="input" rows="3" wire:model.live.blur="{{ $code }}.{{ $tBase }}.{{ $id }}.text.{{ $field }}" @disabled(! $editable)></textarea>
                                    @else
                                        <input type="text" class="input" wire:model.live.blur="{{ $code }}.{{ $tBase }}.{{ $id }}.text.{{ $field }}" @disabled(! $editable)>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @php $lines = collect($spec['shared'] ?? [])->filter(fn ($s) => ($s['type'] ?? 'line') !== 'switch'); @endphp
                @if ($lines->isNotEmpty())
                    <div class="cp-shared">
                        @foreach ($lines as $field => $fieldSpec)
                            <label class="field">
                                <span class="field-label">{{ $fieldSpec['label'] }} <em>both languages</em></span>
                                <input type="text" class="input" wire:model.live.blur="{{ $sBase }}.{{ $i }}.shared.{{ $field }}" @disabled(! $editable)>
                            </label>
                        @endforeach
                    </div>
                @endif

                @foreach ($spec['lists'] ?? [] as $child => $childSpec)
                    @include('livewire.hq.company-profile.partials.list', [
                        'list' => $child,
                        'spec' => $childSpec,
                        'items' => $item['lists'][$child] ?? [],
                        'op' => $op . ':' . $id . ':' . $child,
                        'sBase' => $sBase . '.' . $i . '.lists.' . $child,
                        'tBase' => $tBase . '.' . $id . '.lists.' . $child,
                        'editable' => $editable,
                        'lang' => $lang,
                    ])
                @endforeach
            @endif
        </div>
    @empty
        <p class="cp-empty">No {{ $spec['label'] }} yet.@if ($editable) Add the first one.@endif</p>
    @endforelse
</div>
