<?php

namespace App\Livewire\Hq\Campaigns;

use App\Models\Campaigns\Broadcast;
use App\Support\Campaigns\Broadcasts;
use App\Support\Campaigns\Renderer;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * One broadcast's report (docs/CAMPAIGNS.md 4.7): sent, failed, opens (approximate), clicks per link, unsubscribes,
 * and every recipient with what they did. Refreshes itself while the broadcast is still sending.
 */
#[Layout('components.layouts.hq')]
class BroadcastReport extends Component
{
    use WithPagination;

    #[Locked]
    public string $broadcastId;

    /** all, sent, opened, clicked, unsubscribed, problems, queued */
    #[Url(as: 'who', except: 'all')]
    public string $who = 'all';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public bool $showEmail = false;

    public string $emailLocale = 'en';

    public function mount(string $broadcast): void
    {
        $model = Broadcast::findOrFail($broadcast);
        $this->broadcastId = $model->id;
        if (in_array($model->status, ['Draft', 'Scheduled'], true) && ! $model->isLegacy()) {
            $this->redirectRoute('hq.campaigns.broadcast', ['broadcast' => $model->id], navigate: true);

            return;
        }
        $this->broadcastId = $model->id;
        $this->emailLocale = $model->locales()[0];
    }

    public function updatingWho(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $broadcast = Broadcast::with('segment', 'author')->findOrFail($this->broadcastId);
        $search = trim($this->search);
        $recipients = DB::table('broadcast_logs as l')
            ->leftJoin('client_users as cu', 'cu.id', '=', 'l.user_id')
            ->leftJoin('organizations as o', 'o.id', '=', 'cu.organization_id')
            ->where('l.campaign_id', $broadcast->id)
            ->when($this->who !== 'all', fn ($q) => match ($this->who) {
                'sent' => $q->where('l.status', 'Sent'),
                'opened' => $q->where('l.status', 'Sent')->where(fn ($w) => $w->where('l.open_count', '>', 0)->orWhere('l.click_count', '>', 0)),
                'clicked' => $q->where('l.click_count', '>', 0),
                'unsubscribed' => $q->whereNotNull('l.unsubscribed_at'),
                'problems' => $q->whereIn('l.status', ['Failed', 'Bounced', 'Skipped']),
                'queued' => $q->whereIn('l.status', ['Queued', 'Sending']),
                default => $q,
            })
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('l.email', 'like', "%{$search}%")->orWhere('cu.email', 'like', "%{$search}%")
                ->orWhere('l.first_name', 'like', "%{$search}%")->orWhere('l.last_name', 'like', "%{$search}%")->orWhere('l.company', 'like', "%{$search}%")))
            ->orderBy('l.id')
            ->select('l.*', 'cu.email as contact_email', 'cu.first_name as contact_first', 'cu.last_name as contact_last', 'o.name as organisation')
            ->paginate(50);

        $links = DB::table('broadcast_links as k')->where('k.campaign_id', $broadcast->id)
            ->leftJoin('broadcast_clicks as c', 'c.link_id', '=', 'k.id')
            ->groupBy('k.id', 'k.url', 'k.locale', 'k.clicks')
            ->selectRaw('k.id, k.url, k.locale, k.clicks, COUNT(DISTINCT c.log_id) AS people')
            ->orderByDesc('k.clicks')->get();

        return view('livewire.hq.campaigns.broadcast-report', [
            'broadcast' => $broadcast,
            'stats' => Broadcasts::stats($broadcast),
            'recipients' => $recipients,
            'links' => $links,
            'emailHtml' => $this->showEmail && ! $broadcast->isLegacy() ? Renderer::preview($broadcast, $this->emailLocale) : null,
        ])->title($broadcast->title() . ' · Report');
    }
}
