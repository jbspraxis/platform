<?php

namespace App\Support\Opportunities;

use App\Events\SowSigned;
use App\Mail\SowMail;
use App\Models\AiRun;
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\StatementOfWork;
use App\Models\User;
use App\Support\Access;
use App\Support\Ai\Ai;
use App\Support\Finance\DepositInvoice;
use App\Support\Finance\Vat;
use App\Support\Leads\LeadStatus;
use App\Support\Portal\SignerOnboarding;
use App\Support\Signing\SigningCodes;
use App\Support\WebsiteSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

/**
 * An opportunity's statement of work, version by version (docs/OPPORTUNITIES.md section 4, row 5; owner decisions
 * 7, 9 and 10). The AI drafts it in the background (purpose sow, Claude Sonnet) from the proposal the client approved
 * (status Client Approved, one per opportunity) and the opportunity's notes and answers; the lead edits its sections
 * and milestones (title, what it delivers and how it is accepted, weeks, weight: its share of the contract, all adding
 * up to 100%). The owner or the lead approves it by signing it for JBS Praxis (the HQ countersignature, as the legacy
 * builder did); it is then sent (a branded email with the PDF and a signed portal link, as proposals are), and an
 * active contact of the organisation signs it in the portal with the one-time code (App\Support\Signing\SigningCodes).
 * Signing creates the project (it holds the deposit invoice), raises the deposit invoice, makes the opportunity Won and
 * queues the project draft (ProjectDrafts). The fees are never the AI's: they come from the approved proposal.
 *
 * Every change to which versions are open, sent or signed locks the opportunity's row first, then the version's, as
 * Proposals does. Callers check the permission first.
 */
final class Sows
{
    public const MAX_SECTIONS = 20;

    public const MAX_BODY = 20000;

    public const MAX_TITLE = 200;

    public const MAX_NOTE = 2000;

    public const MAX_MILESTONES = 20;

    public const MAX_RECIPIENTS = Proposals::MAX_RECIPIENTS;

    public const VALIDITY_DAYS = 30;

    /** The sections a blank SOW starts with, in this order (titles from lang sow.sections) */
    public const BLANK_SECTIONS = ['purpose', 'scope', 'deliverables', 'assumptions', 'timeline', 'governance', 'change_control'];

    /** Shown while the owner hasn't set a legal name (HQ › Website settings › Legal name); approval waits for one */
    public const LEGAL_NAME_PLACEHOLDER = '[JBS Praxis legal name: set it in Website settings]';

    public static function canEdit(?User $user, Opportunity $opportunity): bool
    {
        return OpportunityAccess::canManage($user, $opportunity) && ! $opportunity->isClosed();
    }

    /** The AI's draft retells the meetings (their notes, or their transcripts when asked): only for someone who may read them */
    public static function canDraftWithAi(?User $user, Opportunity $opportunity): bool
    {
        return self::canEdit($user, $opportunity) && OpportunityAccess::canReadTranscripts($user, $opportunity);
    }

    /** Approve (and sign for JBS Praxis) before it goes out: the owner or the opportunity's lead (decision 7) */
    public static function canApprove(?User $user, Opportunity $opportunity): bool
    {
        return OpportunityAccess::canApprove($user, $opportunity) && ! $opportunity->isClosed();
    }

    /**
     * JBS Praxis's legal name as the SOW names it: the company's legal name in HQ › Website settings (the one the
     * site's company page and privacy policy show). Null when it is empty: the SOW shows LEGAL_NAME_PLACEHOLDER and
     * can't be approved. HQ keeps the name as it stood when HQ signed (provider_legal_name).
     */
    public static function legalName(): ?string
    {
        $name = trim((string) WebsiteSettings::get('company.legal_name'));

        return $name !== '' ? $name : null;
    }

    /** The name on a version: as HQ signed it, else today's setting, else the placeholder */
    public static function partyName(StatementOfWork $sow): string
    {
        return $sow->provider_legal_name ?: (self::providerName($sow) ?? self::LEGAL_NAME_PLACEHOLDER);
    }

    /**
     * The JBS Praxis party a version names: the legal name of the company issuing it (Master data › Legal entities: the
     * opportunity's billing company, else Nigeria's; Sows::vat), else Website settings' legal name (legalName())
     */
    public static function providerName(StatementOfWork $sow): ?string
    {
        return Vat::companyName(self::vat($sow)['entity_id']) ?? self::legalName();
    }

    /** The proposal the client approved: what every SOW of this opportunity is drafted from and priced by */
    public static function approvedProposal(Opportunity $opportunity): ?Proposal
    {
        return Proposals::clientApproved($opportunity->id);
    }

    /** The opportunity's SOW versions, latest first */
    public static function versions(Opportunity $opportunity): Collection
    {
        return StatementOfWork::where('opportunity_id', $opportunity->id)->orderByDesc('version')->get();
    }

    public static function signed(string $opportunityId): ?StatementOfWork
    {
        return StatementOfWork::where('opportunity_id', $opportunityId)->where('status', StatementOfWork::SIGNED)->first();
    }

    /** Version 1 from the approved proposal: blank sections for the lead, or ready for the AI. A second click makes nothing. */
    public static function start(Opportunity $opportunity, User $by): ?StatementOfWork
    {
        return DB::transaction(function () use ($opportunity, $by) {
            self::lockOpportunity($opportunity->id);
            if ($existing = self::versions($opportunity)->first()) {
                return $existing;
            }
            $proposal = self::approvedProposal($opportunity);
            if (! $proposal) {
                return null;
            }
            $sow = StatementOfWork::create([
                'opportunity_id' => $opportunity->id,
                'proposal_id' => $proposal->id,
                'reference' => self::reference(),
                'title' => Str::limit((string) $proposal->title, self::MAX_TITLE, ''),
                'version' => 1,
                'sections' => array_map(fn ($key) => ['key' => $key, 'title' => __('sow.sections.' . $key, [], $opportunity->language), 'body' => ''], self::BLANK_SECTIONS),
                'milestones' => [],
                'status' => StatementOfWork::DRAFT,
                'validity_days' => self::VALIDITY_DAYS,
                'created_by' => $by->id,
                'updated_by' => $by->id,
            ]);
            OpportunityAccess::log('SOW_STARTED', "Started the statement of work for “{$opportunity->title}” ({$sow->reference}).", 'opportunities', $opportunity->id,
                [], ['sow_id' => $sow->id, 'version' => 1]);
            self::moveStage($opportunity, ['proposal_approved'], 'sow');

            return $sow;
        });
    }

