{{-- The AI's internal notes of a meeting (meeting_notes in config/ai.php): summary, decisions, needs, risks, open questions, next steps. $notes is the stored array. --}}
<div class="mtg-notes">
    @if (filled($notes['summary'] ?? null))
        <p class="mtg-notes-summary">{{ $notes['summary'] }}</p>
    @endif
    @foreach (['decisions', 'needs', 'risks', 'open_questions'] as $key)
        @php($items = array_values(array_filter((array) ($notes[$key] ?? []), 'filled')))
        @if ($items)
            <div class="mtg-notes-group">
                <h5 class="mtg-notes-label">{{ __('meetings.notes.' . $key) }}</h5>
                <ul>
                    @foreach ($items as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endforeach
    @php($steps = array_values(array_filter((array) ($notes['next_steps'] ?? []), fn ($s) => filled($s['action'] ?? null))))
    @if ($steps)
        <div class="mtg-notes-group">
            <h5 class="mtg-notes-label">{{ __('meetings.notes.next_steps') }}</h5>
            <ul>
                @foreach ($steps as $step)
                    <li>{{ $step['action'] }}@if (filled($step['owner'] ?? null) || filled($step['due'] ?? null))<span class="muted"> · {{ collect([$step['owner'] ?? null, $step['due'] ?? null])->filter()->implode(', ') }}</span>@endif</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
