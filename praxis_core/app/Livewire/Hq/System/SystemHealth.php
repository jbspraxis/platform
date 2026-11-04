<?php

namespace App\Livewire\Hq\System;

use App\Support\Backups\Backups;
use App\Support\Backups\Status;
use App\Support\Monitor\ErrorMonitor;
use App\Support\Monitor\HealthChecks;
use App\Support\Monitor\LogScanner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * HQ → System Health: health checks, and errors grouped by kind with status,
 * notes, "Copy for AI" and a Markdown export of the ticked ones.
 * The data comes from app/Support/Monitor (monitor:run every 5 minutes).
 */
#[Layout('components.layouts.hq')]
#[Title('System health')]
class SystemHealth extends Component
{
    private const STATUSES = ['new', 'investigating', 'fixed', 'ignored'];

    public string $tab = 'health';          // health | errors
    public string $statusFilter = 'open';   // open | all | fixed | ignored
    public string $search = '';
    public array $selected = [];
    public string $noteText = '';

    #[Locked]
    public ?int $openGroupId = null;
    #[Locked]
    public ?string $flash = null;

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['health', 'errors'], true) ? $tab : 'health';
        $this->openGroupId = null;
    }

    public function setFilter(string $filter): void
    {
        $this->statusFilter = in_array($filter, ['open', 'all', 'fixed', 'ignored'], true) ? $filter : 'open';
        $this->selected = [];
    }

    public function openGroup(int $id): void
    {
        $this->openGroupId = $id;
        $this->noteText = '';
    }

    public function closeGroup(): void
    {
        $this->openGroupId = null;
    }

    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            return;
        }
        DB::table('monitor_error_groups')->where('id', $id)->update(['status' => $status, 'updated_at' => now()]);
        $this->flash = 'Status changed to ' . $status . '.';
    }

    public function bulkStatus(string $status): void
    {
        $ids = array_map('intval', $this->selected);
        if (!$ids || !in_array($status, self::STATUSES, true)) {
            return;
        }
        DB::table('monitor_error_groups')->whereIn('id', $ids)->update(['status' => $status, 'updated_at' => now()]);
        $this->flash = count($ids) . ' error type(s) marked ' . $status . '.';
        $this->selected = [];
    }

    public function addNote(): void
    {
        $this->validate(['noteText' => 'required|string|max:2000']);
        if (!$this->openGroupId) {
            return;
        }
        $group = DB::table('monitor_error_groups')->where('id', $this->openGroupId)->first();
        if (!$group) {
            return;
        }
        $who = trim((Auth::user()->first_name ?? '') . ' ' . (Auth::user()->last_name ?? '')) ?: 'HQ';
        $line = '[' . now('Africa/Lagos')->format('Y-m-d H:i') . " {$who}] " . trim($this->noteText);
        DB::table('monitor_error_groups')->where('id', $group->id)->update([
            'notes' => trim(($group->notes ?? '') . "\n" . $line),
            'updated_at' => now(),
        ]);
        $this->noteText = '';
        $this->flash = 'Note added.';
    }

    public function scanNow(): void
    {
        $n = LogScanner::scanAll();
        $this->flash = $n ? "{$n} new log entr" . ($n === 1 ? 'y' : 'ies') . ' recorded.' : 'No new log entries.';
    }

    /** The ticked errors (or, if none are ticked, the open ones) as one Markdown file for an AI. */
    public function exportSelected()
    {
        $ids = array_map('intval', $this->selected);
        $query = DB::table('monitor_error_groups');
        $ids ? $query->whereIn('id', $ids) : $query->whereIn('status', ['new', 'investigating']);
        $groups = $query->orderByDesc('last_seen_at')->limit(30)->get();

        $md = "# jbspraxis.com: " . $groups->count() . " error type(s) to fix\n\n"
            . "Exported " . now('Africa/Lagos')->format('Y-m-d H:i') . " (Lagos time) from HQ → System health.\n"
            . "Work through them one by one. For each, find the root cause and propose the smallest safe fix.\n\n";
        foreach ($groups as $i => $g) {
            $md .= "\n---\n\n## " . ($i + 1) . ". " . mb_substr(trim($g->message), 0, 120) . "\n\n" . preg_replace('/^# .*\n/', '', ErrorMonitor::brief($g));
        }

        $this->selected = [];
        return response()->streamDownload(fn () => print($md), 'jbspraxis-errors-' . now()->format('Y-m-d-His') . '.md', ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    /** Field names in validation messages: the labels people see, not the property names (docs/BRAND.md sections 5 and 12) */
    protected function validationAttributes(): array
    {
        return [
            'noteText' => 'note',
        ];
    }

    /** The Server backups table (docs/BACKUPS.md); null when it can't be read, so the page still opens */
    private function backups(): ?array
    {
        try {
            return ['setUp' => app(Backups::class)->setUp(), 'sites' => Status::sites()];
        } catch (\Throwable) {
            return null;
        }
    }

    public function render()
    {
        $checks = HealthChecks::all();

        $query = DB::table('monitor_error_groups');
        match ($this->statusFilter) {
            'open' => $query->whereIn('status', ['new', 'investigating']),
            'fixed' => $query->where('status', 'fixed'),
            'ignored' => $query->where('status', 'ignored'),
            default => null,
        };
        if (trim($this->search) !== '') {
            $term = '%' . trim($this->search) . '%';
            $query->where(fn ($q) => $q->where('message', 'like', $term)->orWhere('file', 'like', $term)->orWhere('exception_class', 'like', $term));
        }
        $groups = $query->orderByDesc('last_seen_at')->limit(100)->get();

        $counts = DB::table('monitor_error_groups')->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        $open = null;
        if ($this->openGroupId) {
            $group = DB::table('monitor_error_groups')->where('id', $this->openGroupId)->first();
            if ($group) {
                $events = DB::table('monitor_error_events')->where('group_id', $group->id)->orderByDesc('id')->limit(10)->get();
                $open = [
                    'group' => $group,
                    'events' => $events,
                    'hint' => ErrorMonitor::explain($group),
                    'code' => ErrorMonitor::codeExcerpt($group->file, $group->line),
                    'brief' => ErrorMonitor::brief($group, $events->take(5)),
                ];
            }
        }

        return view('livewire.hq.system.system-health', [
            'checks' => $checks,
            'overall' => HealthChecks::worst($checks),
            'groups' => $groups,
            'counts' => $counts,
            'open' => $open,
            'heartbeat' => ErrorMonitor::state('heartbeat'),
            'backups' => $this->tab === 'health' ? $this->backups() : null,
            'alertEmail' => config('services.monitor.alert_email'),
        ]);
    }
}