    /**
     * Asks the AI for this version's text and milestones, in the background (one draft per click; AiUnavailable, no key
     * or over the budget, before anything is spent). It reads the meetings' notes; $withTranscripts adds their full
     * transcripts (6.2, cost rule 4). The draft replaces the title, sections and milestones when it arrives.
     */
    public static function draft(StatementOfWork $sow, string $instructions, bool $withTranscripts, User $by): bool
    {
        self::sync($sow);
        if (! $sow->isEditable() || Proposals::running($sow->ai_run_id)) {
            return false;
        }
        $opportunity = $sow->opportunity;
        // The SOW part is left out: the AI reads earlier versions in MATERIAL, so the cached context stays the same
        $parts = ['profile', 'copq', 'meetings', 'internal', 'notes', 'questionnaire', 'proposal'];

        $run = Ai::queue('sow', $sow, [
            'context' => OpportunityContext::for($opportunity, include: $withTranscripts ? [...$parts, 'transcripts'] : $parts),
            'instructions' => Str::limit(trim($instructions), self::MAX_NOTE, ''),
            'material' => self::draftMaterial($sow),
            'language' => $opportunity->language,
        ]);
        $sow->forceFill(['ai_run_id' => $run->id, 'updated_by' => $by->id])->save();

        OpportunityAccess::log('SOW_DRAFT_REQUESTED', "Asked the AI to draft version {$sow->version} of the statement of work for “{$opportunity->title}”.",
            'opportunities', $opportunity->id, [], ['sow_id' => $sow->id, 'version' => $sow->version, 'transcripts' => $withTranscripts]);
        self::sync($sow);

        return true;
    }

    /** Applies a finished draft (row locked, once). True when it wrote something. A failed job stays linked until dismissed. */
    public static function sync(StatementOfWork $sow): bool
    {
        $runId = $sow->ai_run_id;
        if (! $runId || ! AiRun::whereKey($runId)->where('status', 'done')->exists()) {
            return false;
        }
        $applied = DB::transaction(function () use ($sow, $runId) {
            $locked = StatementOfWork::whereKey($sow->id)->lockForUpdate()->first();
            $run = AiRun::find($runId);
            if (! $locked || ! $run || $locked->ai_run_id !== $runId) {
                return false;
            }
            if (! $locked->isEditable()) {
                $locked->forceFill(['ai_run_id' => null])->save();

                return false;
            }

            return self::applyDraft($locked, $run);
        });
        if ($applied) {
            $sow->refresh();
        }

        return $applied;
    }

    /** The job finished (FileSowAi on AiRunFinished): filed at once, and the lead gets a bell unless the page waited for it */
    public static function fileAiOutput(AiRun $run): void
    {
        if ($run->purpose !== 'sow' || $run->subject_type !== 'statement_of_work' || ! $run->isFinished()) {
            return;
        }
        $sow = StatementOfWork::find($run->subject_id);
        if (! $sow || $sow->ai_run_id !== $run->id) {
            return;
        }
        self::sync($sow);
        $opportunity = $sow->opportunity;
        $locale = config('app.staff_locale', 'en');
        if ($sow->ai_run_id === null) {
            self::tellLead($opportunity, __('sow.notify.ready_title', ['title' => $opportunity->title], $locale), __('sow.notify.ready', ['n' => $sow->version], $locale));
        } elseif (($run = $run->fresh())->status === 'failed') {
            self::tellLead($opportunity, __('sow.notify.failed_title', ['title' => $opportunity->title], $locale), __('sow.notify.failed', ['message' => $run->errorMessage()], $locale));
        }
    }

    public static function dismissFailure(StatementOfWork $sow): void
    {
        if ($sow->ai_run_id && ! Proposals::running($sow->ai_run_id)) {
            $sow->forceFill(['ai_run_id' => null])->save();
        }
    }

