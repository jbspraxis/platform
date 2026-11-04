<div class="ig">
    <style>
        /* System › Integrations: only what the shared component classes don't cover. Brand tokens only. */
        .ig { max-width: 960px; display: flex; flex-direction: column; gap: 24px; }
        .ig-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(min(280px, 100%), 1fr)); }
        .ig-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 16px; }
        .ig-copy { display: flex; gap: 8px; align-items: stretch; }
        .ig-copy code { flex: 1; min-width: 0; overflow-wrap: anywhere; padding: 9px 12px; border: 1px solid var(--color-line); border-radius: var(--radius-control); background: var(--color-mist); color: var(--color-ink); font-size: 0.8125rem; line-height: 1.375rem; }
        .ig-meter { height: 8px; border-radius: 999px; background: var(--color-mist); overflow: hidden; margin: 8px 0 4px; }
        .ig-meter span { display: block; height: 100%; background: var(--color-deep-teal); }
        .ig-meter span.is-over { background: var(--color-danger); }
        .ig-list { margin: 8px 0 0; padding-left: 18px; }
        .ig-section-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        .ig-section-head h2 { margin: 0; }
    </style>

    <div class="page-header" style="margin-bottom: 0">
        <div>
            <h1 class="page-title">{{ __('integrations.title') }}</h1>
            <p class="page-subtitle">{{ __('integrations.subtitle') }}</p>
        </div>
    </div>

    <x-flash />
    @if ($notice)
        <div @class(['alert', 'alert-success' => $noticeTone === 'success', 'alert-danger' => $noticeTone === 'error']) role="{{ $noticeTone === 'error' ? 'alert' : 'status' }}">
            <i data-lucide="{{ $noticeTone === 'error' ? 'circle-alert' : 'circle-check' }}"></i>
            <span>{{ $notice }}</span>
        </div>
    @endif

    {{-- Google --}}
    <section class="card" aria-labelledby="ig-google">
        <div class="ig-section-head">
            <h2 id="ig-google" class="card-title">{{ __('integrations.google.title') }}</h2>
            @if ($google['reconnect'])
                <span class="pill pill-danger">{{ __('integrations.google.status.reconnect') }}</span>
            @elseif ($google['connected'])
                <span class="pill pill-success">{{ __('integrations.google.status.connected', ['email' => $google['email'] ?? '—']) }}</span>
            @else
                <span class="pill pill-neutral">{{ __('integrations.google.status.not_connected') }}</span>
            @endif
        </div>
        <p class="help" style="margin: 0 0 16px">{{ __('integrations.google.intro', ['account' => $google['expected']]) }}</p>

        @if ($google['missing'])
            <div class="alert alert-warning" role="alert" style="margin-bottom: 16px">
                <i data-lucide="triangle-alert"></i>
                <span>{{ __('integrations.google.flash.missing_scopes') }}</span>
            </div>
        @endif

        {{-- Server backups (docs/BACKUPS.md): Drive needs one more permission, given once by the owner on Google's page --}}
        @if ($google['connected'])
            <div @class(['alert', 'alert-success' => $google['drive'], 'alert-warning' => ! $google['drive']]) role="status" style="margin-bottom: 16px">
                <i data-lucide="{{ $google['drive'] ? 'circle-check' : 'triangle-alert' }}"></i>
                <span>{{ $google['drive'] ? __('integrations.google.drive.allowed') : __('integrations.google.drive.missing') }}</span>
            </div>
        @endif

        <div class="field" style="margin-bottom: 16px">
            <span class="label" id="ig-redirect-label">{{ __('integrations.google.redirect_label') }}</span>
            <div class="ig-copy" x-data="{ copied: false }">
                <code aria-labelledby="ig-redirect-label">{{ $google['redirectUri'] }}</code>
                <button type="button" class="btn btn-secondary btn-sm" x-on:click="navigator.clipboard.writeText(@js($google['redirectUri'])); copied = true; setTimeout(() => copied = false, 2000)">
                    <i data-lucide="copy"></i>
                    <span x-show="!copied">{{ __('integrations.copy') }}</span>
                    <span x-show="copied" x-cloak>{{ __('integrations.copied') }}</span>
                </button>
            </div>
            <span class="help">{{ __('integrations.google.redirect_help') }}</span>
        </div>

        <form wire:submit="saveGoogleClient" novalidate>
            <div class="ig-grid">
                <div class="field">
                    <label for="ig-client-id" class="label">{{ __('integrations.google.client_id') }}</label>
                    <input id="ig-client-id" type="text" class="input" wire:model="clientId" autocomplete="off" spellcheck="false"
                           @error('clientId') aria-invalid="true" aria-describedby="ig-client-id-error" @enderror>
                    @error('clientId') <span class="field-error" id="ig-client-id-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label for="ig-client-secret" class="label">{{ __('integrations.google.client_secret') }}</label>
                    <input id="ig-client-secret" type="password" class="input" wire:model="clientSecret" autocomplete="new-password" spellcheck="false"
                           @error('clientSecret') aria-invalid="true" aria-describedby="ig-client-secret-error" @else aria-describedby="ig-client-secret-help" @enderror>
                    @error('clientSecret')
                        <span class="field-error" id="ig-client-secret-error">{{ $message }}</span>
                    @else
                        <span class="help" id="ig-client-secret-help">{{ $google['secretSaved'] ? __('integrations.secret_saved') : __('integrations.google.secret_help') }}</span>
                    @enderror
                </div>
            </div>
            <div class="ig-actions">
                <button type="submit" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="saveGoogleClient">
                    <i data-lucide="save"></i> {{ __('integrations.google.save_client') }}
                </button>
                @if ($google['hasClient'])
                    <a href="{{ route('hq.integrations.google.connect') }}" class="btn btn-primary">
                        <i data-lucide="link"></i> {{ $google['connected'] ? __('integrations.google.reconnect') : __('integrations.google.connect') }}
                    </a>
                @endif
                @if ($google['connected'])
                    <button type="button" class="btn btn-ghost" wire:click="testGoogle" wire:loading.attr="disabled" wire:target="testGoogle">
                        <i data-lucide="activity"></i>
                        <span wire:loading.remove wire:target="testGoogle">{{ __('integrations.google.test') }}</span>
                        <span wire:loading wire:target="testGoogle">{{ __('integrations.testing') }}</span>
                    </button>
                    <button type="button" class="btn btn-ghost" wire:click="disconnectGoogle" wire:confirm="{{ __('integrations.google.disconnect_confirm') }}">
                        <i data-lucide="unlink"></i> {{ __('integrations.google.disconnect') }}
                    </button>
                @endif
            </div>
        </form>
    </section>

    {{-- Claude --}}
    <section class="card" aria-labelledby="ig-claude">
        <div class="ig-section-head">
            <h2 id="ig-claude" class="card-title">{{ __('integrations.claude.title') }}</h2>
            @if ($claude['saved'])
                <span class="pill pill-success">{{ $claude['fromEnv'] ? __('integrations.claude.status.env') : __('integrations.claude.status.saved') }}</span>
            @else
                <span class="pill pill-neutral">{{ __('integrations.claude.status.missing') }}</span>
            @endif
        </div>
        <p class="help" style="margin: 0 0 16px">{{ __('integrations.claude.intro') }}</p>

        <form wire:submit="saveAnthropicKey" novalidate>
            <div class="field" style="max-width: 480px">
                <label for="ig-claude-key" class="label">{{ __('integrations.claude.key') }}</label>
                <input id="ig-claude-key" type="password" class="input" wire:model="anthropicKey" autocomplete="new-password" spellcheck="false"
                       @error('anthropicKey') aria-invalid="true" aria-describedby="ig-claude-key-error" @else aria-describedby="ig-claude-key-help" @enderror>
                @error('anthropicKey')
                    <span class="field-error" id="ig-claude-key-error">{{ $message }}</span>
                @else
                    <span class="help" id="ig-claude-key-help">{{ $claude['saved'] && ! $claude['fromEnv'] ? __('integrations.secret_saved') : __('integrations.claude.key_help') }}</span>
                @enderror
            </div>
            <div class="ig-actions">
                <button type="submit" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="saveAnthropicKey">
                    <i data-lucide="save"></i> {{ __('integrations.claude.save') }}
                </button>
                @if ($claude['saved'])
                    <button type="button" class="btn btn-ghost" wire:click="testClaude" wire:loading.attr="disabled" wire:target="testClaude">
                        <i data-lucide="activity"></i>
                        <span wire:loading.remove wire:target="testClaude">{{ __('integrations.claude.test') }}</span>
                        <span wire:loading wire:target="testClaude">{{ __('integrations.testing') }}</span>
                    </button>
                    @unless ($claude['fromEnv'])
                        <button type="button" class="btn btn-ghost" wire:click="removeAnthropicKey" wire:confirm="{{ __('integrations.claude.remove_confirm') }}">
                            <i data-lucide="trash-2"></i> {{ __('integrations.claude.remove') }}
                        </button>
                    @endunless
                @endif
            </div>
        </form>
        <p class="help" style="margin: 16px 0 0">{{ __('integrations.claude.models') }}</p>
        <p class="help" style="margin: 4px 0 0">{{ $gemini ? __('integrations.gemini.on') : __('integrations.gemini.off') }}</p>
    </section>

    {{-- Budget --}}
    <section class="card" aria-labelledby="ig-budget">
        <div class="ig-section-head">
            <h2 id="ig-budget" class="card-title">{{ __('integrations.budget.title') }}</h2>
            @if ($spend['spent'] >= $spend['cap'])
                <span class="pill pill-danger">{{ __('integrations.budget.status.reached') }}</span>
            @elseif ($spend['percent'] >= 80)
                <span class="pill pill-warning">{{ __('integrations.budget.status.close') }}</span>
            @else
                <span class="pill pill-success">{{ __('integrations.budget.status.ok') }}</span>
            @endif
        </div>

        <div class="stat-label">{{ __('integrations.budget.this_month') }}</div>
        @php $usd = fn (float $v, int $d = 2) => app()->getLocale() === 'fr' ? number_format($v, $d, ',', "\u{202F}") : number_format($v, $d); @endphp
        <div class="stat-value">{{ __('integrations.budget.spent_of', ['spent' => $usd($spend['spent']), 'cap' => $usd($spend['cap'])]) }}</div>
        <div class="ig-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $spend['percent'] }}" aria-label="{{ __('integrations.budget.this_month') }}">
            <span @class(['is-over' => $spend['percent'] >= 100]) style="width: {{ $spend['percent'] }}%"></span>
        </div>
        <p class="help" style="margin: 0 0 16px">{{ __('integrations.budget.help') }}</p>

        <form wire:submit="saveBudget" novalidate class="ig-actions" style="align-items: flex-end; margin-top: 0">
            <div class="field" style="width: 200px">
                <label for="ig-budget-amount" class="label">{{ __('integrations.budget.label') }}</label>
                <input id="ig-budget-amount" type="number" min="0" max="1000" step="0.5" inputmode="decimal" class="input" wire:model="budget"
                       @error('budget') aria-invalid="true" aria-describedby="ig-budget-error" @enderror>
            </div>
            <button type="submit" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="saveBudget">
                <i data-lucide="save"></i> {{ __('integrations.budget.save') }}
            </button>
        </form>
        @error('budget') <span class="field-error" id="ig-budget-error">{{ $message }}</span> @enderror

        <h3 class="section-title" style="margin-top: 24px; font-size: 1rem">{{ __('integrations.budget.by_purpose') }}</h3>
        @if ($spend['byPurpose'])
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('integrations.budget.purpose') }}</th>
                            <th scope="col" class="num">{{ __('integrations.budget.runs') }}</th>
                            <th scope="col" class="num">{{ __('integrations.budget.cost') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($spend['byPurpose'] as $purpose => $row)
                            <tr wire:key="purpose-{{ $purpose }}">
                                <td>{{ __('ai.purposes.' . $purpose) }}</td>
                                <td class="num">{{ $row['runs'] }}</td>
                                <td class="num">{{ __('integrations.budget.usd', ['amount' => $usd($row['cost'], 4)]) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="help" style="margin: 0">{{ __('integrations.budget.none') }}</p>
        @endif
    </section>
</div>
