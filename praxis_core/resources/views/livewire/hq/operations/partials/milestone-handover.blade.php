{{-- One milestone's handover (docs/OPPORTUNITIES.md section 12, PR 6): $h is App\Support\Opportunities\Handovers::forMilestone()
     for that milestone and the signed-in person. Any milestone screen includes it as it is. --}}
<div class="flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0 flex-1" style="min-width: min(100%, 240px)">
        <p class="text-small font-semibold text-ink" style="overflow-wrap: anywhere">{{ $h['milestone']->title }}</p>
        @if(! $h['opportunityId'])
            <p class="mt-1 text-small text-slate">{{ __('handover.board.no_opportunity') }}</p>
        @elseif($h['meeting'])
            <p class="mt-1 text-small text-ink" style="overflow-wrap: anywhere">
                {{ $h['meeting']->title }} · {{ $h['when'] }}
                <span class="pill {{ $h['meeting']->status === 'held' ? 'pill-success' : ($h['meeting']->status === 'requested' ? 'pill-warning' : 'pill-info') }} ml-1">{{ __('opportunities.meetings.statuses.' . $h['meeting']->status) }}</span>
            </p>
            @if($h['meeting']->status === 'held' || $h['meeting']->starts_at->isPast())
                <p class="mt-1 text-caption text-slate">
                    {{ __('meetings.transcript_status.' . $h['meeting']->transcript_status) }}
                    · {{ trans_choice('handover.board.review', $h['review'], ['count' => $h['review']]) }}
                    · {{ trans_choice('handover.board.issue_count', $h['issues'], ['count' => $h['issues'], 'open' => $h['openIssues']]) }}
                </p>
            @endif
        @else
            <p class="mt-1 text-small text-slate">{{ __('handover.board.none') }} @unless($h['canSchedule']){{ __('handover.board.no_permission') }}@endunless</p>
        @endif
    </div>
    @if($h['meetingUrl'] || $h['scheduleUrl'])
        <div class="flex flex-wrap gap-2">
            @if($h['meetingUrl'])
                <a href="{{ $h['meetingUrl'] }}" class="btn btn-secondary btn-sm" wire:navigate><i data-lucide="calendar"></i> {{ __('handover.board.open') }}</a>
            @endif
            @if($h['scheduleUrl'])
                <a href="{{ $h['scheduleUrl'] }}" class="btn {{ $h['meeting'] ? 'btn-ghost' : 'btn-primary' }} btn-sm" wire:navigate
                   aria-label="{{ ($h['meeting'] ? __('handover.board.schedule_another') : __('handover.board.schedule')) . ': ' . $h['milestone']->title }}">
                    <i data-lucide="calendar-plus"></i> {{ $h['meeting'] ? __('handover.board.schedule_another') : __('handover.board.schedule') }}
                </a>
            @endif
        </div>
    @endif
</div>
