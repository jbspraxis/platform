<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\WithGatewayLanguage;
use App\Mail\SigningCode;
use App\Models\Proposal;
use App\Support\MailLocale;
use App\Support\Opportunities\PortalLinks;
use App\Support\Opportunities\Proposals;
use App\Support\Signing\SigningCodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
 * Approving (D5) is for the organisation's approvers only: a link sent to a contact approves as that contact (the
 * address typed is ignored), and a link without one asks for a code emailed to the approver's address first.
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

    /** Approving from a link without a contact: details (name, title, address), then code (the one emailed) */
    #[Locked]
    public string $step = 'details';

    public string $code = '';

    /** Who the emailed code approves as: name, title and address, kept from the request (D5) */
    #[Locked]
    public array $pending = [];

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

    /** Read once per request (the action and the render share it); forgotten after anything that writes */
    private ?Proposal $loaded = null;

    /** The link's contact address, read once per request: [address] once read */
    private ?array $linkEmailRead = null;

    private function proposal(): Proposal
    {
        return $this->loaded ??= Proposal::with('opportunity.organization')->findOrFail($this->proposalId);
    }

    /** Before a write: the render reads the version again, so it shows the new status */
    private function changed(): void
    {
        $this->loaded = null;
    }

    public function openComment(string $section): void
    {
        $this->resetErrorBag();
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
        $this->commenting = '';
        $this->step = 'details';
        $this->code = '';
        $this->pending = [];
        $this->mode = in_array($mode, ['approve', 'changes'], true) ? $mode : '';
        if ($this->mode === 'approve' && ! $this->contactMayApprove($this->proposal())) {
            $this->mode = '';
        }
    }

    /** The address of the contact the link was sent to, if it has one: it approves as them, whatever is typed (D5) */
    private function linkEmail(): ?string
    {
        $this->linkEmailRead ??= [$this->contactId ? (DB::table('client_users')->where('id', $this->contactId)->whereNull('deleted_at')->value('email') ?: null) : null];

        return $this->linkEmailRead[0];
    }

    /** Whoever the link was sent to approves for the organisation (D5); a link without a contact can't tell yet */
    private function contactMayApprove(Proposal $p): bool
    {
        $email = $this->linkEmail();

        return ! $email || Proposals::isApprover($p->opportunity, (string) $email);
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
        $this->changed();
        if (! Proposals::comment($p, $this->commenting, ['name' => $this->name, 'email' => $person['email']], $this->body)) {
            $this->addError('body', __('proposals.portal.errors.full'));

            return;
        }
        $this->commenting = '';
        $this->body = '';
        session()->flash('success', __('proposals.portal.done_commented'));
    }

    public function approve(): void
    {
        $p = $this->proposal();
        if (! $this->open($p) || ! $this->valid($p)) {
            return;
        }
        $this->jobTitle = trim($this->jobTitle);
        // A link sent to a contact approves as that contact: the address typed doesn't count (D5)
        $linkEmail = $this->linkEmail();
        if ($linkEmail !== null) {
            $this->email = $linkEmail;
        }
        $this->person(['jobTitle' => ['required', 'string', 'max:150']]);
        if (! ($person = $this->known($p))) {
            return;
        }
        // Only the organisation's approvers approve (D5); other contacts and meeting guests read, comment and ask for changes
        if (! Proposals::isApprover($p->opportunity, $person['email'])) {
            $error = Proposals::isActiveContact($p->opportunity, $person['email']) ? 'not_approver' : 'not_contact';
            $this->addError('email', __('proposals.portal.errors.' . $error, ['company' => $p->opportunity->organization?->name]));

            return;
        }
        if ($linkEmail !== null) {
            $this->finishApproval($p, ['name' => $this->name, 'title' => $this->jobTitle, 'email' => $person['email']]);

            return;
        }

        // A link without a contact: the approver confirms with a code emailed to their address, so nobody approves under
        // someone else's (SigningCodes: 3 codes per version and per address, 5 wrong codes, every 15 minutes)
        if ($refused = SigningCodes::claimSend('approval', $p->id, $person['email'])) {
            $this->addError('code', __('proposals.portal.errors.' . $refused[0], ['wait' => $this->minutes($refused[1])]));

            return;
        }
        $email = mb_strtolower(trim((string) $person['email']));
        $code = SigningCodes::newCode();
        $this->changed();
        // The code is kept with the address it was sent to, so it approves for that address only
        if (! Proposal::whereKey($p->id)->where('status', Proposal::SENT)
            ->update(['auth_otp' => SigningCodes::hash($code . '|' . $email), 'otp_expires_at' => now()->addMinutes(SigningCodes::MINUTES)])) {
            $this->addError('mode', __('proposals.portal.errors.closed'));

            return;
        }
        try {
            $locale = session()->has('locale') ? MailLocale::forVisitor() : MailLocale::forRecipient($p->opportunity->language);
            Mail::to($email)->locale($locale)->send(new SigningCode($code, SigningCode::APPROVAL, $p->title, explode(' ', $this->name)[0] ?: null,
                signer: $this->name . ', ' . $this->jobTitle));
        } catch (\Throwable $e) {
            // Never log the code itself; the unsent code is cancelled
            Log::error('Proposal approval code email failed', ['proposal_id' => $p->id, 'message' => $e->getMessage()]);
            Proposal::whereKey($p->id)->update(['auth_otp' => null, 'otp_expires_at' => null]);
            $this->addError('email', __('proposals.portal.errors.email_failed'));

            return;
        }
        $this->pending = ['name' => $this->name, 'title' => $this->jobTitle, 'email' => $email];
        $this->code = '';
        $this->step = 'code';
    }

    /** The emailed code: the right one approves, as the person it was sent for */
    public function verifyApproval(): void
    {
        $p = $this->proposal();
        if (! $this->open($p) || ! $this->valid($p) || ! $this->pending) {
            return;
        }
        $this->code = trim($this->code);
        $this->validate(['code' => ['required', 'digits:6']], ['code.*' => __('proposals.portal.errors.code_format')]);
        $this->changed();
        $refused = SigningCodes::check('approval', $p->id, $p->auth_otp ? (string) $p->auth_otp : null, $p->otp_expires_at, $this->code . '|' . $this->pending['email']);
        if ($refused) {
            if ($refused[0] === 'cancel') {
                Proposal::whereKey($p->id)->update(['auth_otp' => null, 'otp_expires_at' => null]);
            }
            $this->addError('code', $refused[0] === 'wrong' ? __('proposals.portal.errors.code_invalid') : __('proposals.portal.errors.too_many_attempts', ['wait' => $this->minutes($refused[1])]));

            return;
        }
        Proposal::whereKey($p->id)->update(['auth_otp' => null, 'otp_expires_at' => null]);
        $this->finishApproval($p, $this->pending);
    }

    /** Back to the details: a new code, or another approver */
    public function startAgain(): void
    {
        $this->resetErrorBag();
        $this->step = 'details';
        $this->code = '';
        $this->pending = [];
    }

    private function finishApproval(Proposal $p, array $person): void
    {
        $this->changed();
        if (! Proposals::approveForClient($p, ['name' => $person['name'], 'email' => $person['email']], $person['title'])) {
            $this->addError('mode', __('proposals.portal.errors.closed'));

            return;
        }
        $this->mode = '';
        $this->step = 'details';
        $this->code = '';
        $this->pending = [];
        session()->flash('success', __('proposals.portal.done_approved'));
    }

    private function minutes(int $minutes): string
    {
        return trans_choice('gateway.proposal.wait_minutes', $minutes, ['count' => $minutes]);
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
        $this->changed();
        if (! Proposals::requestChanges($p, ['name' => $this->name, 'email' => $person['email']], $this->changeNote)) {
            $this->addError('mode', __('proposals.portal.errors.closed'));

            return;
        }
        $this->mode = '';
        $this->changeNote = '';
        session()->flash('success', __('proposals.portal.done_changes'));
    }

    /**
     * The link is still valid (Livewire's update requests don't go through `signed`, so its expiry, kept at mount,
     * is checked here), and the version still waits for the client: sent, not answered, not replaced by a later
     * version, and the work not lost
     */
    private function open(Proposal $p): bool
    {
        $this->resetErrorBag();
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
            'vat' => $vat = Proposals::vat($p),
            'totals' => Proposals::totals($lines, (float) $vat['rate']),
            'newerUrl' => $newer ? PortalLinks::proposal($newer, $this->contactId) : null,
            'pdfUrl' => PortalLinks::proposalPdf($p),
            'meetingsUrl' => $o->stage !== 'lost' ? PortalLinks::meetings($o, $this->contactId) : null,
            'reviewing' => $p->status === Proposal::SENT && $o->stage !== 'lost' && ! $p->hasExpired(),
            'mayApprove' => $this->contactMayApprove($p),
            'linkEmail' => $this->linkEmail(),
        ])->title(__('proposals.portal.page_title', ['title' => $p->title]));
    }
}
