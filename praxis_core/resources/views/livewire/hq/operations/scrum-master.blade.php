<div>
    <div class="page-header">
        <div>
            <h1 class="page-title">Plan milestones</h1>
            <p class="page-subtitle">{{ $project->title }}. Draft the milestones and their tasks from the scope of work, check them, then save them to the project page.</p>
        </div>
        <a href="{{ route('hq.ops.project', ['project' => $projectId]) }}" class="btn btn-secondary" wire:navigate>
            <i data-lucide="arrow-left"></i> {{ __('projects.open_page') }}
        </a>
    </div>

    <x-flash class="mb-4" />

    @if ($blocked !== '')
        {{-- Until per-milestone planning (PR 5b): a project with a plan, or a plan waiting in its project draft, isn't planned again --}}
        <div class="card" role="note">
            <p style="margin: 0 0 12px">{{ __('projects.planner.' . $blocked) }}</p>
            @if ($blocked === 'draft_pending' && $draftOpportunityId)
                <a href="{{ route('hq.crm.opportunity', ['opportunity' => $draftOpportunityId, 'tab' => 'project']) }}" class="btn btn-primary" wire:navigate><i data-lucide="folder-kanban"></i> {{ __('projects.planner.open_draft') }}</a>
            @else
                <a href="{{ route('hq.ops.project', ['project' => $projectId, 'tab' => 'board']) }}" class="btn btn-primary" wire:navigate><i data-lucide="kanban"></i> {{ __('projects.planner.open_board') }}</a>
            @endif
        </div>
    @else
    <div class="grid items-start gap-6 lg:grid-cols-[2fr_3fr]">

        <div class="card">
            <h2 class="card-title mb-2"><label for="sow-context">Scope of work</label></h2>
            <p id="sow-help" class="help mb-4">The JBS Praxis assistant reads this text to draft milestones and tasks. Remove anything that isn’t scope, or add requirements, before you draft the plan.</p>

            <div class="field">
                <textarea id="sow-context" wire:model="sowContext" class="textarea min-h-[400px]" aria-describedby="sow-help"></textarea>
                @error('sowContext') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <button type="button" wire:click="generateScrumBacklog" class="btn btn-primary mt-4 w-full" wire:loading.attr="disabled" wire:target="generateScrumBacklog">
                <span wire:loading.remove wire:target="generateScrumBacklog" class="inline-flex items-center gap-2">
                    <i data-lucide="sparkles"></i> Draft the plan
                </span>
                <span wire:loading wire:target="generateScrumBacklog">Drafting the plan…</span>
            </button>
        </div>

        <div class="card">
            <h2 class="card-title mb-4">Plan for review</h2>

            @if(empty($generatedBacklog))
                <div class="empty-state rounded-lg border border-dashed border-line">
                    <i data-lucide="layout-template" class="mx-auto mb-3 h-8 w-8 text-muted"></i>
                    <strong>No draft yet</strong>
                    Draft the plan from the scope of work. Its milestones and tasks appear here for you to check and edit before you save them.
                </div>
            @else
                <div class="alert alert-warning mb-4"><i data-lucide="triangle-alert"></i> <span>Check every milestone and the acceptance criteria. New milestones start two weeks long after the existing ones; set their real dates and weights on the project page.</span></div>

                @foreach($generatedBacklog as $mIndex => $milestone)
                    <div class="mb-4 overflow-hidden rounded-lg border border-line" wire:key="plan-{{ $mIndex }}">
                        <div class="flex flex-col gap-3 border-b border-line bg-mist px-4 py-3">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="field min-w-[200px] flex-1">
                                    <label for="milestone-{{ $mIndex }}" class="sr-only">Milestone title</label>
                                    <input id="milestone-{{ $mIndex }}" type="text" wire:model="generatedBacklog.{{ $mIndex }}.milestone_title" class="input font-semibold">
                                </div>
                                <span class="help">{{ count($milestone['tasks']) }} {{ count($milestone['tasks']) === 1 ? 'task' : 'tasks' }}</span>
                            </div>
                            <div class="field">
                                <label for="goal-{{ $mIndex }}" class="label">Goal</label>
                                <input id="goal-{{ $mIndex }}" type="text" wire:model="generatedBacklog.{{ $mIndex }}.goal" class="input">
                            </div>
                        </div>

                        @foreach($milestone['tasks'] as $tIndex => $task)
                            <div class="flex flex-wrap items-start gap-4 border-b border-line px-4 py-4 last:border-b-0" wire:key="plan-{{ $mIndex }}-{{ $tIndex }}">
                                <div class="min-w-[240px] flex-1">
                                    <div class="field mb-3">
                                        <label for="task-{{ $mIndex }}-{{ $tIndex }}" class="label">Task</label>
                                        <input id="task-{{ $mIndex }}-{{ $tIndex }}" type="text" wire:model="generatedBacklog.{{ $mIndex }}.tasks.{{ $tIndex }}.title" class="input font-medium">
                                    </div>

                                    <div class="field">
                                        <label for="criteria-{{ $mIndex }}-{{ $tIndex }}" class="label">Acceptance criteria</label>
                                        <textarea id="criteria-{{ $mIndex }}-{{ $tIndex }}" wire:model="generatedBacklog.{{ $mIndex }}.tasks.{{ $tIndex }}.description" class="textarea min-h-[80px] text-caption"></textarea>
                                        <span class="help">A list in HTML (ul and li tags).</span>
                                    </div>
                                </div>

                                <div class="pt-6">
                                    <button type="button" wire:click="removeTask({{ $mIndex }}, {{ $tIndex }})" class="btn btn-ghost btn-sm text-danger" title="Remove task" aria-label="Remove task">
                                        <i data-lucide="trash-2"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach

                <button type="button" wire:click="commitBacklogToDatabase" class="btn btn-primary mt-2 w-full" wire:loading.attr="disabled" wire:target="commitBacklogToDatabase">
                    <span wire:loading.remove wire:target="commitBacklogToDatabase" class="inline-flex items-center gap-2">
                        <i data-lucide="save"></i> Save the plan
                    </span>
                    <span wire:loading wire:target="commitBacklogToDatabase">Saving the plan…</span>
                </button>
            @endif
        </div>

    </div>
    @endif
</div>
