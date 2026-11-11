<?php

namespace App\Livewire\Hq\Campaigns\Outreach;

use App\Models\Campaigns\OutreachCampaign;
use App\Models\Campaigns\OutreachEmail;
use App\Models\Campaigns\OutreachProspect;
use App\Models\User;
use App\Support\Campaigns\Outreach\Drafts;
use App\Support\Campaigns\Outreach\Limits;
use App\Support\Campaigns\Outreach\Outreach;
use App\Support\Campaigns\Outreach\ProspectImportSchema;
use App\Support\Campaigns\Outreach\Senders;
use App\Support\Campaigns\RichText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * One outreach campaign (docs/CAMPAIGNS.md section 10; owner's decision 3, 3 Nov 2026): its numbers, a clean table of
 * its prospects and their drafts, and Import prospects. A row opens a small dialog with the full email and its
 * follow-up, where the reviewer Approves, Edits (rich text), Rewrites (Claude again, with an optional note) or Skips;
 * after Approve or Skip the next draft opens. "Approve the rest" unlocks after 10 approved by hand.
 */
#[Layout('components.layouts.hq')]
class OutreachCampaignPage extends Component
{
    #[Locked]
    public string $campaignId;

    /** review, writing, approved, sent, stopped, all; replies (C3: OutreachReplies, for those who review the campaign) */
    #[Url(as: 'show', except: 'review')]
    public string $filter = 'review';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public int $limit = 50;

    /** The first email open in the dialog */
    public ?int $openId = null;

    /** The dialog's mode: read, edit or rewrite */
    public string $mode = 'read';

    public string $editSubject = '';

    /** The editors' HTML: first, follow_up (setText from the rich-text editor) */
    public array $editBody = ['first' => '', 'follow_up' => ''];

    public string $rewriteNote = '';

    public bool $confirmingRest = false;

    public bool $editingDetails = false;

    public string $detailName = '';

    public string $detailBrief = '';

    #[Locked]
    public bool $canReview = false;

    /** This campaign's emails and replies as last drawn (stamp()): the 4 s refresh redraws only when they moved */
    #[Locked]
    public string $drawnAt = '';

    public const FILTERS = [
        'review' => 'To review', 'writing' => 'Not ready', 'approved' => 'Approved', 'sent' => 'Sent', 'stopped' => 'Not sent', 'all' => 'All',
        'replies' => 'Replies',
    ];

    /** The filter, if this person may see it (Replies: only those who review the campaign), else To review */
    private function allowed(string $filter): string
    {
        return isset(self::FILTERS[$filter]) && ($filter !== 'replies' || $this->canReview) ? $filter : 'review';
    }

    public function mount(string $campaign): void
    {
        $model = OutreachCampaign::find($campaign);
        abort_unless($model && Outreach::canView(Auth::user()), 404);
        $this->campaignId = $model->id;
        $this->canReview = Outreach::canReview(Auth::user(), $model);
        if ($this->canReview) {
            // The import dialog's default campaign
            session([ProspectImportSchema::SESSION_KEY => $model->id]);
        }
        $this->filter = $this->allowed($this->filter);
    }

    private function campaign(): OutreachCampaign
    {
        return OutreachCampaign::findOrFail($this->campaignId);
    }

    private function mustReview(): void
    {
        abort_unless($this->canReview && Outreach::canReview(Auth::user(), $this->campaign()), 403);
    }

    /** The first email open in the dialog, of this campaign */
    private function current(): ?OutreachEmail
    {
        return $this->openId ? OutreachEmail::where('campaign_id', $this->campaignId)->where('kind', 'first')->find($this->openId) : null;
    }

    #[On('import-finished')]
    public function imported(): void
    {
        $this->filter = 'all';
    }

    public function updatedFilter(): void
    {
        $this->filter = $this->allowed($this->filter);
        $this->limit = 50;
    }

    public function updatedSearch(): void
    {
        $this->limit = 50;
    }

    public function showMore(): void
    {
        $this->limit += 100;
    }

    // ------------------------------------------------------------------ the dialog

    public function openEmail(int $id): void
    {
        $email = OutreachEmail::where('campaign_id', $this->campaignId)->where('kind', 'first')->find($id);
        if (! $email) {
            return;
        }
        $this->openId = $email->id;
        $this->mode = 'read';
        $this->rewriteNote = '';
        $this->resetErrorBag();
    }

    public function closeEmail(): void
    {
        $this->reset(['openId', 'mode', 'editSubject', 'editBody', 'rewriteNote']);
        $this->resetErrorBag();
    }

    public function approve(): void
    {
        $this->mustReview();
        $email = $this->current();
        if (! $email || Drafts::busy($email)) {
            return;
        }
        if (! Outreach::approve($email, Auth::user())) {
            $this->addError('dialog', 'This email can’t be approved as it is: it needs a subject and some words.');

            return;
        }
        $this->next($email, 'Approved. It goes out from ' . (Senders::mailbox($this->campaign()->sender_id)?->email ?? 'the sender’s mailbox') . ' in its turn.');
    }

    public function skip(): void
    {
        $this->mustReview();
        $email = $this->current();
        if ($email && Outreach::skip($email, Auth::user())) {
            $this->next($email, 'Skipped: nothing will be sent to ' . (OutreachProspect::whereKey($email->prospect_id)->value('first_name') ?? 'them') . '.');
        }
    }

    public function backToReview(): void
    {
        $this->mustReview();
        $email = $this->current();
        if ($email && Outreach::unapprove($email)) {
            session()->flash('success', 'Back to review.');
        }
    }

    /** After Approve or Skip: the next draft to review, else close */
    private function next(OutreachEmail $done, string $message): void
    {
        session()->flash('success', $message);
        $next = OutreachEmail::where('campaign_id', $this->campaignId)->where('kind', 'first')->where('status', 'draft')
            ->whereNull('ai_run_id')->where('id', '!=', $done->id)->orderByRaw('id < ? ASC', [$done->id])->orderBy('id')->first();
        $next ? $this->openEmail($next->id) : $this->closeEmail();
    }

    public function startEdit(): void
    {
        $this->mustReview();
        $email = $this->current();
        if (! $email || ! in_array($email->status, ['draft', 'not_written'], true) || Drafts::busy($email)) {
            return;
        }
        $this->editSubject = (string) $email->subject;
        $this->editBody = [
            'first' => (string) $email->body_html,
            'follow_up' => (string) OutreachEmail::where('prospect_id', $email->prospect_id)->where('kind', 'follow_up')->value('body_html'),
        ];
        $this->mode = 'edit';
        $this->resetErrorBag();
    }

    /** The rich-text editor's words (resources/campaigns/editor.js calls it on every pause and on blur) */
    public function setText(string $part, string $id, string $html): void
    {
        if ($this->mode === 'edit' && array_key_exists($part, $this->editBody) && (int) $id === $this->openId) {
            $this->editBody[$part] = mb_substr($html, 0, 20000);
        }
    }

    public function saveEdit(): void
    {
        $this->mustReview();
        $email = $this->current();
        if (! $email) {
            return;
        }
        $this->resetErrorBag();
        if (trim($this->editSubject) === '') {
            $this->addError('editSubject', 'Write a subject.');
        } elseif (mb_strlen(trim($this->editSubject)) > 200) {
            $this->addError('editSubject', 'Keep the subject under 200 characters.');
        }
        if (RichText::isBlank(RichText::clean($this->editBody['first']))) {
            $this->addError('editBody.first', 'Write the email.');
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }
        if (! Outreach::saveEdit($email, $this->editSubject, $this->editBody['first'], $this->editBody['follow_up'])) {
            $this->addError('dialog', 'This email has moved on (approved or sent) since you opened it.');

            return;
        }
        $this->mode = 'read';
        session()->flash('success', 'Saved. Read it once more, then approve it.');
    }

    public function cancelMode(): void
    {
        $this->mode = 'read';
        $this->rewriteNote = '';
        $this->resetErrorBag();
    }

    public function startRewrite(): void
    {
        $this->mustReview();
        $this->mode = 'rewrite';
        $this->rewriteNote = '';
        $this->resetErrorBag();
    }

    /** Rewrite (a draft) or Try again (one Claude couldn't write): Claude again, with the note */
    public function rewrite(): void
    {
        $this->mustReview();
        $email = $this->current();
        if (! $email) {
            return;
        }
        if (mb_strlen($this->rewriteNote) > 500) {
            $this->addError('rewriteNote', 'Keep the note under 500 characters.');

            return;
        }
        if (! Drafts::again($email, $this->rewriteNote)) {
            $email->refresh();
            $this->addError('dialog', $email->ai_error ? __($email->ai_error) : 'Claude is already writing this one.');
            $this->mode = 'read';

            return;
        }
        $this->mode = 'read';
        $this->rewriteNote = '';
    }

    // ------------------------------------------------------------------ the campaign

    public function askApproveRest(): void
    {
        $this->mustReview();
        $this->confirmingRest = Outreach::handApproved($this->campaign()) >= Outreach::HAND_APPROVALS;
    }

    public function approveRest(): void
    {
        $this->mustReview();
        $this->confirmingRest = false;
        $n = Outreach::approveRest($this->campaign(), Auth::user());
        session()->flash($n ? 'success' : 'error', $n
            ? number_format($n) . ' ' . ($n === 1 ? 'email' : 'emails') . ' approved. They go out in turn, within the day’s limit.'
            : 'Approve ' . Outreach::HAND_APPROVALS . ' emails one by one first.');
    }

    public function pause(): void
    {
        $this->mustReview();
        Outreach::setStatus($this->campaign(), 'paused', Auth::user());
        session()->flash('success', 'Paused: nothing more goes out from this campaign until you resume it.');
    }

    public function resume(): void
    {
        $this->mustReview();
        Outreach::setStatus($this->campaign(), 'active', Auth::user());
        session()->flash('success', 'Resumed: approved emails go out again in their turn.');
    }

    public function archive(): void
    {
        $this->mustReview();
        Outreach::setStatus($this->campaign(), 'archived', Auth::user());
        $this->editingDetails = false;
        session()->flash('success', 'Archived: nothing more goes out from it. Its numbers stay here.');
    }

    public function startDetails(): void
    {
        $this->mustReview();
        $c = $this->campaign();
        $this->detailName = $c->name;
        $this->detailBrief = (string) $c->brief;
        $this->resetErrorBag();
        $this->editingDetails = true;
    }

    public function saveDetails(): void
    {
        $this->mustReview();
        $this->validate([
            'detailName' => ['required', 'string', 'min:3', 'max:150'],
            'detailBrief' => ['nullable', 'string', 'max:2000'],
        ], [
            'detailName.required' => 'Give the campaign a name.', 'detailName.min' => 'Give the campaign a name of 3 characters or more.',
            'detailName.max' => 'Keep the name under 150 characters.', 'detailBrief.max' => 'Keep this under 2,000 characters.',
        ]);
        $this->campaign()->forceFill(['name' => trim($this->detailName), 'brief' => trim($this->detailBrief) !== '' ? trim($this->detailBrief) : null])->save();
        $this->editingDetails = false;
        session()->flash('success', 'Saved. Drafts Claude writes from now on read the new note; Rewrite an existing draft to use it.');
    }

    // ------------------------------------------------------------------ the page

    /** wire:poll while Claude writes: two small counts, and a redraw only when an email or a reply changed */
    public function tick(): void
    {
        if ($this->stamp() === $this->drawnAt) {
            $this->skipRender();
        }
    }

    /** Where this campaign's emails and replies stand: how many, how many are being written, the latest change */
    private function stamp(): string
    {
        $emails = DB::table('outreach_emails')->where('campaign_id', $this->campaignId)
            ->selectRaw("COUNT(*) AS n, SUM(status = 'writing') AS writing, SUM(ai_run_id IS NOT NULL) AS busy, MAX(updated_at) AS changed")->first();
        $replies = DB::table('outreach_replies')->where('campaign_id', $this->campaignId)->selectRaw('COUNT(*) AS n, MAX(updated_at) AS changed')->first();

        return md5(implode('|', [...array_values((array) $emails), ...array_values((array) $replies)]));
    }

    /** The first emails the table shows, with their prospect and follow-up */
    private function rows(): Builder
    {
        $q = OutreachEmail::query()->where('campaign_id', $this->campaignId)->where('kind', 'first')->with('prospect');
        match ($this->filter) {
            'review' => $q->where('status', 'draft'),
            'writing' => $q->whereIn('status', ['writing', 'not_written']),
            'approved' => $q->whereIn('status', ['approved', 'sending']),
            'sent' => $q->where('status', 'sent'),
            'stopped' => $q->whereIn('status', ['skipped', 'bounced', 'failed']),
            default => null,
        };
        if (($term = trim($this->search)) !== '') {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $q->whereHas('prospect', fn ($p) => $p->where(fn ($p) => $p->where('company', 'like', $like)->orWhere('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)->orWhere('email', 'like', $like)));
        }

        return $q->orderBy('id');
    }

    public function render()
    {
        // Taken before the page is read, so a change made while it is drawn shows on the next tick
        $this->drawnAt = $this->stamp();
        $campaign = $this->campaign();
        $sender = User::find($campaign->sender_id);
        $stats = Outreach::stats([$campaign->id])[$campaign->id];
        $counts = [
            'review' => $stats['to_review'], 'writing' => $stats['writing'] + $stats['not_written'], 'approved' => $stats['approved'],
            'sent' => $stats['sent'], 'stopped' => $stats['skipped'] + $stats['failed'] + OutreachEmail::where('campaign_id', $campaign->id)->where('kind', 'first')->where('status', 'bounced')->count(),
            'all' => $stats['prospects'], 'replies' => $stats['replies'],
        ];
        // The Replies tab is its own component (OutreachReplies): no prospects to list here then
        $replies = $this->filter === 'replies';
        $total = $replies ? 0 : (clone $this->rows())->count();
        $rows = $replies ? collect() : $this->rows()->limit($this->limit)->get();
        $followUps = OutreachEmail::whereIn('prospect_id', $rows->pluck('prospect_id'))->where('kind', 'follow_up')->get()->keyBy('prospect_id');

        $open = $this->current();
        if ($this->openId && ! $open) {
            $this->closeEmail();
        }

        return view('livewire.hq.campaigns.outreach.outreach-campaign', [
            'campaign' => $campaign,
            'sender' => $sender,
            'senderDay' => Senders::summary($sender),
            'stats' => $stats,
            'counts' => $counts,
            'rows' => $rows,
            'followUps' => $followUps,
            'more' => max($total - $this->limit, 0),
            'handApproved' => Outreach::handApproved($campaign),
            'open' => $open,
            'openProspect' => $open ? OutreachProspect::find($open->prospect_id) : null,
            'openFollowUp' => $open ? OutreachEmail::where('prospect_id', $open->prospect_id)->where('kind', 'follow_up')->first() : null,
            'busy' => $stats['writing'] > 0 || ($open && Drafts::busy($open)),
            'canApprove' => Outreach::canApprove(Auth::user()),
            'window' => Limits::windowText(),
            'gap' => (int) Limits::get('gap_minutes'),
            'followUpDays' => (int) Limits::get('follow_up_days'),
        ])->title($campaign->name . ' · Outreach');
    }
}
