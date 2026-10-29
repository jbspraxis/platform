<div>
    <div class="page-header">
        <div>
            <h1 class="page-title">Plan sprints</h1>
            <p class="page-subtitle">{{ $project->title }}. Draft a sprint backlog from the scope of work, check it, then save it to the project’s sprint board.</p>
        </div>
        <a href="{{ route('hq.ops.deployments') }}" class="btn btn-secondary" wire:navigate>
            <i data-lucide="arrow-left"></i> All projects
        </a>
    </div>

    @if (session()->has('success'))
        <div class="alert alert-success mb-4" role="status"><i data-lucide="check-circle"></i> <span>{{ session('success') }}</span></div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-danger mb-4" role="alert"><i data-lucide="circle-alert"></i> <span>{{ session('error') }}</span></div>
    @endif

    <div class="grid items-start gap-6 lg:grid-cols-[2fr_3fr]">

        <div class="card">
            <h2 class="card-title mb-2">Scope of work</h2>
            <p class="help mb-4">The JBS Praxis assistant reads this text to draft sprints and tasks. Remove anything that isn’t scope, or add requirements, before you draft the backlog.</p>

            <div class="field">
                <label for="sow-context" class="label">Scope of work</label>
                <textarea id="sow-context" wire:model="sowContext" class="textarea min-h-[400px]"></textarea>
                @error('sowContext') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <button type="button" wire:click="generateScrumBacklog" class="btn btn-primary mt-4 w-full" wire:loading.attr="disabled" wire:target="generateScrumBacklog">
                <span wire:loading.remove wire:target="generateScrumBacklog" class="inline-flex items-center gap-2">
                    <i data-lucide="sparkles"></i> Draft backlog
                </span>
                <span wire:loading wire:target="generateScrumBacklog">Drafting the backlog…</span>
            </button>
        </div>

        <div class="card">
            <h2 class="card-title mb-4">Backlog for review</h2>

            @if(empty($generatedBacklog))
                <div class="empty-state rounded-lg border border-dashed border-line">
                    <i data-lucide="layout-template" class="mx-auto mb-3 h-8 w-8 text-muted"></i>
                    <strong>No draft yet</strong>
                    Draft the backlog from the scope of work. Its sprints and tasks appear here for you to check and edit before you save them.
                </div>
            @else
                <div class="alert alert-warning mb-4"><i data-lucide="triangle-alert"></i> <span>Check every story point estimate and the acceptance criteria. Edit anything here before you save the backlog.</span></div>

                @foreach($generatedBacklog as $sIndex => $sprint)
                    <div class="mb-4 overflow-hidden rounded-lg border border-line">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-mist px-4 py-3">
                            <div class="field min-w-[200px] flex-1">
                                <label for="sprint-{{ $sIndex }}" class="sr-only">Sprint name</label>
                                <input id="sprint-{{ $sIndex }}" type="text" wire:model="generatedBacklog.{{ $sIndex }}.sprint_title" class="input font-semibold">
                            </div>
                            <span class="help">{{ count($sprint['tasks']) }} {{ count($sprint['tasks']) === 1 ? 'task' : 'tasks' }}</span>
                        </div>

                        @foreach($sprint['tasks'] as $tIndex => $task)
                            <div class="flex flex-wrap items-start gap-4 border-b border-line px-4 py-4 last:border-b-0">
                                <div class="field w-20">
                                    <label for="pts-{{ $sIndex }}-{{ $tIndex }}" class="label">Points</label>
                                    <input id="pts-{{ $sIndex }}-{{ $tIndex }}" type="number" wire:model="generatedBacklog.{{ $sIndex }}.tasks.{{ $tIndex }}.story_points" class="input num text-center">
                                </div>

                                <div class="min-w-[240px] flex-1">
                                    <div class="mb-3 grid gap-3 sm:grid-cols-[1fr_2fr]">
                                        <div class="field">
                                            <label for="phase-{{ $sIndex }}-{{ $tIndex }}" class="label">Phase</label>
                                            <input id="phase-{{ $sIndex }}-{{ $tIndex }}" type="text" wire:model="generatedBacklog.{{ $sIndex }}.tasks.{{ $tIndex }}.phase_title" class="input">
                                        </div>
                                        <div class="field">
                                            <label for="task-{{ $sIndex }}-{{ $tIndex }}" class="label">Task</label>
                                            <input id="task-{{ $sIndex }}-{{ $tIndex }}" type="text" wire:model="generatedBacklog.{{ $sIndex }}.tasks.{{ $tIndex }}.title" class="input font-medium">
                                        </div>
                                    </div>

                                    <div class="field">
                                        <label for="criteria-{{ $sIndex }}-{{ $tIndex }}" class="label">Acceptance criteria</label>
                                        <textarea id="criteria-{{ $sIndex }}-{{ $tIndex }}" wire:model="generatedBacklog.{{ $sIndex }}.tasks.{{ $tIndex }}.description" class="textarea min-h-[80px] text-caption"></textarea>
                                        <span class="help">A list in HTML (ul and li tags).</span>
                                    </div>
                                </div>

                                <div class="pt-6">
                                    <button type="button" wire:click="removeTask({{ $sIndex }}, {{ $tIndex }})" class="btn btn-ghost btn-sm text-danger" title="Remove task" aria-label="Remove task">
                                        <i data-lucide="trash-2"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach

                <button type="button" wire:click="commitBacklogToDatabase" class="btn btn-primary mt-2 w-full" wire:loading.attr="disabled" wire:target="commitBacklogToDatabase">
                    <span wire:loading.remove wire:target="commitBacklogToDatabase" class="inline-flex items-center gap-2">
                        <i data-lucide="save"></i> Save backlog
                    </span>
                    <span wire:loading wire:target="commitBacklogToDatabase">Saving the backlog…</span>
                </button>
            @endif
        </div>

    </div>
</div>
