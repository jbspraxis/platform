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

    /**
     * The worst check, for the pill at the top. Worked out whenever the checks run (opening the page or the Health
     * tab, and after a change to the errors), so ticking, searching and opening errors don't run all twelve again.
     */
    #[Locked]
    public string $overall = 'unknown';

    /** This request's checks and backups table, once run (not kept between requests) */
    private ?array $checks = null;

    private ?array $backupTable = null;

    public function mount(): void
    {
        $this->runChecks();
    }

    /**
     * Every check, once per request. On the Health tab the Server backups table and the backups check read the same
     * rows, so they are read once for both.
     */
    private function runChecks(): array
    {
        if ($this->checks === null) {
            $this->backupTable = $this->tab === 'health' ? $this->backups() : null;
            $this->checks = HealthChecks::all($this->backupTable['sites'] ?? null);
            $this->overall = HealthChecks::worst($this->checks);
        }

        return $this->checks;
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['health', 'errors'], true) ? $tab : 'health';
        $this->openGroupId = null;
        if ($this->tab === 'health') {
            $this->runChecks();
        }
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
        $this->runChecks();
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
        $this->runChecks();
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
        $this->runChecks();
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
        $health = $this->tab === 'health';
        $checks = $health ? $this->runChecks() : [];

        $groups = $health ? collect() : $this->groups();
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
            'overall' => $this->overall,
            'groups' => $groups,
            'counts' => $counts,
            'open' => $open,
            'heartbeat' => $health ? ErrorMonitor::state('heartbeat') : null,
            'backups' => $health ? $this->backupTable : null,
            'alertEmail' => config('services.monitor.alert_email'),
        ]);
    }

    /** The Errors tab's list: the filter and the search, latest first */
    private function groups()
    {
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
        return $query->orderByDesc('last_seen_at')->limit(100)->get();
    }
}
