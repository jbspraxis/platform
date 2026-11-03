{{-- Timeline rows (LeadTimeline::for), newest first; $bodies shows what was said or typed under each line --}}
<ol class="ld-feed">
    @foreach ($rows as $row)
        <li wire:key="tl-{{ $row->id }}">
            <span class="ld-feed-icon">@include('livewire.hq.leads.partials.timeline-icon', ['type' => $row->type])</span>
            <div class="ld-feed-text">
                {{ $row->summary }}
                <time class="ld-feed-meta" datetime="{{ \Illuminate\Support\Carbon::parse($row->occurred_at)->toIso8601String() }}">
                    {{ \Illuminate\Support\Carbon::parse($row->occurred_at)->setTimezone('Africa/Lagos')->format('j M Y, H:i') }} · {{ $row->actor_name }}
                    · {{ \App\Support\Leads\LeadTimeline::label($row->type) }}
                </time>
                @if ($bodies && $row->body && trim($row->body) !== trim($row->summary))
                    <p class="ld-feed-body">{{ \Illuminate\Support\Str::limit($row->body, 4000) }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>
