<?php

namespace App\Livewire\Hq\Projects;

use App\Support\Projects\ClientDependencies;
use App\Support\Projects\ProjectRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The milestone drawer's "From the client" section (owner, 13 Nov 2026): what this milestone needs from the client,
 * grouped ("1.A Organisation and people data"), each with its needed-by date and status, late items and the day-for-day
 * extension they earn (SOW clause 8.2). Everyone who sees the project reads it; its lead and managers add, change,
 * move and remove items. The client sees the open ones in the portal and marks them sent.
 */
class MilestoneDependencies extends Component
{
    #[Locked]
    public string $projectId;

    #[Locked]
    public string $phaseId;

    /** null: not editing; 0: adding; an id: editing that item */
    #[Locked]
    public ?int $editing = null;

    public string $groupCode = '';
    public string $groupTitle = '';
    public string $title = '';
    public string $dueDate = '';
    public string $note = '';

    private ?bool $canManage = null;

    private function canManage(): bool
    {
        return $this->canManage ??= ProjectRules::canManage($this->projectId, ProjectRules::canView($this->projectId));
    }

    public function mount(string $projectId, string $phaseId): void
    {
        abort_unless(ProjectRules::canView($projectId), 403);
        abort_unless(DB::table('project_phases')->where('id', $phaseId)->where('project_id', $projectId)->exists(), 404);
        $this->projectId = $projectId;
        $this->phaseId = $phaseId;
    }

    private function phase(): object
    {
        return DB::table('project_phases')->where('id', $this->phaseId)->where('project_id', $this->projectId)->whereNull('deleted_at')->firstOrFail();
    }

    private function item(int $id): object
    {
        return DB::table('milestone_dependencies')->where('id', $id)->where('phase_id', $this->phaseId)->whereNull('deleted_at')->firstOrFail();
    }

    public function startAdd(): void
    {
        abort_unless($this->canManage(), 403);
        $last = DB::table('milestone_dependencies')->where('phase_id', $this->phaseId)->whereNull('deleted_at')->orderByDesc('position')->first(['group_code', 'group_title']);
        $this->resetErrorBag();
        $this->editing = 0;
        $this->groupCode = (string) ($last->group_code ?? '');
        $this->groupTitle = (string) ($last->group_title ?? '');
        $this->title = '';
        $this->dueDate = (string) ClientDependencies::defaultDue($this->phase());
        $this->note = '';
    }

    public function startEdit(int $id): void
    {
        abort_unless($this->canManage(), 403);
        $item = $this->item($id);
        $this->resetErrorBag();
        $this->editing = $id;
        $this->groupCode = (string) $item->group_code;
        $this->groupTitle = (string) $item->group_title;
        $this->title = (string) $item->title;
        $this->dueDate = (string) $item->due_date;
        $this->note = (string) $item->note;
    }

    public function cancel(): void
    {
        $this->editing = null;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        abort_unless($this->canManage() && $this->editing !== null, 403);
        $this->validate([
            'groupCode' => ['nullable', 'string', 'max:12'],
            'groupTitle' => ['nullable', 'string', 'max:150'],
            'title' => ['required', 'string', 'max:2000'],
            'dueDate' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:5000'],
        ], ['title.required' => __('projects.deps.title_required')]);

        $values = ['group_code' => $this->groupCode, 'group_title' => $this->groupTitle, 'title' => $this->title, 'due_date' => $this->dueDate, 'note' => $this->note];
        if ($this->editing === 0) {
            ClientDependencies::add($this->phase(), $values, Auth::id());
        } else {
            $this->item($this->editing);
            DB::table('milestone_dependencies')->where('id', $this->editing)->update([
                'group_code' => trim($this->groupCode) ?: null,
                'group_title' => trim($this->groupTitle) ?: null,
                'title' => trim($this->title),
                'due_date' => $this->dueDate ?: null,
                'note' => trim($this->note) ?: null,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]);
        }
        $this->editing = null;
        $this->dispatch('project-changed', kind: 'milestone');
    }

    public function setStatus(int $id, string $status): void
    {
        abort_unless($this->canManage(), 403);
        validator(['status' => $status], ['status' => [Rule::in(ClientDependencies::STATUSES)]])->validate();
        $this->item($id);
        ClientDependencies::setStatus($id, $status, Auth::id());
        $this->dispatch('project-changed', kind: 'milestone');
    }

    public function remove(int $id): void
    {
        abort_unless($this->canManage(), 403);
        $this->item($id);
        DB::table('milestone_dependencies')->where('id', $id)->update(['deleted_at' => now(), 'updated_by' => Auth::id()]);
        if ($this->editing === $id) {
            $this->editing = null;
        }
        $this->dispatch('project-changed', kind: 'milestone');
    }

    public function render()
    {
        $items = ClientDependencies::forPhase($this->phaseId);

        return view('livewire.hq.projects.milestone-dependencies', [
            'groups' => $items->groupBy(fn ($i) => trim(($i->group_code ? $i->group_code . ' ' : '') . ($i->group_title ?? ''))),
            'summary' => (object) [
                'total' => $items->count(),
                'done' => $items->whereIn('status', ['received', 'waived'])->count(),
                'late' => $items->filter(fn ($i) => in_array($i->status, ClientDependencies::OPEN, true) && $i->late_days > 0)->count(),
                'extension' => ClientDependencies::extensionDays($items),
            ],
            'canManage' => $this->canManage(),
        ]);
    }
}
