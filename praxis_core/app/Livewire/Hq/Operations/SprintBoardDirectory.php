<?php

namespace App\Livewire\Hq\Operations;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use App\Support\ProjectAccess;

#[Layout('components.layouts.hq')]
class SprintBoardDirectory extends Component
{
    #[Locked]
    public bool $canSeeAll = false; // projects.approve or projects.export (Auditor): every project, not only assigned ones
    public $projects = [];

    public function mount()
    {
        $this->canSeeAll = ProjectAccess::seesEveryProject();
        $this->loadDirectory();
    }

    public function loadDirectory()
    {
        $query = DB::table('projects')
            ->join('organizations', 'projects.organization_id', '=', 'organizations.id')
            ->whereIn('projects.status', ['Active', 'Locked'])
            ->select('projects.id', 'projects.title', 'projects.status', 'organizations.name as client_name')
            ->orderBy('projects.created_at', 'desc');

        // Isolation: without $canSeeAll, they only see projects where they have assigned tasks
        if (! $this->canSeeAll) {
            $query->whereIn('projects.id', ProjectAccess::assignedProjectIds());
        }

        $this->projects = $query->get()->toArray();
    }

    public function render()
    {
        return view('livewire.hq.operations.sprint-board-directory');
    }
}