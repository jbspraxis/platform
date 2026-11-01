<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\WithGatewayLanguage;
use App\Models\Proposal;
use App\Support\Opportunities\PortalLinks;
use App\Support\Opportunities\Proposals;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One version of an opportunity's proposal, as the client reviews it (docs/OPPORTUNITIES.md decisions 8 and 9),
 * opened from the signed link in the proposal email (PortalLinks::proposal). They read it, comment on any section,
 * approve it with one click (name, title and email recorded) or ask for changes, download the PDF, or book a
 * follow-up on their meetings page. Only a version that was sent opens here, never a draft.
 *
 * Whoever acts must use an address HQ already knows for this work (Proposals::knows), so a forwarded link can't act
 * for anyone; comments are capped in size and number and limited per hour, and unknown addresses are limited too.
 */
#[Layout('components.layouts.portal')]
class ProposalReview extends Component
{
    use WithGatewayLanguage;

    /** Addresses that aren't known, per version, in 15 minutes: so the page can't be used to test addresses */
    public const MAX_UNKNOWN = 5;

    #[Locked]
    public string $proposalId;

    #[Locked]
    public ?string $contactId = null;

    /** The section whose comment form is open ('general' for the proposal as a whole) */
    #[Locked]
    public string $commenting = '';

    /** '' | approve | changes */
    #[Locked]
    public string $mode = '';

    public string $name = '';

    public string $email = '';

    public string $jobTitle = '';

    public string $body = '';

    public string $changeNote = '';

    /** commented | approved | changes, once done */
    #[Locked]
    public string $done = '';

    /** When the signed link stops working (its expires parameter, a Unix time); 0 when it has none */
    #[Locked]
    public int $linkExpiresAt = 0;

    public function mount(string $proposal): void
    {
        $p = Proposal::with('opportunity')->where('signing_mode', 'approve')->whereNotNull('sent_at')->find($proposal);
        abort_unless($p && $p->opportunity, 404);
        $this->useGatewayLanguage($p->opportunity->language);
        $this->proposalId = $p->id;
        // The route checked the signature; later requests (Livewire updates) check this instead
        $this->linkExpiresAt = (int) request()->query('expires') ?: (int) $p->validUntil()?->getTimestamp();

        $contact = request()->query('c');
        $contact = is_string($contact) && Str::isUuid($contact)
            ? DB::table('client_users')->where('id', $contact)->where('organization_id', $p->opportunity->organization_id)->whereNull('deleted_at')->first(['id', 'first_name', 'last_name', 'email', 'job_title'])
            : null;
        if ($contact) {
            $this->contactId = $contact->id;
            $this->name = trim($contact->first_name . ' ' . $contact->last_name);
            $this->email = (string) $contact->email;
            $this->jobTitle = (string) $contact->job_title;
        }
    }

    private function proposal(): Proposal
    {
        return Proposal::with('opportunity.organization')->findOrFail($this->proposalId);
    }

    public function openComment(string $section): void
    {
        $this->resetErrorBag();
        $this->done = '';
        $this->mode = '';
        $this->commenting = Proposals::hasSection($this->proposal(), $section) ? $section : '';
        $this->body = '';
    }

    public function closeComment(): void
    {
        $this->commenting = '';
        $this->body = '';
        $this->resetErrorBag();
    }

    public function choose(string $mode): void
    {
        $this->resetErrorBag();
        $this->done = '';
        $this->commenting = '';
        $this->mode = in_array($mode, ['approve', 'changes'], true) ? $mode : '';
    }

    public function comment(): void
    {
        $p = $this->proposal();
        if (! $this->open($p) || ! $this->valid($p) || $this->commenting === '') {
            return;
        }
        $this->body = trim($this->body);
        $this->person(['body' => ['required', 'string', 'max:' . Proposals::MAX_COMMENT]]);
        if (! ($person = $this->known($p))) {
            return;
        }
        // Per person, so one busy commenter (or someone trying a forwarded link) doesn't silence the others
        $key = 'portal-proposal-comments:' . $p->id . ':' . sha1($person['email']);
        if (RateLimiter::tooManyAttempts($key, Proposals::COMMENTS_PER_HOUR)) {
            $this->addError('body', __('proposals.portal.errors.too_many'));

            return;
        }
        RateLimiter::hit($key, 3600);
        if (! Proposals::comment($p, $this->commenting, ['name' => $this->name, 'email' => $person['email']], $this->body)) {
            $this->addError('body', __('proposals.portal.errors.full'));

            return;
        }
        $this->commenting = '';
        $this->body = '';
        $this->done = 'commented';
    }

    public function approve(): void
    {
        $p = $this->proposal();
        if (! $this->open($p) || ! $this->valid($p)) {
            return;
        }
        $this->jobTitle = trim($this->jobTitle);
        $this->person(['jobTitle' => ['required', 'string', 'max:150']]);
        if (! ($person = $this->known($p))) {
            return;
        }
        // Only the organisation's own active contacts approve; meeting guests read and comment
        if (! Proposals::isActiveContact($p->opportunity, $person['email'])) {
            $this->addError('email', __('proposals.portal.errors.not_contact', ['company' => $p->opportunity->organization?->name]));

            return;
        }
        if (! Proposals::approveForClient($p, ['name' => $this->name, 'email' => $person['email']], $this->jobTitle)) {
            $this->addError('mode', __('proposals.portal.errors.closed'));

            return;
        }
        $this->mode = '';
        $this->done = 'approved';
    }

