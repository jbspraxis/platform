<div>
    <div class="page-header">
        <div>
            <h1 class="page-title">Sprint boards</h1>
            <p class="page-subtitle">{{ $canSeeAll ? 'Every active project. Open one to see its tasks by status.' : 'The active projects you have tasks on. Open one to see its tasks by status.' }}</p>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($projects as $p)
            {{-- The board is the Sprints tab of the project page (docs/PROJECTS.md) --}}
            <a href="{{ route('hq.ops.project', ['project' => $p->id, 'tab' => 'sprints']) }}" class="card flex flex-col gap-3 no-underline transition-colors hover:border-deep-teal" wire:navigate>
                <div>
                    <div class="text-caption text-muted">{{ $p->client_name }}</div>
                    <div class="mt-1 font-semibold text-ink">{{ $p->title }}</div>
                </div>

                @if(strtolower($p->status) === 'locked')
                    <span class="pill pill-warning w-fit">On hold</span>
                @else
                    <span class="pill pill-success w-fit">Active</span>
                @endif

                <div class="mt-auto flex items-center justify-between border-t border-line pt-3 text-ui font-medium text-deep-teal">
                    <span>Open sprint board</span>
                    <i data-lucide="arrow-right" class="h-4 w-4"></i>
                </div>
            </a>
        @empty
            <div class="card empty-state sm:col-span-2 xl:col-span-3">
                <strong>No sprint boards to show</strong>
                {{ $canSeeAll ? 'A sprint board appears here once a project is active.' : 'A project appears here once a task on it is assigned to you.' }}
            </div>
        @endforelse
    </div>
</div>
