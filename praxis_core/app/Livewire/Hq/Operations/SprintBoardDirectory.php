<?php

namespace App\Livewire\Hq\Operations;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

#[Layout('components.layouts.hq')]
class SprintBoardDirectory extends Component
{
    #[Locked]
    public bool $canSeeAll = false; // projects.approve or projects.export (Auditor): every project, not only assigned ones
    public $projects = [];

    public function mount()
    {
        $this->canSeeAll = Auth::user()->can('projects.approve') || Auth::user()->can('projects.export');
        $this->loadDirectory();
    }

    public function loadDirectory()
    {
        $query = DB::table('projects')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->whereIn('projects.status', ['Active', 'Locked'])
            ->select('projects.id', 'projects.title', 'projects.status', 'organizations.name as client_name')
            ->orderBy('projects.created_at', 'desc');

        // Isolation: without $canSeeAll, they only see projects where they have assigned tasks.
        // (tasks has no project_id; bridge through project_phases)
        if (! $this->canSeeAll) {
            $assignedProjectIds = DB::table('tasks')
                ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
                ->where('tasks.assigned_to', Auth::id())
                ->pluck('project_phases.project_id')
                ->unique();
                
            $query->whereIn('projects.id', $assignedProjectIds);
        }

        $this->projects = $query->get()->toArray();
    }

    public function render()
    {
        return view('livewire.hq.operations.sprint-board-directory');
    }
}