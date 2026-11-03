<?php

namespace App\Livewire\Hq\Campaigns;

use App\Models\Campaigns\Broadcast;
use App\Support\Campaigns\Broadcasts;
use App\Support\Campaigns\Layouts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * HQ › Campaigns › Broadcasts (docs/CAMPAIGNS.md): every broadcast with its numbers, newest first, and New broadcast
 * (pick a layout, then write it). Several broadcasts may be sending at once: they queue behind each other at the
 * server's pace (Broadcasts::PER_RUN), so there is no lock any more.
 */
#[Layout('components.layouts.hq')]
class BroadcastList extends Component
{
    use WithPagination;

    /** all, drafts, scheduled, sending, sent */
    #[Url(as: 'show', except: 'all')]
    public string $show = 'all';

    public bool $choosing = false;

    #[Locked]
    public bool $canCreate = false;

    private const FILTERS = ['all' => null, 'drafts' => ['Draft'], 'scheduled' => ['Scheduled'], 'sending' => ['Sending'], 'sent' => ['Completed', 'Cancelled']];

    public function mount(): void
    {
        $this->canCreate = Auth::user()->can('broadcasts.create');
        if (! array_key_exists($this->show, self::FILTERS)) {
            $this->show = 'all';
        }
    }

    public function updatedShow(): void
    {
        $this->resetPage();
    }

    public function create(string $layout)
    {
        abort_unless($this->canCreate, 403);
        $broadcast = Broadcasts::create(Auth::user(), $layout);

        return $this->redirectRoute('hq.campaigns.broadcast', ['broadcast' => $broadcast->id], navigate: true);
    }

    public function deleteDraft(string $id): void
    {
        abort_unless($this->canCreate, 403);
        $deleted = Broadcast::whereKey($id)->where('status', 'Draft')->delete();
        session()->flash($deleted ? 'success' : 'error', $deleted ? 'Draft deleted.' : 'Only drafts can be deleted: this broadcast has been sent or scheduled.');
    }

    public function render()
    {
        $statuses = self::FILTERS[$this->show] ?? null;
        $broadcasts = Broadcast::query()->with('segment')
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->orderByRaw("FIELD(status, 'Draft', 'Scheduled', 'Sending') DESC")
            ->orderByDesc('updated_at')
            ->paginate(25);

        $ids = $broadcasts->getCollection()->pluck('id');
        $stats = $ids->isEmpty() ? collect() : DB::table('broadcast_logs')->whereIn('campaign_id', $ids)->groupBy('campaign_id')->selectRaw("
            campaign_id, SUM(status = 'Sent') AS sent, SUM(status IN ('Queued', 'Sending')) AS queued,
            SUM(status = 'Sent' AND (open_count > 0 OR click_count > 0)) AS opened, SUM(click_count > 0) AS clicked,
            SUM(unsubscribed_at IS NOT NULL) AS unsubscribed, SUM(status IN ('Failed', 'Bounced', 'Skipped')) AS problems")->get()->keyBy('campaign_id');

        $queued = DB::table('broadcast_logs')->whereIn('status', ['Queued', 'Sending'])->count();

        return view('livewire.hq.campaigns.broadcast-list', [
            'broadcasts' => $broadcasts,
            'stats' => $stats,
            'queued' => $queued,
            'lastAt' => $queued ? Broadcasts::eta() : null,
            'layouts' => Layouts::ALL,
            'counts' => Broadcast::selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status'),
        ])->title('Broadcasts');
    }
}
