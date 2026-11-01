{{-- A record's history in a drawer (App\Support\Projects\ProjectHistory::for), newest first --}}
@if ($history->isEmpty())
    <p class="help" style="margin: 0">{{ __('projects.drawer.no_history') }}</p>
@else
    <ol class="pj-list">
        @foreach ($history as $line)
            <li>
                {{ $line->text }}
                <time datetime="{{ \Illuminate\Support\Carbon::parse($line->when)->toIso8601String() }}">{{ $line->who }} · {{ \App\Support\Projects\ProjectPlan::day($line->when, 'datetime') }}</time>
            </li>
        @endforeach
    </ol>
@endif
