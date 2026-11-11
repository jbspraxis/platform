<?php

namespace App\Livewire\Hq\Campaigns\Outreach;

use App\Models\Campaigns\OutreachCampaign;
use App\Models\Campaigns\OutreachEmail;
use App\Models\Campaigns\OutreachProspect;
use App\Models\Campaigns\OutreachReply;
use App\Models\Organization;
use App\Models\User;
use App\Support\Campaigns\Marketing;
use App\Support\Campaigns\Outreach\Limits;
use App\Support\Campaigns\Outreach\Outreach;
use App\Support\Campaigns\Outreach\ProspectLead;
use App\Support\Campaigns\Outreach\Reminders;
use App\Support\Campaigns\Outreach\ReplyText;
use App\Support\Campaigns\Outreach\Sorting;
use App\Support\Leads\LeadRuleBroken;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Replies to outreach emails (C3, docs/CAMPAIGNS.md section 12; owner's decisions of 3 Nov 2026): Claude's suggested
 * label on each reply, confirmed or changed by a person in one click; Make a lead; Not now with a reminder day; the
 * bounce notices and spam reports HQ filed by itself. Two places:
 *
 *   /campaigns/outreach/replies                    every campaign this person sorts (their own; all with outreach.approve)
 *   /campaigns/outreach/{campaign}?show=replies    one campaign, inside its page
 *
 * Permissions as C2's review: the campaign's sender or owner (outreach.create), or outreach.approve. A reply's text is
 * read through HQ Mail (ReplyText), so it shows only to someone who may read the mailbox it is in.
 */
#[Layout('components.layouts.hq')]
class OutreachReplies extends Component
{
    /** One campaign (inside its page), or null: every campaign this person sorts */
    #[Locked]
    public ?string $campaignId = null;

    /** sort, interested, not_now, not_interested, unsubscribe, notices, all ('' = to sort when there are some, else all) */
    #[Url(as: 'label', except: '')]
    public string $filter = '';

    /** The reply open in the dialog */
    #[Url(as: 'reply', except: null)]
    public ?int $openId = null;

    /** Not now: the reminder day (Y-m-d, Lagos) */
    public string $remindOn = '';

    public bool $confirmingSpam = false;

    public int $limit = 50;

    /** The replies shown as last drawn (stamp()): the 4 s refresh while Claude sorts redraws only when they moved */
    #[Locked]
    public string $drawnAt = '';

    public const FILTERS = [
        'sort' => 'To sort', 'interested' => 'Interested', 'not_now' => 'Not now', 'not_interested' => 'Not interested',
        'unsubscribe' => 'Unsubscribed', 'notices' => 'Bounced and spam', 'all' => 'All replies',
    ];

    public function mount(?string $campaignId = null): void
    {
        $user = Auth::user();
        if ($campaignId !== null) {
            $campaign = OutreachCampaign::find($campaignId);
            abort_unless($campaign && Outreach::canReview($user, $campaign), 403);
            $this->campaignId = $campaign->id;
        } else {
            abort_unless(Outreach::canCreate($user) || Outreach::canApprove($user), 403);
        }
        if ($this->filter !== '' && ! isset(self::FILTERS[$this->filter])) {
            $this->filter = '';
        }
        if ($this->openId) {
            $this->openReply($this->openId);
        }
    }

    /** The campaigns shown: the one, or every one this person sorts (checked again on every request) */
    private function campaignIds(): array
    {
        if ($this->campaignId !== null) {
            $campaign = OutreachCampaign::find($this->campaignId);

            return $campaign && Outreach::canReview(Auth::user(), $campaign) ? [$campaign->id] : [];
        }

        return Outreach::reviewableIds(Auth::user());
    }

    /** The reply open in the dialog, if it belongs here */
    private function current(): ?OutreachReply
    {
        return $this->openId ? OutreachReply::whereIn('campaign_id', $this->campaignIds())->find($this->openId) : null;
    }

    /** The open reply, for an action that changes it: 403 when this person may not sort its campaign */
    private function mustSort(): OutreachReply
    {
        $reply = $this->current();
        abort_unless($reply, 403);

        return $reply;
    }

    public function updatedFilter(): void
    {
        $this->filter = isset(self::FILTERS[$this->filter]) ? $this->filter : '';
        $this->limit = 50;
    }

    public function showMore(): void
    {
        $this->limit += 100;
    }

    // ------------------------------------------------------------------ the dialog

    public function openReply(int $id): void
    {
        $reply = OutreachReply::whereIn('campaign_id', $this->campaignIds())->find($id);
        if (! $reply) {
            $this->openId = null;

            return;
        }
        $this->openId = $reply->id;
        $prospect = OutreachProspect::find($reply->prospect_id);
        $this->remindOn = $prospect?->reply_label === 'not_now' && $prospect->remind_on ? $prospect->remind_on->format('Y-m-d') : Sorting::defaultRemindOn($reply);
        $this->confirmingSpam = false;
        $this->resetErrorBag();
    }

    public function closeReply(): void
    {
        $this->reset(['openId', 'remindOn', 'confirmingSpam']);
        $this->resetErrorBag();
    }

    /** Confirm Claude's label, or choose another: one click */
    public function choose(string $label): void
    {
        $reply = $this->mustSort();
        $this->resetErrorBag();
        if (! isset(OutreachReply::LABELS[$label]) || ! $reply->isReply()) {
            return;
        }
        if ($label === 'not_now' && ! Sorting::validDay($this->remindOn)) {
            $this->addError('remindOn', 'Choose a day after today, within two years.');

            return;
        }
        $first = OutreachProspect::whereKey($reply->prospect_id)->value('first_name') ?? 'them';
        if (! Sorting::label($reply, $label, Auth::user(), $label === 'not_now' ? $this->remindOn : null)) {
            $this->addError('dialog', 'That didn’t save. Close this and try again.');

            return;
        }
        session()->flash('success', match ($label) {
            'interested' => 'Sorted as Interested. Make a lead when you are ready to work it.',
            'not_now' => 'Sorted as Not now. You’ll get a bell on ' . self::day($this->remindOn) . ' to get back to ' . $first . '.',
            'not_interested' => 'Sorted as Not interested. Nothing more goes to ' . $first . '.',
            'unsubscribe' => 'Unsubscribed: ' . $first . '’s address is on the do-not-email list, and nothing more goes to them.',
        });
    }

    /** A new reminder day for a Not now */
    public function saveReminder(): void
    {
        $reply = $this->mustSort();
        $this->resetErrorBag();
        $prospect = OutreachProspect::find($reply->prospect_id);
        if (! $prospect || ! Sorting::remind($prospect, $this->remindOn, Auth::user())) {
            $this->addError('remindOn', 'Choose a day after today, within two years.');

            return;
        }
        session()->flash('success', 'Reminder moved to ' . self::day($this->remindOn) . '.');
    }

    public function makeLead(): void
    {
        $reply = $this->mustSort();
        $prospect = OutreachProspect::find($reply->prospect_id);
        if (! $prospect || ! $reply->isReply()) {
            return;
        }
        $this->resetErrorBag();
        try {
            $made = ProspectLead::make($prospect, Auth::user());
        } catch (ValidationException $e) {
            $this->addError('dialog', 'HQ couldn’t make the lead: ' . collect($e->errors())->flatten()->first());

            return;
        } catch (LeadRuleBroken $e) {
            $this->addError('dialog', 'HQ couldn’t make the lead: ' . $e->getMessage());

            return;
        }
        // Making a lead is a person saying they will work it: a reply nobody had sorted yet is Interested
        if ($reply->fresh()?->label === null) {
            Sorting::label($reply->fresh(), 'interested', Auth::user());
        }
        session()->flash('success', $made['message']);
    }

    /** Claude couldn't sort it: ask again */
    public function askAgain(): void
    {
        $reply = $this->mustSort();
        if (! Sorting::again($reply)) {
            $this->addError('dialog', 'Claude is already sorting this reply.');
        }
    }

    public function askSpam(): void
    {
        $this->mustSort();
        $this->confirmingSpam = true;
    }

    public function cancelSpam(): void
    {
        $this->confirmingSpam = false;
    }

    /** "They call it spam": a person's choice, never Claude's */
    public function markSpam(): void
    {
        $reply = $this->mustSort();
        $this->confirmingSpam = false;
        if (Sorting::complain($reply, Auth::user())) {
            session()->flash('success', 'Recorded as a spam complaint: their address is on the do-not-email list, and outreach from that mailbox is paused until the owner resumes it.');
        }
    }

    // ------------------------------------------------------------------ the page

    /** wire:poll while Claude sorts: one small count, and a redraw only when a reply changed */
    public function tick(): void
    {
        if ($this->stamp($this->campaignIds()) === $this->drawnAt) {
            $this->skipRender();
        }
    }

    /** Where the replies of these campaigns stand: how many, how many Claude is sorting, the latest change */
    private function stamp(array $campaignIds): string
    {
        $row = $campaignIds ? DB::table('outreach_replies')->whereIn('campaign_id', $campaignIds)
            ->selectRaw('COUNT(*) AS n, SUM(ai_run_id IS NOT NULL) AS busy, MAX(updated_at) AS changed')->first() : null;

        return md5(implode('|', [implode(',', $campaignIds), ...array_values((array) $row)]));
    }

    /** Replies (or notices) for the filter, newest first */
    private function rows(array $campaignIds, string $filter): Builder
    {
        $q = OutreachReply::query()->whereIn('campaign_id', $campaignIds)->with(['prospect', 'campaign:id,name']);
        match ($filter) {
            'sort' => $q->where('kind', 'reply')->whereNull('label'),
            'notices' => $q->whereIn('label', array_keys(OutreachReply::NOTICES)),
            'all' => $q->where('kind', 'reply'),
            default => $q->where('kind', 'reply')->where('label', $filter),
        };

        return $q->orderByDesc('received_at')->orderByDesc('id');
    }

    /** filter => how many */
    private function counts(array $campaignIds): array
    {
        $row = $campaignIds ? DB::table('outreach_replies')->whereIn('campaign_id', $campaignIds)->selectRaw("
            SUM(kind = 'reply' AND label IS NULL) AS sort, SUM(kind = 'reply' AND label = 'interested') AS interested,
            SUM(kind = 'reply' AND label = 'not_now') AS not_now, SUM(kind = 'reply' AND label = 'not_interested') AS not_interested,
            SUM(kind = 'reply' AND label = 'unsubscribe') AS unsubscribe, SUM(label IN ('bounced', 'complained')) AS notices,
            SUM(kind = 'reply') AS `all`")->first() : null;

        return collect(self::FILTERS)->map(fn ($label, $key) => (int) ($row->{$key} ?? 0))->all();
    }

    /** "Thu 4 Feb 2027" for a Y-m-d day */
    private static function day(?string $ymd): string
    {
        return $ymd ? \Illuminate\Support\Carbon::createFromFormat('!Y-m-d', $ymd, Limits::TIMEZONE)->format('D j M Y') : '';
    }

    public function render()
    {
        $user = Auth::user();
        $ids = $this->campaignIds();
        // Taken before the page is read, so a change made while it is drawn shows on the next tick
        $this->drawnAt = $this->stamp($ids);
        $counts = $this->counts($ids);
        $filter = $this->filter !== '' ? $this->filter : ($counts['sort'] > 0 ? 'sort' : 'all');
        $total = (clone $this->rows($ids, $filter))->count();
        $rows = $this->rows($ids, $filter)->limit($this->limit)->get();

        $open = $this->current();
        if ($this->openId && ! $open) {
            $this->closeReply();
        }
        $openProspect = $open ? OutreachProspect::find($open->prospect_id) : null;
        $copy = $open ? ReplyText::copyFor($user, $open) : null;
        $sent = $open ? OutreachEmail::where('prospect_id', $open->prospect_id)->whereIn('status', ['sent', 'bounced'])->whereNotNull('sent_at')->orderBy('sent_at')->get() : collect();

        $view = view('livewire.hq.campaigns.outreach.outreach-replies', [
            'filter' => $filter,
            'counts' => $counts,
            'rows' => $rows,
            'more' => max($total - $this->limit, 0),
            'stats' => $this->campaignId === null ? self::sum(Outreach::stats($ids)) : null,
            'reminders' => $this->campaignId === null ? Reminders::waiting($ids) : 0,
            'campaigns' => $this->campaignId === null ? count($ids) : 1,
            'open' => $open,
            'openProspect' => $openProspect,
            'openCampaign' => $open ? OutreachCampaign::find($open->campaign_id) : null,
            'openSender' => $open ? User::find(OutreachCampaign::whereKey($open->campaign_id)->value('sender_id')) : null,
            'copy' => $copy,
            'body' => $copy ? ReplyText::present($user, $copy) : null,
            'sent' => $sent,
            'lead' => $openProspect?->organization_id ? Organization::find($openProspect->organization_id) : null,
            'existing' => $openProspect && ! $openProspect->organization_id && $open?->isReply() ? ProspectLead::existing($openProspect) : null,
            'suppressed' => $openProspect ? Marketing::entry($openProspect->email) : null,
            'busy' => $rows->contains(fn ($r) => $r->ai_run_id !== null) || ($open && Sorting::busy($open)),
        ]);

        return $this->campaignId === null ? $view->title('Replies · Outreach') : $view;
    }

    /** The numbers of several campaigns, added up (the rate worked out again) */
    private static function sum(array $stats): array
    {
        $out = ['sent' => 0, 'replies' => 0, 'to_sort' => 0, 'interested' => 0, 'not_now' => 0, 'not_interested' => 0, 'unsubscribe' => 0, 'leads' => 0];
        foreach ($stats as $s) {
            foreach ($out as $key => $value) {
                $out[$key] = $value + $s[$key];
            }
        }
        $out['reply_rate'] = $out['sent'] > 0 ? (int) min(100, round(100 * $out['replies'] / $out['sent'])) : 0;

        return $out;
    }
}
