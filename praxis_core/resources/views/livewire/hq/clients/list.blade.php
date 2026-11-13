<div class="cl">
    @assets
    <style>
        /* HQ › Clients: only what the shared component classes don't cover. Brand tokens only. */
        .cl-tools { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
        .cl-search { width: min(320px, 100%); margin: 0; }
        .cl-count { font-variant-numeric: tabular-nums; color: var(--color-muted); font-weight: 500; }
        .cl-row { cursor: pointer; }
        .cl-row:hover td { background: var(--color-mist); }
        .cl-name { font-weight: 600; color: var(--color-ink); text-decoration: none; overflow-wrap: anywhere; }
        .cl-name:hover { text-decoration: underline; }
        .cl-money { display: block; font-variant-numeric: tabular-nums; }
        .cl-missing { display: inline-flex; align-items: flex-start; gap: 6px; color: var(--color-warning); font-size: 0.8125rem; line-height: 1.125rem; }
        .cl-missing svg, .cl-ok svg { width: 16px; height: 16px; flex-shrink: 0; }
        .cl-ok { display: inline-flex; align-items: center; gap: 6px; color: var(--color-success); font-size: 0.8125rem; }
        .cl-foot { margin-top: 12px; font-size: 0.875rem; color: var(--color-slate); }
        @media (max-width: 600px) { .cl-search { width: 100%; } .cl-tools .segmented { width: 100%; } }
    </style>
    @endassets

    @php
        $money = fn ($currency, $amount) => \App\Livewire\Hq\HqMoney::format($currency, $amount, 0);
        $missingText = fn (array $keys) => __('clients.missing', ['list' => collect($keys)->map(fn ($k) => __('clients.missing_names.' . $k))->implode(', ')]);
        $columns = 4 + ($canSeeMoney ? 1 : 0);
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">{{ __('clients.title') }}</h1>
            <p class="page-subtitle">{{ __('clients.subtitle') }}</p>
        </div>
    </div>

    <div class="cl-tools">
        <div class="segmented" role="group" aria-label="{{ __('clients.show_label') }}">
            @foreach (array_keys(\App\Support\Clients\Clients::SHOW) as $key)
                <button type="button" class="segmented-item" aria-pressed="{{ $show === $key ? 'true' : 'false' }}" wire:click="showOnly('{{ $key }}')" wire:key="show-{{ $key }}">
                    {{ __('clients.show.' . $key) }} <span class="cl-count">{{ number_format($counts[$key]) }}</span>
                </button>
            @endforeach
        </div>
        <div class="field cl-search">
            <label class="sr-only" for="cl-search">{{ __('clients.search') }}</label>
            <input id="cl-search" type="search" class="input" maxlength="100" wire:model.live.debounce.300ms="search" placeholder="{{ __('clients.search_placeholder') }}" autocomplete="off">
        </div>
    </div>

    <div class="card" style="padding: 0;">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="min-width: 220px;">{{ __('clients.columns.client') }}</th>
                        <th style="min-width: 180px;">{{ __('clients.columns.contact') }}</th>
                        <th style="min-width: 150px;">{{ __('clients.columns.projects') }}</th>
                        @if ($canSeeMoney)
                            <th class="num" style="text-align: right; min-width: 130px;">{{ __('clients.columns.unpaid') }}</th>
                        @endif
                        <th style="min-width: 200px;">{{ __('clients.columns.details') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $client)
                        @php
                            $url = route('hq.client', ['organization' => $client->id]);
                            $missing = \App\Support\Clients\Clients::missing($client);
                        @endphp
                        <tr class="cl-row" wire:key="client-{{ $client->id }}" x-on:click="if (! $event.target.closest('a, button')) Livewire.navigate('{{ $url }}')">
                            <td>
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ $url }}" class="cl-name" wire:navigate>{{ $client->name }}</a>
                                    @if ($client->status === 'Past Client')
                                        <span class="pill pill-neutral">{{ __('clients.status.Past Client') }}</span>
                                    @endif
                                </div>
                                @if ($client->city || $client->country)
                                    <div class="text-caption text-muted mt-1">{{ collect([$client->city, $client->country])->filter()->implode(', ') }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="text-ink">{{ $client->main_contact ?: __('clients.no_contact') }}</div>
                                <div class="text-caption text-muted">{{ trans_choice('clients.contacts_count', (int) $client->contacts, ['count' => (int) $client->contacts]) }}</div>
                            </td>
                            <td>
                                @if ((int) $client->projects_total > 0)
                                    <span class="text-ink">{{ __('clients.projects_value', ['running' => (int) $client->projects_running, 'total' => (int) $client->projects_total]) }}</span>
                                @else
                                    <span class="text-caption text-muted">{{ __('clients.no_projects') }}</span>
                                @endif
                            </td>
                            @if ($canSeeMoney)
                                <td class="num">
                                    @forelse ($unpaid[$client->id] ?? [] as $currency => $amount)
                                        <span class="cl-money font-semibold text-ink">{{ $money($currency, $amount) }}</span>
                                    @empty
                                        <span class="text-caption text-muted">{{ __('clients.nothing_owed') }}</span>
                                    @endforelse
                                </td>
                            @endif
                            <td>
                                @if ($missing)
                                    <span class="cl-missing"><i data-lucide="triangle-alert" aria-hidden="true"></i> {{ $missingText($missing) }}</span>
                                @else
                                    <span class="cl-ok"><i data-lucide="circle-check" aria-hidden="true"></i> {{ __('clients.complete') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $columns }}">
                                <div class="empty-state">
                                    @if (trim($search) !== '')
                                        <strong>{{ __('clients.empty.search', ['term' => trim($search)]) }}</strong>
                                        <button type="button" class="btn btn-secondary btn-sm mt-2" wire:click="clearSearch">{{ __('clients.empty.clear') }}</button>
                                    @else
                                        <strong>{{ __('clients.empty.' . $show) }}</strong>
                                        {{ __('clients.empty.how') }}
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($clients->isNotEmpty())
        <p class="cl-foot" aria-live="polite">{{ trans_choice('clients.count', $clients->count(), ['count' => number_format($clients->count())]) }}</p>
    @endif
</div>
