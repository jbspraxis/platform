<div>
    {{-- The milestone boards of the active projects (sprints were folded into milestones, docs/PROJECTS.md) --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ __('projects.directory.title') }}</h1>
            <p class="page-subtitle">{{ $canSeeAll ? __('projects.directory.all') : __('projects.directory.mine') }}</p>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($projects as $p)
            {{-- The board is the Milestone board tab of the project page --}}
            <a href="{{ route('hq.ops.project', ['project' => $p->id, 'tab' => 'board']) }}" class="card flex flex-col gap-3 no-underline transition-colors hover:border-deep-teal" wire:navigate>
                <div>
                    <div class="text-caption text-muted">{{ $p->client_name }}</div>
                    <div class="mt-1 font-semibold text-ink">{{ $p->title }}</div>
                </div>

                @if(strtolower($p->status) === 'locked')
                    <span class="pill pill-warning w-fit">{{ __('projects.project_statuses.Locked') }}</span>
                @else
                    <span class="pill pill-success w-fit">{{ __('projects.project_statuses.Active') }}</span>
                @endif

                <div class="mt-auto flex items-center justify-between border-t border-line pt-3 text-ui font-medium text-deep-teal">
                    <span>{{ __('projects.open_board') }}</span>
                    <i data-lucide="arrow-right" class="h-4 w-4"></i>
                </div>
            </a>
        @empty
            <div class="card empty-state sm:col-span-2 xl:col-span-3">
                <strong>{{ __('projects.directory.empty_title') }}</strong>
                {{ $canSeeAll ? __('projects.directory.empty_all') : __('projects.directory.empty_mine') }}
            </div>
        @endforelse
    </div>
</div>
