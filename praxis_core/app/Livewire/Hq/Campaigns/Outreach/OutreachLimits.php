<?php

namespace App\Livewire\Hq\Campaigns\Outreach;

use App\Models\Campaigns\OutreachCampaign;
use App\Models\User;
use App\Support\Campaigns\Outreach\Limits;
use App\Support\Campaigns\Outreach\Outreach;
use App\Support\Campaigns\Outreach\Senders;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * HQ › Campaigns › Outreach › Sending limits (owner's decision 2, 3 Nov 2026; outreach.approve, the owner): the daily
 * limits, the window, the gap between emails, the follow-up's delay and the bounce rate that pauses a sender; and
 * every sender's day, with Resume for one that paused.
 */
#[Layout('components.layouts.hq')]
class OutreachLimits extends Component
{
    /** The form, as Limits::DEFAULTS */
    public array $values = [];

    public function mount(): void
    {
        abort_unless(Outreach::canApprove(Auth::user()), 403);
        $this->values = Limits::all();
    }

    public function save(): void
    {
        abort_unless(Outreach::canApprove(Auth::user()), 403);
        $before = Limits::all();
        try {
            $changed = Limits::set($this->values, Auth::user());
        } catch (ValidationException $e) {
            $this->setErrorBag(collect($e->errors())->mapWithKeys(fn ($m, $k) => ['values.' . $k => $m])->all());

            return;
        }
        $this->resetErrorBag();
        $this->values = Limits::all();
        if ($changed) {
            Outreach::log('OUTREACH_LIMITS_CHANGED', 'Outreach sending limits changed: ' . implode(', ', $changed) . '.', 'outreach_settings', 'limits',
                array_intersect_key($before, array_flip($changed)), array_intersect_key($this->values, array_flip($changed)));
        }
        session()->flash('success', $changed ? 'Saved. The sender follows the new limits from its next run (within a minute).' : 'Nothing changed.');
    }

    public function defaults(): void
    {
        abort_unless(Outreach::canApprove(Auth::user()), 403);
        $this->values = Limits::DEFAULTS;
        $this->resetErrorBag();
    }

    public function resumeSender(string $userId): void
    {
        abort_unless(Outreach::canApprove(Auth::user()), 403);
        if (Senders::resume($userId, Auth::user())) {
            Outreach::log('OUTREACH_SENDER_RESUMED', 'Outreach resumed for ' . Senders::firstName(User::find($userId)) . '.', 'users', $userId);
            session()->flash('success', 'Resumed: ' . Senders::firstName(User::find($userId)) . '’s approved emails go out again in their turn.');
        }
    }

    public function render()
    {
        $ids = OutreachCampaign::query()->distinct()->pluck('sender_id')
            ->merge(DB::table('outreach_senders')->pluck('user_id'))->unique()->values();
        $senders = User::whereIn('id', $ids)->orderBy('first_name')->get()->map(fn (User $u) => ['user' => $u] + Senders::summary($u)
            + ['first' => Senders::firstSentAt($u->id), 'waiting' => DB::table('outreach_emails AS e')->join('outreach_campaigns AS c', 'c.id', '=', 'e.campaign_id')
                ->where('c.sender_id', $u->id)->where(fn ($q) => $q->where('e.status', 'approved')->orWhere('e.status', 'scheduled'))->count()]);

        return view('livewire.hq.campaigns.outreach.outreach-limits', [
            'labels' => Limits::LABELS,
            'senders' => $senders,
        ])->title('Sending limits · Outreach');
    }
}
