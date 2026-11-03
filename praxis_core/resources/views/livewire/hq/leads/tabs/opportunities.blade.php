{{-- The lead's opportunities, simply. PR 2 replaces this file with the full tab (docs/LEADS.md section 9). --}}
<section class="card" aria-labelledby="ld-opps-title">
    <div class="ld-card-head">
        <h2 class="card-title" id="ld-opps-title">Opportunities</h2>
        @if ($canOpenOpportunity)
            <a href="{{ route('hq.crm.opportunities', ['new' => 1, 'organization' => $lead->id]) }}" class="btn btn-secondary btn-sm" wire:navigate><i data-lucide="folder-plus"></i> Open an opportunity</a>
        @endif
    </div>
    @if ($opportunities->isEmpty())
        <p class="help" style="margin: 0">No opportunities yet. Open one for each piece of work you discuss with them: a lead can have several, some won and some lost.</p>
    @else
        <ul class="ld-opps">
            @foreach ($opportunities as $o)
                <li wire:key="opp-{{ $o->id }}">
                    <span style="min-width: 0">
                        @if ($canSeeOpportunity($o))
                            <a href="{{ route('hq.crm.opportunity', ['opportunity' => $o->id]) }}" class="font-semibold" wire:navigate style="overflow-wrap: anywhere">{{ $o->title }}</a>
                        @else
                            <span class="font-semibold text-ink" style="overflow-wrap: anywhere">{{ $o->title }}</span>
                        @endif
                        <span class="help" style="display: block">Opened {{ $day($o->created_at) }}@if ($o->lead) · led by {{ trim($o->lead->first_name . ' ' . $o->lead->last_name) }}@endif</span>
                    </span>
                    <span class="pill {{ match ($o->stage) { 'won' => 'pill-success', 'lost' => 'pill-danger', 'on_hold' => 'pill-warning', default => 'pill-info' } }}">{{ $stageLabel($o->stage) }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</section>
