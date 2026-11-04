<?php

namespace App\Livewire\Hq\Campaigns\Outreach;

use App\Models\Campaigns\OutreachCampaign;
use App\Models\User;
use App\Support\Campaigns\Outreach\Limits;
use App\Support\Campaigns\Outreach\Outreach;
use App\Support\Campaigns\Outreach\Senders;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * HQ › Campaigns › Outreach (docs/CAMPAIGNS.md section 10): every outreach campaign with its numbers, the signed-in
 * person's sending day (their mailbox, today's count and limit, a pause), and New campaign.
 */
#[Layout('components.layouts.hq')]
class OutreachList extends Component
{
    /** active (active and paused) or archived */
    #[Url(as: 'list', except: 'active')]
    public string $list = 'active';

    public bool $creating = false;

    public string $name = '';

    public string $brief = '';

    public ?string $senderId = null;

    #[Locked]
    public bool $canCreate = false;

    #[Locked]
    public bool $canApprove = false;

    public function mount(): void
    {
        $this->canCreate = Outreach::canCreate(Auth::user());
        $this->canApprove = Outreach::canApprove(Auth::user());
        if (! in_array($this->list, ['active', 'archived'], true)) {
            $this->list = 'active';
        }
    }

    public function startCreating(): void
    {
        abort_unless($this->canCreate, 403);
        $this->reset(['name', 'brief']);
        $this->senderId = Auth::id();
        $this->resetErrorBag();
        $this->creating = true;
    }

    public function create()
    {
        abort_unless($this->canCreate, 403);
        $senders = $this->canApprove ? array_keys(self::senderChoices()) : [Auth::id()];
        $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'brief' => ['nullable', 'string', 'max:2000'],
            'senderId' => ['required', 'in:' . implode(',', $senders)],
        ], [
            'name.required' => 'Give the campaign a name, such as “Lagos logistics, November”.',
            'name.min' => 'Give the campaign a name of 3 characters or more.',
            'name.max' => 'Keep the name under 150 characters.',
            'brief.max' => 'Keep this under 2,000 characters.',
            'senderId.*' => 'Choose who it is sent from.',
        ]);
        $campaign = Outreach::create(Auth::user(), $this->name, $this->brief, $this->senderId);

        return $this->redirectRoute('hq.campaigns.outreach.campaign', ['campaign' => $campaign->id], navigate: true);
    }

    /** Staff an approver can send a campaign for: active people with Outreach (create), by first name */
    public static function senderChoices(): array
    {
        return User::query()->where('status', 'Active')->whereNull('deleted_at')->orderBy('first_name')->orderBy('last_name')->get()
            ->filter(fn (User $u) => Outreach::canCreate($u))
            ->mapWithKeys(fn (User $u) => [$u->id => trim($u->first_name . ' ' . $u->last_name) ?: $u->email])->all();
    }

    public function render()
    {
        $campaigns = OutreachCampaign::query()->with('sender')
            ->when($this->list === 'archived', fn ($q) => $q->where('status', 'archived'), fn ($q) => $q->where('status', '!=', 'archived'))
            ->orderByDesc('created_at')->get();
        $archived = OutreachCampaign::where('status', 'archived')->count();

        return view('livewire.hq.campaigns.outreach.outreach-list', [
            'campaigns' => $campaigns,
            'stats' => Outreach::stats($campaigns->pluck('id')->all()),
            'archived' => $archived,
            'me' => $this->canCreate ? Senders::summary(Auth::user()) : null,
            'senders' => $this->creating && $this->canApprove ? self::senderChoices() : [],
            'window' => Limits::windowText(),
            'gap' => (int) Limits::get('gap_minutes'),
            'followUpDays' => (int) Limits::get('follow_up_days'),
        ])->title('Outreach');
    }
}