    public function requestChanges(): void
    {
        $p = $this->proposal();
        if (! $this->open($p) || ! $this->valid($p)) {
            return;
        }
        $this->changeNote = trim($this->changeNote);
        $this->person(['changeNote' => ['nullable', 'string', 'max:' . Proposals::MAX_COMMENT]]);
        if (! ($person = $this->known($p))) {
            return;
        }
        if (! Proposals::requestChanges($p, ['name' => $this->name, 'email' => $person['email']], $this->changeNote)) {
            $this->addError('mode', __('proposals.portal.errors.closed'));

            return;
        }
        $this->mode = '';
        $this->changeNote = '';
        $this->done = 'changes';
    }

    /**
     * The link is still valid (Livewire's update requests don't go through `signed`, so its expiry, kept at mount,
     * is checked here), and the version still waits for the client: sent, not answered, not replaced by a later
     * version, and the work not lost
     */
    private function open(Proposal $p): bool
    {
        $this->resetErrorBag();
        $this->done = '';
        if ($this->linkExpiresAt > 0 && now()->getTimestamp() > $this->linkExpiresAt) {
            $this->addError('mode', __('proposals.portal.errors.link_expired'));

            return false;
        }
        if ($p->status !== Proposal::SENT || $p->opportunity?->stage === 'lost') {
            $this->addError('mode', __('proposals.portal.errors.closed'));

            return false;
        }

        return true;
    }

    /** Within the proposal's validity: after it, no comment, approval or change request (the page offers a meeting) */
    private function valid(Proposal $p): bool
    {
        if ($p->hasExpired()) {
            $this->addError('mode', __('proposals.portal.errors.expired'));

            return false;
        }

        return true;
    }

    /** Name and email of whoever acts, with the rules of what they send */
    private function person(array $rules): void
    {
        $this->name = trim($this->name);
        $this->email = trim($this->email);
        $this->validate($rules + [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
        ], [
            'name.required' => __('proposals.portal.errors.name'),
            'name.max' => __('proposals.portal.errors.name_long'),
            'email.*' => __('proposals.portal.errors.email'),
            'jobTitle.required' => __('proposals.portal.errors.job_title'),
            'jobTitle.max' => __('proposals.portal.errors.job_title_long'),
            'body.required' => __('proposals.portal.errors.body'),
            'body.max' => __('proposals.portal.errors.body_long', ['max' => Proposals::MAX_COMMENT]),
            'changeNote.max' => __('proposals.portal.errors.body_long', ['max' => Proposals::MAX_COMMENT]),
        ]);
    }

    /**
     * Someone HQ knows for this work. Addresses it doesn't know count against this version and this visitor's IP (only
     * misses count), so they can't be tried one after another, and someone trying them doesn't lock out the client
     */
    public static function unknownKey(string $proposalId, ?string $ip): string
    {
        return 'portal-proposal-unknown:' . $proposalId . ':' . sha1((string) $ip);
    }

    private function known(Proposal $p): ?array
    {
        $key = self::unknownKey($p->id, request()->ip());
        if (RateLimiter::tooManyAttempts($key, self::MAX_UNKNOWN)) {
            $this->addError('email', __('proposals.portal.errors.wait'));

            return null;
        }
        $person = Proposals::knows($p, $this->email);
        if (! $person) {
            RateLimiter::hit($key, 900);
            $this->addError('email', __('proposals.portal.errors.unknown_email'));
        }

        return $person;
    }

    public function render()
    {
        $p = $this->proposal();
        $o = $p->opportunity;
        $lines = Proposals::lines($p);
        // A later version the client was sent replaces this one
        $newer = $p->status === Proposal::SUPERSEDED
            ? Proposal::where('opportunity_id', $o->id)->where('signing_mode', 'approve')->whereNotNull('sent_at')->where('version', '>', $p->version)->orderByDesc('version')->first()
            : null;

        return view('livewire.portal.proposal-review', [
            'p' => $p,
            'o' => $o,
            'comments' => $p->comments()->where('author_type', 'client')->get()->groupBy('section_key'),
            'lines' => $lines,
            'totals' => Proposals::totals($lines, (float) $p->vat_rate),
            'newerUrl' => $newer ? PortalLinks::proposal($newer, $this->contactId) : null,
            'pdfUrl' => PortalLinks::proposalPdf($p),
            'meetingsUrl' => $o->stage !== 'lost' ? PortalLinks::meetings($o, $this->contactId) : null,
            'reviewing' => $p->status === Proposal::SENT && $o->stage !== 'lost' && ! $p->hasExpired(),
        ])->title(__('proposals.portal.page_title', ['title' => $p->title]));
    }
}
