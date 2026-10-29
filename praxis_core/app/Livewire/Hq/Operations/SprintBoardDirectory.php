<?php

namespace App\Livewire\Hq\Operations;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

#[Layout('components.layouts.hq')]
class SprintBoardDirectory extends Component
{
    public $userClearance;
    public $projects = [];

    public function mount()
    {
        $this->userClearance = Auth::user()->role->clearance_level ?? 0;
        $this->loadDirectory();
    }

    public function loadDirectory()
    {
        $query = DB::table('projects')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->whereIn('projects.status', ['Active', 'Locked'])
            ->select('projects.id', 'projects.title', 'projects.status', 'organizations.name as client_name')
            ->orderBy('projects.created_at', 'desc');

        // Isolation: If Level 2 or 3, they only see projects where they have assigned tasks.
        if ($this->userClearance < 4) {
            $assignedProjectIds = DB::table('tasks')
                ->where('assigned_to', Auth::id())
                ->pluck('project_id')
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