    /**
     * Saves the lead's edits to a draft nobody has approved, while the AI isn't rewriting it. $data: title, sections
     * ([key, title, body]), milestones ([key, title, description, weeks, weight]), validity_days, changes_summary.
     */
    public static function save(StatementOfWork $sow, array $data, User $by): bool
    {
        return DB::transaction(function () use ($sow, $data, $by) {
            $locked = StatementOfWork::whereKey($sow->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->isEditable() || Proposals::running($locked->ai_run_id)) {
                return false;
            }
            $locked->forceFill([
                'title' => Str::limit(trim((string) ($data['title'] ?? $locked->title)), self::MAX_TITLE, '') ?: $locked->title,
                'sections' => Proposals::cleanSections((array) ($data['sections'] ?? []), keepEmpty: true),
                'milestones' => self::cleanMilestones((array) ($data['milestones'] ?? []), keepEmpty: true),
                'validity_days' => max(7, min(90, (int) ($data['validity_days'] ?? $locked->validity_days))),
                'changes_summary' => $locked->version < 2 ? null : (Str::limit(trim((string) ($data['changes_summary'] ?? '')), self::MAX_NOTE, '') ?: null),
                'updated_by' => $by->id,
            ])->save();
            $sow->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    /** The next version, from the latest one the client has (sent or with changes asked for), its text copied */
    public static function newVersion(Opportunity $opportunity, User $by): ?StatementOfWork
    {
        return DB::transaction(function () use ($opportunity, $by) {
            self::lockOpportunity($opportunity->id);
            $latest = self::versions($opportunity)->first();
            if (! $latest || ! in_array($latest->status, [StatementOfWork::SENT, StatementOfWork::CHANGES_REQUESTED], true) || self::signed($opportunity->id)) {
                return null;
            }
            $next = $latest->replicate(['ai_run_id', 'approved_by', 'approved_at', 'sent_at', 'recipients', 'recipients_unsent', 'token_hash', 'auth_otp', 'otp_expires_at',
                'hq_signer_name', 'hq_signature_b64', 'hq_signature_hash', 'hq_ip_address', 'hq_signed_at', 'provider_legal_name', 'signed_by_name', 'signed_by_title',
                'signed_by_email', 'client_signature_b64', 'client_signature_hash', 'client_ip_address', 'ip_security_flag', 'signed_at', 'deposit_invoice_id', 'project_id',
                'changes_request', 'changes_requested_by', 'changes_requested_at', 'changes_summary', 'deleted_by', 'vat_rate', 'vat_applied', 'billing_entity_id']);
            $next->forceFill([
                'version' => $latest->version + 1,
                'previous_version_id' => $latest->id,
                'status' => StatementOfWork::DRAFT,
                'created_by' => $by->id,
                'updated_by' => $by->id,
            ])->save();
            OpportunityAccess::log('SOW_VERSION_STARTED', "Started version {$next->version} of the statement of work for “{$opportunity->title}”.", 'opportunities', $opportunity->id,
                [], ['sow_id' => $next->id, 'version' => $next->version]);

            return $next;
        });
    }

    /** Sets a draft aside (soft delete); an approved one only by someone who may approve. Never once sent. */
    public static function discard(StatementOfWork $sow, User $by, bool $mayApprove = false): bool
    {
        $done = DB::transaction(function () use ($sow, $by, $mayApprove) {
            self::lockOpportunity($sow->opportunity_id);
            $locked = StatementOfWork::whereKey($sow->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== StatementOfWork::DRAFT || $locked->sent_at !== null || ($locked->approved_at !== null && ! $mayApprove)) {
                return false;
            }
            $locked->forceFill(['deleted_by' => $by->id])->save();
            $locked->delete();

            return true;
        });
        if ($done) {
            OpportunityAccess::log('SOW_VERSION_DISCARDED', "Discarded the draft of version {$sow->version} of the statement of work.", 'opportunities', $sow->opportunity_id,
                ['sow_id' => $sow->id, 'version' => $sow->version], []);
        }

        return $done;
    }

    /**
     * Approves a draft for sending by signing it for JBS Praxis (decision 7, and the HQ countersignature the legacy
     * builder kept): the approver's name and drawn signature, a SHA-256 fingerprint, their IP and the time, and the
     * legal name as it stands. Returns null when done, else why not: signed (the client signed a version already),
     * running, empty (no title or no section with text), milestones (none, or their weights don't add up to 100%),
     * changes (a later version says what changed), legal_name (not set), proposal (no approved proposal), state.
     */
    public static function approve(StatementOfWork $sow, User $by, string $signerName, string $signature, ?string $ip): ?string
    {
        self::sync($sow);
        $why = DB::transaction(function () use ($sow, $by, $signerName, $signature, $ip) {
            self::lockOpportunity($sow->opportunity_id);
            $locked = StatementOfWork::whereKey($sow->id)->lockForUpdate()->first();
            if ($locked && self::signed($locked->opportunity_id)) {
                return 'signed';
            }
            if (! $locked || ! $locked->isEditable()) {
                return 'state';
            }
            if (Proposals::running($locked->ai_run_id)) {
                return 'running';
            }
            $written = collect((array) $locked->sections)->filter(fn ($s) => trim((string) ($s['body'] ?? '')) !== '');
            if (trim((string) $locked->title) === '' || $written->isEmpty()) {
                return 'empty';
            }
            $milestones = self::cleanMilestones((array) $locked->milestones);
            if (! $milestones || abs(self::weightTotal($milestones) - 100) > 0.001) {
                return 'milestones';
            }
            if ($locked->version > 1 && trim((string) $locked->changes_summary) === '') {
                return 'changes';
            }
            if (! ($legalName = self::providerName($locked))) {
                return 'legal_name';
            }
            if (! $locked->proposal_id || ! Proposal::whereKey($locked->proposal_id)->where('status', Proposal::CLIENT_APPROVED)->exists()) {
                return 'proposal';
            }
            $at = now();
            // The VAT and the issuing company HQ signs with the rest (App\Support\Finance\Vat), as of today; both are in the fingerprint
            $vat = Vat::for($locked->opportunity?->organization, $at, $locked->opportunity?->billing_entity_id);
            $locked->forceFill(['vat_rate' => $vat['rate'], 'vat_applied' => $vat['applies'], 'billing_entity_id' => $vat['entity_id']]);
            $locked->forceFill([
                'milestones' => $milestones,
                'approved_by' => $by->id,
                'approved_at' => $at,
                'hq_signer_name' => Str::limit(trim($signerName), 150, ''),
                'hq_signature_b64' => $signature,
                'hq_signature_hash' => 'SHA256-' . strtoupper(hash('sha256', $locked->id . self::contentHash($locked) . trim($signerName) . $at)),
                'hq_ip_address' => $ip,
                'hq_signed_at' => $at,
                'provider_legal_name' => $legalName,
                'updated_by' => $by->id,
            ])->save();
            $sow->setRawAttributes($locked->getAttributes(), true);

            return null;
        });
        if ($why === null) {
            OpportunityAccess::log('SOW_APPROVED', "Approved and signed version {$sow->version} of the statement of work for JBS Praxis.", 'opportunities', $sow->opportunity_id,
                [], ['sow_id' => $sow->id, 'version' => $sow->version, 'signer' => $sow->hq_signer_name]);
        }

        return $why;
    }

    /** An approved version that hasn't been sent goes back to draft: HQ's signature is removed with the approval */
    public static function reopen(StatementOfWork $sow, User $by): bool
    {
        $done = (bool) StatementOfWork::whereKey($sow->id)->where('status', StatementOfWork::DRAFT)->whereNotNull('approved_at')->whereNull('sent_at')->update([
            'approved_by' => null, 'approved_at' => null, 'hq_signer_name' => null, 'hq_signature_b64' => null, 'hq_signature_hash' => null,
            'hq_ip_address' => null, 'hq_signed_at' => null, 'provider_legal_name' => null, 'vat_rate' => null, 'vat_applied' => null, 'billing_entity_id' => null, 'updated_by' => $by->id, 'updated_at' => now(),
        ]);
        if ($done) {
            $sow->refresh();
            OpportunityAccess::log('SOW_REOPENED', "Took version {$sow->version} of the statement of work back to draft; HQ’s signature was removed.", 'opportunities', $sow->opportunity_id,
                ['approved' => true], ['sow_id' => $sow->id, 'approved' => false]);
        }

        return $done;
    }

    /** Someone HQ knows for this work: one the version was sent to, or one of Proposals::people() */
    public static function knows(StatementOfWork $sow, string $email): ?array
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return null;
        }
        foreach ((array) $sow->recipients as $r) {
            if (mb_strtolower((string) ($r['email'] ?? '')) === $email) {
                return $r;
            }
        }

        return Proposals::people($sow->opportunity)[$email] ?? null;
    }

    /**
     * Sends an approved version: the branded email with the PDF and the portal link, in the opportunity's language,
     * to people chosen from Proposals::people(). As Proposals::send: claimed under the locks before any email, every
     * address on recipients_unsent until its email goes. The earlier versions with the client are superseded in the
     * same claim, so from that moment they can't be signed, whatever happens to the emails; if nobody is reached the
     * version goes back to approved and unsent and they are restored. Returns the addresses it could not reach.
     *
     * @param  list<string>  $emails
     */
    public static function send(StatementOfWork $sow, array $emails, User $by): array
    {
        $opportunity = $sow->opportunity;
        $chosen = self::choose($opportunity, $emails);
        if (! $chosen) {
            throw new LogicException('Choose at least one person HQ knows for this work');
        }
        if (self::vatChanged($sow)) {
            throw new LogicException('The VAT changed since HQ signed this version: take it back to draft and approve it again');
        }
        $pdf = SowPdf::render($sow);

        return self::whileMailing($sow, function () use ($sow, $opportunity, $chosen, $pdf, $by) {
            $replaced = self::claim($sow, $chosen, $by);
            if ($replaced === null) {
                throw new LogicException('Only an approved version that has not been sent can be sent');
            }
            $sow->refresh();

            $unsent = self::mail($sow, $chosen, $pdf);
            if (count($unsent) === count($chosen)) {
                DB::transaction(function () use ($sow, $replaced) {
                    self::lockOpportunity($sow->opportunity_id);
                    $sow->forceFill(['status' => StatementOfWork::DRAFT, 'sent_at' => null, 'recipients' => null, 'recipients_unsent' => null])->save();
                    foreach ($replaced as $id => $status) {
                        StatementOfWork::whereKey($id)->where('status', StatementOfWork::SUPERSEDED)->update(['status' => $status, 'updated_at' => now()]);
                    }
                });

                return $unsent;
            }
            $sow->refresh();
            self::afterSend($sow, $opportunity, count($chosen) - count($unsent));

            return $unsent;
        });
    }

    /**
     * The send's claim, under the locks: an approved, unsent draft becomes Sent with every chosen address on
     * recipients_unsent, and the earlier versions with the client (sent, or with changes asked for) are superseded at
     * once. Returns what it superseded ([id => previous status]), or null when the version can't be sent.
     */
    private static function claim(StatementOfWork $sow, array $chosen, User $by): ?array
    {
        return DB::transaction(function () use ($sow, $chosen, $by) {
            self::lockOpportunity($sow->opportunity_id);
            $locked = StatementOfWork::whereKey($sow->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== StatementOfWork::DRAFT || $locked->approved_at === null || $locked->sent_at !== null || self::signed($locked->opportunity_id)
                || self::vatChanged($locked)) {
                return null;
            }
            $replaced = StatementOfWork::where('opportunity_id', $locked->opportunity_id)->where('version', '<', $locked->version)
                ->whereIn('status', [StatementOfWork::SENT, StatementOfWork::CHANGES_REQUESTED])->pluck('status', 'id')->all();
            StatementOfWork::whereIn('id', array_keys($replaced))->update(['status' => StatementOfWork::SUPERSEDED, 'auth_otp' => null, 'otp_expires_at' => null, 'updated_at' => now()]);
            $locked->forceFill(['status' => StatementOfWork::SENT, 'sent_at' => now(), 'recipients' => $chosen, 'recipients_unsent' => array_column($chosen, 'email'),
                'updated_by' => $by->id])->save();

            return $replaced;
        });
    }

    /**
     * What follows a version reaching the client: earlier versions superseded (already done by the claim; again here
     * in case), the stage to SOW sent, and the history line once. Idempotent, so resend() finishes a send that was cut
     * short after its claim (a PHP timeout mid-loop).
     */
    private static function afterSend(StatementOfWork $sow, Opportunity $opportunity, int $reached): void
    {
        StatementOfWork::where('opportunity_id', $opportunity->id)->where('version', '<', $sow->version)
            ->whereIn('status', [StatementOfWork::SENT, StatementOfWork::CHANGES_REQUESTED])->update(['status' => StatementOfWork::SUPERSEDED, 'updated_at' => now()]);
        self::moveStage($opportunity, ['proposal_approved', 'sow'], 'sow_sent');
        $logged = DB::table('system_audit_logs')->where('target_table', 'opportunities')->where('target_id', $opportunity->id)->where('action', 'SOW_SENT')
            ->where('new_values', 'like', '%' . $sow->id . '%')->exists();
        OpportunityAccess::log('SOW_SENT', $logged
            ? sprintf('Sent version %d of the statement of work again to %d %s it hadn’t reached.', $sow->version, $reached, $reached === 1 ? 'person' : 'people')
            : sprintf('Sent version %d of the statement of work to %d %s.', $sow->version, $reached, $reached === 1 ? 'person' : 'people'),
            'opportunities', $opportunity->id, [], ['sow_id' => $sow->id, 'version' => $sow->version]);
    }

    /** The addresses a sent version didn't reach, again; under the mail lock. It also finishes what a cut-short send skipped. */
    public static function resend(StatementOfWork $sow, User $by): array
    {
        return self::whileMailing($sow, function () use ($sow) {
            $sow->refresh();
            $emails = $sow->status === StatementOfWork::SENT ? array_map('mb_strtolower', (array) $sow->recipients_unsent) : [];
            if (! $emails) {
                return [];
            }
            $recipients = array_values(array_filter((array) $sow->recipients, fn ($r) => in_array(mb_strtolower((string) ($r['email'] ?? '')), $emails, true)));
            $sow->forceFill(['recipients_unsent' => array_column($recipients, 'email') ?: null])->save();
            $unsent = $recipients ? self::mail($sow, $recipients, SowPdf::render($sow)) : [];
            if (count($unsent) < count($recipients)) {
                self::afterSend($sow->fresh(), $sow->opportunity, count($recipients) - count($unsent));
            }

            return $unsent;
        }, fn () => (array) $sow->fresh()->recipients_unsent);
    }

    /**
     * The email with the link (and the PDF) again for a version that is with the client and still valid, to the same
     * people or others from Proposals::people(), who join its recipients
     *
     * @param  list<string>  $emails
     */
    public static function sendAgain(StatementOfWork $sow, array $emails, User $by): array
    {
        $chosen = self::choose($sow->opportunity, $emails);
        if (! $chosen) {
            throw new LogicException('Choose at least one person HQ knows for this work');
        }
        $pdf = SowPdf::render($sow);

        return self::whileMailing($sow, function () use ($sow, $chosen, $pdf, $by) {
            $claimed = DB::transaction(function () use ($sow, $chosen, $by) {
                self::lockOpportunity($sow->opportunity_id);
                $locked = StatementOfWork::whereKey($sow->id)->lockForUpdate()->first();
                if (! $locked || $locked->status !== StatementOfWork::SENT || $locked->hasExpired()) {
                    return false;
                }
                $known = array_map(fn ($r) => mb_strtolower((string) ($r['email'] ?? '')), (array) $locked->recipients);
                $new = array_values(array_filter($chosen, fn ($r) => ! in_array($r['email'], $known, true)));
                $locked->forceFill([
                    'recipients' => [...(array) $locked->recipients, ...$new],
                    'recipients_unsent' => array_values(array_unique([...(array) $locked->recipients_unsent, ...array_column($chosen, 'email')])),
                    'updated_by' => $by->id,
                ])->save();

                return true;
            });
            if (! $claimed) {
                throw new LogicException('Only a version that is with the client and still valid can be sent again');
            }
            $sow->refresh();
            $unsent = self::mail($sow, $chosen, $pdf);
            $reached = count($chosen) - count($unsent);
            if ($reached > 0) {
                OpportunityAccess::log('SOW_SENT', sprintf('Sent the link to version %d of the statement of work again to %d %s.', $sow->version, $reached, $reached === 1 ? 'person' : 'people'),
                    'opportunities', $sow->opportunity_id, [], ['sow_id' => $sow->id, 'version' => $sow->version]);
            }

            return $unsent;
        });
    }

    /**
     * The client asks for changes from the portal (their message kept on the version): only on the version with them,
     * valid, and not once a version is signed. The stage goes back from SOW sent to SOW in progress; the lead is told.
     */
    public static function requestChanges(StatementOfWork $sow, array $person, string $message): bool
    {
        $done = DB::transaction(function () use ($sow, $person, $message) {
            self::lockOpportunity($sow->opportunity_id);
            $locked = StatementOfWork::whereKey($sow->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== StatementOfWork::SENT || $locked->hasExpired() || self::signed($locked->opportunity_id) || self::laterSent($locked)) {
                return false;
            }
            $locked->forceFill([
                'status' => StatementOfWork::CHANGES_REQUESTED,
                'changes_request' => Str::limit(trim($message), self::MAX_NOTE, '') ?: null,
                'changes_requested_by' => Str::limit(trim((string) $person['name']) . ' <' . mb_strtolower(trim((string) $person['email'])) . '>', 255, ''),
                'changes_requested_at' => now(),
                // The code a signer may have asked for no longer signs anything
                'auth_otp' => null,
                'otp_expires_at' => null,
            ])->save();

            return true;
        });
        if (! $done) {
            return false;
        }
        $sow->refresh();
        $opportunity = $sow->opportunity;
        OpportunityAccess::log('SOW_CHANGES_REQUESTED', "{$person['name']} asked for changes to version {$sow->version} of the statement of work.", 'opportunities', $opportunity->id,
            [], ['sow_id' => $sow->id, 'version' => $sow->version]);
        self::moveStage($opportunity, ['sow_sent'], 'sow');
        $locale = config('app.staff_locale', 'en');
        self::tellLead($opportunity, __('sow.notify.changes_title', [], $locale), __('sow.notify.changes', ['name' => $person['name'], 'title' => $opportunity->title, 'version' => $sow->version], $locale));

        return true;
    }

    /**
     * The version can be signed now: sent, valid, countersigned by HQ, no later version sent, nothing of this work
     * signed (no SOW, no legacy proposal) and the opportunity neither won nor lost
     */
    public static function awaitsSignature(StatementOfWork $sow): bool
    {
        $stage = Opportunity::whereKey($sow->opportunity_id)->value('stage');

        return $sow->status === StatementOfWork::SENT && ! $sow->hasExpired() && filled($sow->hq_signature_hash)
            && ! in_array($stage, Opportunity::CLOSED, true) && ! self::signed($sow->opportunity_id) && ! self::laterSent($sow)
            && ! Proposal::where('opportunity_id', $sow->opportunity_id)->whereIn('status', ['Authorized', 'Converted'])->exists();
    }

    /** A later version of the same opportunity has gone to the client: this one is replaced, whatever its status says */
    private static function laterSent(StatementOfWork $sow): bool
    {
        return StatementOfWork::where('opportunity_id', $sow->opportunity_id)->where('version', '>', $sow->version)->whereNotNull('sent_at')->exists();
    }

    /** An imported SOW (opportunities:import: signed on paper, no approved proposal in HQ): HQ shows it, never its fees or a PDF */
    public static function isImported(StatementOfWork $sow): bool
    {
        return $sow->proposal_id === null;
    }

    /**
     * The VAT on a version's fees (Vat::for's answer): fixed when HQ signs it (vat_rate, vat_applied, billing_entity_id),
     * and until then today's for the opportunity's billing company, so a draft follows the switch. Anything past draft
     * (sent, signed, imported, superseded) keeps what it recorded. The approved proposal's own VAT doesn't count: the
     * SOW is a later document.
     */
    public static function vat(StatementOfWork $sow): array
    {
        if ($sow->vat_applied === null && $sow->status === StatementOfWork::DRAFT) {
            return Vat::for($sow->opportunity?->organization, null, $sow->opportunity?->billing_entity_id);
        }

        return Vat::recorded($sow->vat_applied, $sow->vat_rate, $sow->billing_entity_id, $sow->opportunity?->organization);
    }

    /**
     * An approved version whose VAT or issuing company is no longer today's (the switch, or the opportunity's billing
     * company, changed after HQ signed): it goes back to draft before it is sent
     */
    public static function vatChanged(StatementOfWork $sow): bool
    {
        if ($sow->vat_applied === null || $sow->sent_at !== null) {
            return false;
        }
        $today = Vat::for($sow->opportunity?->organization, null, $sow->opportunity?->billing_entity_id);

        return (bool) $sow->vat_applied !== $today['applies'] || round((float) $sow->vat_rate, 2) !== round($today['rate'], 2)
            || $sow->billing_entity_id !== $today['entity_id'];
    }

    /**
     * A digest of what is signed: title, sections, milestones and the fees' figures. Both fingerprints (HQ's and the
     * client's) include it, so a change to the text after signing shows.
     */
    public static function contentHash(StatementOfWork $sow): string
    {
        $proposal = $sow->proposal;

        return hash('sha256', json_encode([
            'title' => (string) $sow->title,
            'sections' => array_values((array) $sow->sections),
            'milestones' => self::cleanMilestones((array) $sow->milestones),
            'fees' => [(string) $proposal?->currency, (string) $proposal?->subtotal, ($vat = self::vat($sow))['applies'], (string) $vat['rate'], (string) $vat['entity_id'], (string) $proposal?->payment_schedule_type,
                (string) ($proposal?->custom_deposit_percent ?? ''), self::lineDigest($proposal)],
        ], JSON_UNESCAPED_UNICODE));
    }

    private static function lineDigest(?Proposal $proposal): array
    {
        return $proposal ? Proposals::lines($proposal)->map(fn ($l) => [$l->description, $l->quantity, (string) $l->unit_price])->all() : [];
    }

    /**
     * The signer's details and drawn signature, kept with a new code (so a reload shows the code form): the code only
     * as its HMAC (SigningCodes::hash). A later request replaces them, and the code sent earlier then signs nothing.
     * The caller has checked that they are an active contact of the organisation and claimed the code's send limits.
     */
    public static function holdSignature(StatementOfWork $sow, array $signer, string $signature, string $code): bool
    {
        return (bool) StatementOfWork::whereKey($sow->id)->where('status', StatementOfWork::SENT)->update([
            'auth_otp' => SigningCodes::hash($code),
            'otp_expires_at' => now()->addMinutes(SigningCodes::MINUTES),
            'signed_by_name' => Str::limit(trim((string) $signer['name']), 150, ''),
            'signed_by_title' => Str::limit(trim((string) $signer['title']), 150, ''),
            'signed_by_email' => mb_strtolower(trim((string) $signer['email'])),
            'client_signature_b64' => $signature,
            'updated_at' => now(),
        ]);
    }

    /**
     * The client signs with the code. In one transaction, claimed under the locks so it happens once, and only if
     * $code is the one issued with the signature now held (its HMAC, not expired) and that signer is still an active
     * contact: the version is signed (a fingerprint over the signed text, the signer, the IP and the time) and every
     * other open version superseded; the organisation becomes an Active Client; the project is created (Scoping, led
     * by the opportunity's lead: it holds the deposit invoice, and Create project writes its plan later) with the
     * approved proposal's lines as its budget; the deposit invoice is raised from the approved proposal's price and
     * schedule; the opportunity is Won. Then the signer gets portal access, HQ is told, SowSigned fires and the
     * project draft is queued. False when the version can no longer be signed or the code doesn't match.
     */
    public static function sign(StatementOfWork $sow, string $code, ?string $ip): bool
    {
        $timestamp = now();
        $result = DB::transaction(function () use ($sow, $code, $ip, $timestamp) {
            self::lockOpportunity($sow->opportunity_id);
            $locked = StatementOfWork::whereKey($sow->id)->lockForUpdate()->first();
            if (! $locked || ! self::awaitsSignature($locked) || ! $locked->signed_by_email
                || ! SigningCodes::matches($locked->auth_otp, $locked->otp_expires_at, $code)
                || ! Proposals::isApprover($locked->opportunity, (string) $locked->signed_by_email)) {
                return null;
            }
            $opportunity = Opportunity::with('organization')->find($locked->opportunity_id);
            $proposal = Proposal::whereKey($locked->proposal_id)->where('status', Proposal::CLIENT_APPROVED)->first();
            $organization = DB::table('organizations')->where('id', $opportunity?->organization_id)->first();
            if (! $opportunity || ! $proposal || ! $organization) {
                return null;
            }

            $locked->forceFill([
                'status' => StatementOfWork::SIGNED,
                'client_signature_hash' => 'SHA256-CLIENT-' . strtoupper(hash('sha256', $locked->id . self::contentHash($locked) . $locked->signed_by_name . $locked->signed_by_email . $ip . $timestamp)),
                'client_ip_address' => $ip,
                'signed_at' => $timestamp,
                'auth_otp' => null,
                'otp_expires_at' => null,
            ])->save();
            StatementOfWork::where('opportunity_id', $locked->opportunity_id)->whereKeyNot($locked->id)
                ->whereIn('status', [StatementOfWork::DRAFT, StatementOfWork::SENT, StatementOfWork::CHANGES_REQUESTED])->update(['status' => StatementOfWork::SUPERSEDED, 'updated_at' => $timestamp]);

            // The lead becomes a client (Active Client; the first signature sets since when and how: docs/LEADS.md)
            LeadStatus::markClientFromSigning($organization->id, 'sow', $locked->reference, $locked->id, $timestamp);

            // The project, to hold the deposit invoice (invoices.project_id is required); its plan comes with Create project
            $projectId = (string) Str::uuid();
            DB::table('projects')->insert([
                'id' => $projectId,
                'organization_id' => $organization->id,
                'opportunity_id' => $opportunity->id,
                'title' => Str::limit((string) ($locked->title ?: $proposal->title), 255, ''),
                'description' => "Created from signed statement of work {$locked->reference}.",
                'status' => 'Scoping',
                'currency' => $proposal->currency,
                'total_budget' => self::contractTotal($locked), // before VAT: Finance adds VAT per invoice
                'lead_user_id' => $opportunity->lead_user_id ?: $locked->approved_by,
                'created_by' => $locked->approved_by,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            // The approved proposal's lines become the project's budget, as the legacy gateway does (Finance › Project budgets)
            foreach (Proposals::lines($proposal) as $line) {
                DB::table('project_budgets')->insert([
                    'id' => (string) Str::uuid(), 'project_id' => $projectId, 'description' => $line->description, 'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price, 'total_allocated' => $line->total_price, 'created_at' => $timestamp,
                ]);
            }
            $invoice = DepositInvoice::raise($projectId, $organization, [
                'subtotal' => $proposal->subtotal,
                'billing_entity_id' => $locked->billing_entity_id, // the company that signed it bills the deposit
                'currency' => $proposal->currency,
                'payment_schedule_type' => $proposal->payment_schedule_type,
                'custom_deposit_percent' => $proposal->custom_deposit_percent ?? null,
                'title' => $locked->title ?: $proposal->title,
                'reference' => $locked->reference,
            ], $locked->approved_by, $timestamp);
            $locked->forceFill(['deposit_invoice_id' => $invoice['id'], 'project_id' => $projectId])->save();

            $old = $opportunity->stage;
            $opportunity->forceFill(['stage' => 'won', 'closed_at' => $timestamp, 'lost_reason' => null])->save();
            OpportunityAccess::log('SOW_SIGNED', "{$locked->signed_by_name} ({$locked->signed_by_title}) signed version {$locked->version} of the statement of work; deposit invoice {$invoice['number']} raised.",
                'opportunities', $opportunity->id, ['stage' => $old], ['stage' => 'won', 'sow_id' => $locked->id, 'invoice' => $invoice['number'], 'project_id' => $projectId]);

            return ['sow' => $locked, 'opportunity' => $opportunity, 'organization' => $organization, 'invoice' => $invoice, 'project_id' => $projectId];
        });
        if (! $result) {
            return false;
        }
        $sow->refresh();
        $opportunity = $result['opportunity'];

        SignerOnboarding::welcome($result['organization'], (string) $sow->signed_by_email, (string) ($sow->title ?: $sow->reference), $timestamp);
        // The deposit invoice and its PDF to the client's billing contacts (docs/QA-PORTAL.md D11); never undoes the signing
        try {
            \App\Support\Finance\InvoiceMailer::send($result['invoice']['id']);
        } catch (Throwable $e) {
            Log::error('Deposit invoice email not prepared', ['sow_id' => $sow->id, 'message' => $e->getMessage()]);
        }
        self::tellHq($opportunity, $sow, $result['invoice']['number']);
        SowSigned::dispatch($sow->id, $opportunity->id, $result['project_id']);
        ProjectDrafts::queue($sow);

        return true;
    }

    /**
     * The contract's price before VAT: the approved proposal's subtotal. It is the project's total_budget and what the
     * milestones' amounts share out, because Finance adds VAT on each invoice it raises from a milestone (a total with
     * VAT in it would be taxed twice); the deposit invoice adds its own VAT too.
     */
    public static function contractTotal(StatementOfWork $sow): float
    {
        return (float) ($sow->proposal?->subtotal ?? 0);
    }

    /** Each milestone's amount: weight × total, the rounding on the last one with a weight so they add up to the total exactly */
    public static function amounts(array $milestones, float $total): array
    {
        $amounts = array_map(fn ($m) => round($total * (float) ($m['weight'] ?? 0) / 100, 2), $milestones);
        $last = null;
        foreach ($milestones as $i => $m) {
            if ((float) ($m['weight'] ?? 0) > 0) {
                $last = $i;
            }
        }
        if ($last !== null && abs(self::weightTotal($milestones) - 100) <= 0.001) {
            $amounts[$last] = round($amounts[$last] + round($total - array_sum($amounts), 2), 2);
        }

        return $amounts;
    }

    public static function weightTotal(array $milestones): float
    {
        return round(array_sum(array_map(fn ($m) => (float) ($m['weight'] ?? 0), $milestones)), 2);
    }

    /**
     * Milestones as HQ keeps them: a title, what it delivers and how it is accepted, weeks (1–104) and a weight (0–100,
     * two decimals), a stable key; at most MAX_MILESTONES. One without a title is dropped unless editing.
     */
    public static function cleanMilestones(array $raw, bool $keepEmpty = false): array
    {
        $out = [];
        $keys = [];
        foreach (array_values($raw) as $i => $m) {
            if (! is_array($m) || count($out) >= self::MAX_MILESTONES) {
                continue;
            }
            $title = Str::limit(trim((string) preg_replace('/\s+/u', ' ', (string) ($m['title'] ?? ''))), 140, '');
            if ($title === '' && ! $keepEmpty) {
                continue;
            }
            $key = Str::limit(preg_replace('/[^a-z0-9_]/', '', Str::snake(Str::ascii((string) ($m['key'] ?? '')))), 40, '') ?: 'm' . ($i + 1);
            for ($n = 2, $unique = $key; isset($keys[$unique]); $n++) {
                $unique = "{$key}_{$n}";
            }
            $keys[$unique] = true;
            $out[] = [
                'key' => $unique,
                'title' => $title,
                'description' => Str::limit(trim(str_replace("\r", '', (string) ($m['description'] ?? ''))), self::MAX_NOTE, ''),
                'weeks' => max(1, min(104, (int) ($m['weeks'] ?? 1))),
                'weight' => max(0, min(100, round((float) ($m['weight'] ?? 0), 2))),
            ];
        }

        return $out;
    }

    /** The version as plain text, for the AI (the next version's draft, the project draft) */
    public static function text(StatementOfWork $sow): string
    {
        $out = ['Title: ' . $sow->title];
        foreach ((array) $sow->sections as $section) {
            $out[] = '';
            $out[] = '[' . ($section['key'] ?? '') . '] ' . ($section['title'] ?? '');
            $out[] = trim((string) ($section['body'] ?? ''));
        }
        $milestones = (array) $sow->milestones;
        if ($milestones) {
            $out[] = '';
            $out[] = '[milestones] Milestones';
            foreach (array_values($milestones) as $i => $m) {
                $out[] = sprintf('%d. %s (%d weeks, %s%% of the contract): %s', $i + 1, $m['title'] ?? '', (int) ($m['weeks'] ?? 0), rtrim(rtrim(number_format((float) ($m['weight'] ?? 0), 2, '.', ''), '0'), '.'), trim((string) ($m['description'] ?? '')));
            }
        }

        return implode("\n", $out);
    }

    /** "SOW-20261105-482": the reference every version shares */
    private static function reference(): string
    {
        do {
            $ref = 'SOW-' . now('Africa/Lagos')->format('Ymd') . '-' . random_int(100, 999);
        } while (StatementOfWork::withTrashed()->where('reference', $ref)->exists());

        return $ref;
    }

    /** The parties and the price for the AI (it never writes a figure), then a later version's predecessor and the client's request */
    private static function draftMaterial(StatementOfWork $sow): string
    {
        $proposal = $sow->proposal;
        $out = [
            'THE PARTIES: ' . (self::providerName($sow) ?? 'JBS Praxis') . ' (“JBS Praxis”) and ' . ($sow->opportunity?->organization?->name ?? 'the client') . ' (“the client”).',
            'THE PROPOSAL THE CLIENT APPROVED: ' . ($proposal?->proposal_ref ?? '') . ' version ' . ($proposal?->version ?? 1)
                . ($proposal?->client_approved_name ? ', approved by ' . $proposal->client_approved_name . ' (' . $proposal->client_approved_title . ')' : '') . '.',
            'THE FEES: HQ adds the fees and the payment schedule from that proposal (' . ($proposal?->currency ?? '') . ', payment schedule '
                . str_replace('_', '/', (string) $proposal?->payment_schedule_type) . ', the first share due on signing). Never write a price, rate, total or percentage of the fees in the text.',
        ];
        $previous = $sow->previousVersion;
        if ($previous) {
            $out[] = '';
            $out[] = "THE VERSION THE CLIENT HAS (version {$previous->version}): keep what they didn’t question, the section keys and the milestones’ keys.";
            $out[] = self::text($previous);
            if (filled($previous->changes_request)) {
                $out[] = '';
                $out[] = 'WHAT THE CLIENT ASKED TO CHANGE (answer it in the new version):';
                $out[] = $previous->changes_request;
            }
        }

        return implode("\n", $out);
    }

    /** The AI's draft becomes the version: title, sections, milestones */
    private static function applyDraft(StatementOfWork $locked, AiRun $run): bool
    {
        $sections = Proposals::cleanSections((array) ($run->output['sections'] ?? []));
        if (! $sections) {
            $run->forceFill(['status' => 'failed', 'error' => 'ai.errors.invalid'])->save();

            return false;
        }
        $milestones = self::cleanMilestones(array_map(fn ($m) => [
            'key' => $m['key'] ?? null, 'title' => $m['title'] ?? '', 'description' => $m['deliverables'] ?? '', 'weeks' => $m['weeks'] ?? 1, 'weight' => $m['weight'] ?? 0,
        ], array_filter((array) ($run->output['milestones'] ?? []), 'is_array')));
        $title = Str::limit(trim((string) ($run->output['title'] ?? '')), self::MAX_TITLE, '');
        $locked->forceFill([
            'title' => $title !== '' ? $title : $locked->title,
            'sections' => $sections,
            'milestones' => $milestones ?: (array) $locked->milestones,
            'ai_run_id' => null,
            'updated_by' => $run->requested_by,
        ])->save();

        return true;
    }

    /** One branded email per person with the PDF; each address crossed off recipients_unsent once its email has gone */
    private static function mail(StatementOfWork $sow, array $recipients, string $pdf): array
    {
        $unsent = [];
        foreach ($recipients as $r) {
            try {
                Mail::to($r['email'], $r['name'] ?? null)->locale($sow->opportunity->language)
                    ->send(new SowMail($sow, $r['client_user_id'] ?? null, self::firstName($r), $pdf));
            } catch (Throwable $e) {
                Log::warning('Sows: the email could not be sent', ['sow' => $sow->id, 'error' => $e->getMessage()]);
                $unsent[] = $r['email'];

                continue;
            }
            DB::transaction(function () use ($sow, $r) {
                $locked = StatementOfWork::whereKey($sow->id)->lockForUpdate()->first();
                $left = array_values(array_filter((array) $locked?->recipients_unsent, fn ($e) => mb_strtolower((string) $e) !== mb_strtolower($r['email'])));
                $locked?->forceFill(['recipients_unsent' => $left ?: null])->save();
            });
        }

        return $unsent;
    }

    private static function choose(Opportunity $opportunity, array $emails): array
    {
        $people = Proposals::people($opportunity);

        return array_slice(array_values(array_filter(array_map(fn ($e) => $people[mb_strtolower(trim((string) $e))] ?? null,
            array_unique(array_map(fn ($e) => mb_strtolower(trim((string) $e)), $emails))))), 0, self::MAX_RECIPIENTS);
    }

    /** A send, a resend and a "send again" of one version never mail at once: an atomic cache lock */
    private static function whileMailing(StatementOfWork $sow, callable $send, ?callable $busy = null): array
    {
        $lock = Cache::lock('sow-mail:' . $sow->id, 600);
        if (! $lock->get()) {
            return $busy ? $busy() : throw new LogicException('This version is being sent already');
        }
        try {
            return $send();
        } finally {
            $lock->release();
        }
    }

    private static function lockOpportunity(string $opportunityId): void
    {
        Opportunity::whereKey($opportunityId)->lockForUpdate()->first();
    }

    private static function firstName(array $recipient): ?string
    {
        $name = trim((string) ($recipient['name'] ?? ''));

        return $name === '' || str_contains($name, '@') ? null : explode(' ', $name)[0];
    }

    /** Moves the opportunity forward from one of $from (never back from won, lost or on hold) */
    private static function moveStage(Opportunity $opportunity, array $from, string $to): void
    {
        $opportunity->refresh();
        if (! in_array($opportunity->stage, $from, true) || $opportunity->stage === $to) {
            return;
        }
        $old = $opportunity->stage;
        $opportunity->forceFill(['stage' => $to, 'updated_by' => Auth::guard('web')->id()])->save();
        OpportunityAccess::log('OPPORTUNITY_STAGE_CHANGED', "Moved “{$opportunity->title}” from {$old} to {$to}.", 'opportunities', $opportunity->id, ['stage' => $old], ['stage' => $to]);
    }

    /** A bell for the opportunity's lead, opening $tab */
    public static function tellLead(Opportunity $opportunity, string $title, string $message, string $tab = 'sow'): void
    {
        $lead = $opportunity->lead_user_id ? User::where('id', $opportunity->lead_user_id)->where('status', 'Active')->first() : null;
        if (! $lead) {
            return;
        }
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $lead->id,
            'type' => 'System',
            'title' => Str::limit($title, 150, '…'),
            'message' => $message,
            'link_url' => route('hq.crm.opportunity', ['opportunity' => $opportunity->id, 'tab' => $tab]),
            'is_read' => 0,
            'created_at' => now(),
        ]);
    }

    /** Signed: the lead, the sales team (deal won) and finance (deposit invoice) get a bell, once each */
    private static function tellHq(Opportunity $opportunity, StatementOfWork $sow, string $invoiceNumber): void
    {
        $locale = config('app.staff_locale', 'en');
        $title = __('sow.notify.signed_title', ['title' => $opportunity->title], $locale);
        $message = __('sow.notify.signed', ['name' => $sow->signed_by_name, 'company' => $opportunity->organization?->name, 'version' => $sow->version, 'invoice' => $invoiceNumber], $locale);
        self::tellLead($opportunity, $title, $message, 'project');
        $others = array_diff(array_unique(array_merge(Access::staffWith('crm.edit'), Access::staffWith('finance.approve'))), [$opportunity->lead_user_id]);
        $rows = array_map(fn ($id) => [
            // No link: not everyone told may open Finance or the opportunity (as the legacy gateway's notice)
            'id' => (string) Str::uuid(), 'user_id' => $id, 'type' => 'Project', 'title' => Str::limit($title, 150, '…'), 'message' => $message,
            'link_url' => null, 'is_read' => 0, 'created_at' => now(),
        ], array_values($others));
        if ($rows) {
            DB::table('notifications')->insert($rows);
        }
    }
}
