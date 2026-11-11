@php
    use App\Support\Campaigns\Marketing;
    $kindNames = ['client' => 'Clients', 'lead' => 'Leads', 'kaizen' => 'Kaizen writers', 'subscriber' => 'Subscribers'];
    $day = fn ($at) => $at ? \Carbon\Carbon::parse($at)->timezone('Africa/Lagos')->format('j M Y') : '—';
    $tabs = ['segments' => 'Segments', 'subscribers' => 'Subscribers', 'suppressed' => 'Do not email'];
    $sources = ['broadcast' => 'Broadcast link', 'outreach' => 'Outreach', 'legacy_link' => 'Old broadcast link', 'backfill' => 'Unsubscribed before 3 Nov 2026', 'hq' => 'Added in HQ'];
@endphp
<div class="aud">
    @assets
    <style>
        /* Campaigns › Audiences (docs/CAMPAIGNS.md 4.2): only what the shared component classes don't cover. Brand tokens only. */
        .aud-tools { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px 16px; margin-bottom: 16px; }
        .aud-tools .field { flex: 1 1 240px; max-width: 360px; }
        .aud-table td small { display: block; color: var(--color-muted); font-size: 0.8125rem; }
        .aud-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .aud-form { display: flex; flex-direction: column; gap: 16px; }
        .aud-two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 16px; }
        .aud-modal { max-width: 600px; }
        .aud-tabs .tab span { margin-left: 6px; color: var(--color-muted); font-variant-numeric: tabular-nums; }
        @media (max-width: 600px) { .aud-two { grid-template-columns: 1fr; } .aud .modal-actions { flex-direction: column-reverse; } .aud .modal-actions .btn { width: 100%; } }
    </style>
    @endassets

    <div class="page-header">
        <div>
            <h1 class="page-title">Audiences</h1>
            <p class="page-subtitle">Who broadcasts can go to: saved segments of clients, leads and subscribers. The do-not-email list stops all marketing, from Broadcasts and Outreach, to every address on it.</p>
        </div>
    </div>


    <nav class="tabs aud-tabs" aria-label="Audiences">
        @foreach ($tabs as $key => $label)
            <button type="button" class="tab {{ $tab === $key ? 'is-active' : '' }}" aria-current="{{ $tab === $key ? 'page' : 'false' }}" wire:click="$set('tab', '{{ $key }}')">{{ $label }}<span>{{ $totals[$key] }}</span></button>
        @endforeach
    </nav>

    @if ($tab === 'segments')
        <div class="aud-tools">
            <p class="help m-0">A segment is worked out again each time a broadcast starts, so it always reflects today’s clients, leads and unsubscribes.</p>
            @if ($canCreate)
                <button type="button" class="btn btn-primary" wire:click="newSegment"><i data-lucide="plus"></i> New segment</button>
            @endif
        </div>
        <div class="card" style="padding: 0">
            @if ($segments->isEmpty())
                <div class="empty-state">
                    <p>No segments yet. Save an audience, such as “Clients in Cameroon” or “Banking leads in French”, to send broadcasts to it.</p>
                    @if ($canCreate)<button type="button" class="btn btn-primary mt-3" wire:click="newSegment"><i data-lucide="plus"></i> Create a segment</button>@endif
                </div>
            @else
                <div class="table-wrap">
                    <table class="table aud-table">
                        <thead><tr><th scope="col">Segment</th><th scope="col" class="aud-num">People today</th><th scope="col">In each group</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                        <tbody>
                            @foreach ($segments as $row)
                                @php [$s, $sum] = [$row['segment'], $row['summary']]; @endphp
                                <tr wire:key="s-{{ $s->id }}">
                                    <td><strong>{{ $s->name }}</strong><small>{{ $s->describe() }}</small></td>
                                    <td class="aud-num">{{ $sum['total'] }}<small>{{ $sum['by_language']['en'] }} EN · {{ $sum['by_language']['fr'] }} FR</small></td>
                                    <td>{{ collect($sum['by_kind'])->map(fn ($n, $k) => $kindNames[$k] . ' ' . $n)->implode(' · ') ?: '—' }}@if ($sum['left_out'])<small>{{ $sum['left_out'] }} left out: do not email</small>@endif</td>
                                    <td class="aud-num">
                                        @if ($canCreate)
                                            <button type="button" class="btn btn-ghost btn-sm" wire:click="editSegment('{{ $s->id }}')">Edit</button>
                                            <button type="button" class="btn btn-ghost btn-sm" wire:click="deleteSegment('{{ $s->id }}')" wire:confirm="Delete the segment “{{ $s->name }}”? Sent broadcasts keep their numbers; drafts that use it will need another segment.">Delete</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    @elseif ($tab === 'subscribers')
        <div class="aud-tools">
            <div class="field">
                <label for="aud-search" class="label">Search</label>
                <input id="aud-search" type="search" class="input" wire:model.live.debounce.300ms="search" placeholder="Name, email or organisation">
            </div>
            @if ($canCreate)
                <button type="button" class="btn btn-primary" wire:click="$set('adding', true)"><i data-lucide="user-plus"></i> Add subscribers</button>
            @endif
        </div>
        <p class="help">People who asked to hear from us without being a client’s or a lead’s contact. People who wrote for JBS Praxis Insights through the website count as subscribers too ({{ $kaizenWriters }} so far). A sign-up form on the website comes later, once counsel has confirmed the consent wording.</p>
        <div class="card" style="padding: 0">
            @if ($subscribers->isEmpty())
                <div class="empty-state">
                    <p>{{ $search !== '' ? 'Nobody matches “' . $search . '”.' : 'No subscribers yet. Add the people who asked to hear from us, for example at an event.' }}</p>
                    @if ($canCreate && $search === '')<button type="button" class="btn btn-primary mt-3" wire:click="$set('adding', true)"><i data-lucide="user-plus"></i> Add subscribers</button>@endif
                </div>
            @else
                <div class="table-wrap">
                    <table class="table aud-table">
                        <thead><tr><th scope="col">Subscriber</th><th scope="col">Country</th><th scope="col">Language</th><th scope="col">How they agreed</th><th scope="col">Added</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                        <tbody>
                            @foreach ($subscribers as $sub)
                                <tr wire:key="sub-{{ $sub->id }}">
                                    <td>{{ trim($sub->first_name . ' ' . $sub->last_name) ?: $sub->email }}<small>{{ trim($sub->first_name . ' ' . $sub->last_name) ? $sub->email : '' }}{{ $sub->organisation ? ' · ' . $sub->organisation : '' }}</small></td>
                                    <td>{{ $sub->country ?: '—' }}</td>
                                    <td>{{ $languageOf($sub) === 'fr' ? 'French' : 'English' }}@unless ($sub->language)<small>from the country</small>@endunless</td>
                                    <td>{{ $sub->consent ?: '—' }}</td>
                                    <td>{{ $day($sub->created_at) }}@if ($sub->unsubscribed_at)<small><span class="pill pill-warning">Unsubscribed</span></small>@endif</td>
                                    <td class="aud-num">
                                        @if ($canCreate)
                                            <button type="button" class="btn btn-ghost btn-sm" wire:click="removeSubscriber({{ $sub->id }})" wire:confirm="Remove {{ $sub->email }} from the subscriber list?">Remove</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        <div class="mt-4">{{ $subscribers->links() }}</div>

    @else
        <div class="aud-tools">
            <div class="field">
                <label for="aud-search2" class="label">Search</label>
                <input id="aud-search2" type="search" class="input" wire:model.live.debounce.300ms="search" placeholder="Email address">
            </div>
            @if ($canCreate)
                <button type="button" class="btn btn-secondary" wire:click="$set('blocking', true)"><i data-lucide="ban"></i> Add an address</button>
            @endif
        </div>
        <p class="help">Nobody on this list gets a broadcast or an outreach email, whichever list they are on. People who unsubscribed or marked an email as spam stay on it; only addresses added by hand or that bounced can be taken off.</p>
        <div class="card" style="padding: 0">
            @if ($entries->isEmpty())
                <p class="empty-state">{{ $search !== '' ? 'No address matches “' . $search . '”.' : 'Nobody is on the do-not-email list.' }}</p>
            @else
                <div class="table-wrap">
                    <table class="table aud-table">
                        <thead><tr><th scope="col">Address</th><th scope="col">Why</th><th scope="col">From</th><th scope="col">Since</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                        <tbody>
                            @foreach ($entries as $e)
                                <tr wire:key="x-{{ $e->id }}">
                                    <td>{{ $e->email }}@if ($e->note)<small>{{ $e->note }}</small>@endif</td>
                                    <td><span class="pill {{ in_array($e->reason, Marketing::PERSON_ONLY, true) ? 'pill-neutral' : 'pill-warning' }}">{{ Marketing::REASONS[$e->reason] ?? $e->reason }}</span></td>
                                    <td>{{ $sources[$e->source] ?? ($e->source ?: '—') }}@if ($e->broadcast_id)<small><a href="{{ route('hq.campaigns.report', ['broadcast' => $e->broadcast_id]) }}" wire:navigate>The broadcast</a></small>@endif</td>
                                    <td>{{ $day($e->created_at) }}</td>
                                    <td class="aud-num">
                                        @if ($canApprove && ! in_array($e->reason, Marketing::PERSON_ONLY, true))
                                            <button type="button" class="btn btn-ghost btn-sm" wire:click="unblock({{ $e->id }})" wire:confirm="Take {{ $e->email }} off the do-not-email list? Broadcasts and Outreach can email it again.">Take off the list</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        <div class="mt-4">{{ $entries->links() }}</div>
    @endif

    @if ($adding)
        <div class="modal-backdrop" wire:click.self="$set('adding', false)" @keydown.escape.window="$wire.set('adding', false)">
            <div class="modal aud-modal" role="dialog" aria-modal="true" aria-labelledby="aud-add">
                <h2 class="modal-title" id="aud-add">Add subscribers</h2>
                <p class="help mb-4">Only people who asked to hear from JBS Praxis. Addresses on the do-not-email list are skipped.</p>
                <form class="aud-form" wire:submit="addSubscribers">
                    <div class="field">
                        <label class="label" for="aud-lines">People, one per line</label>
                        <textarea id="aud-lines" class="textarea" rows="7" wire:model="addLines" placeholder="ada@example.com&#10;Ada Okafor <ada@example.com>&#10;ada@example.com, Ada, Okafor, Acme Ltd"></textarea>
                        <span class="help">An address alone, “Name &lt;address&gt;”, or address, first name, last name, organisation.</span>
                        @error('addLines') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="aud-two">
                        <div class="field">
                            <label class="label" for="aud-country">Country <span class="help">(optional)</span></label>
                            <select id="aud-country" class="select" wire:model="addCountry">
                                <option value="">Not known</option>
                                @foreach ($countries as $name)<option value="{{ $name }}">{{ $name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="aud-language">Language <span class="help">(optional)</span></label>
                            <select id="aud-language" class="select" wire:model="addLanguage">
                                <option value="">From the country</option>
                                <option value="en">English</option>
                                <option value="fr">French</option>
                            </select>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="aud-consent">How they agreed to hear from us</label>
                        <input id="aud-consent" type="text" class="input" maxlength="255" wire:model="addConsent" placeholder="e.g. Signed up at the Lagos breakfast, 20 Nov 2026">
                        <span class="help">Kept with each address, in case anyone asks why they get our emails.</span>
                        @error('addConsent') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" wire:click="$set('adding', false)">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add subscribers</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($blocking)
        <div class="modal-backdrop" wire:click.self="$set('blocking', false)" @keydown.escape.window="$wire.set('blocking', false)">
            <div class="modal aud-modal" role="dialog" aria-modal="true" aria-labelledby="aud-block">
                <h2 class="modal-title" id="aud-block">Add an address to the do-not-email list</h2>
                <p class="help mb-4">For someone who asked us, by phone or email, to stop. They get no more broadcasts or outreach; emails about work we do with them still reach them.</p>
                <form class="aud-form" wire:submit="block">
                    <div class="field">
                        <label class="label" for="aud-email">Email address</label>
                        <input id="aud-email" type="email" class="input" maxlength="190" wire:model="blockEmail" autofocus>
                        @error('blockEmail') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="aud-note">Note <span class="help">(optional)</span></label>
                        <input id="aud-note" type="text" class="input" maxlength="255" wire:model="blockNote" placeholder="e.g. Asked by phone, 3 Nov">
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" wire:click="$set('blocking', false)">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add to the list</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @include('livewire.hq.campaigns.partials.segment-builder', ['onSave' => 'saveSegment'])
</div>
