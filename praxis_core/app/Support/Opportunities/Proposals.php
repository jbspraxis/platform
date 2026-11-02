<?php

namespace App\Support\Opportunities;

use App\Events\ProposalApprovedByClient;
use App\Events\ProposalChangesRequested;
use App\Events\ProposalSent;
use App\Mail\ProposalMail;
use App\Models\AiRun;
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\ProposalComment;
use App\Models\ClientUser;
use App\Models\User;
use App\Support\Access;
use App\Support\Ai\Ai;
use App\Support\Finance\Vat;
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
 * An opportunity's proposal, version by version (docs/OPPORTUNITIES.md section 4, row 4; owner decisions 7–9).
 * The AI drafts a version in the background (purpose proposal, Claude Sonnet) from the opportunity: meetings with
 * their full transcripts, notes, the questionnaire's answers and the cost of poor quality. It suggests line items
 * by their code in the financial dictionary (mdm_financial_dictionary); the prices come from the dictionary and
 * people adjust them. A version is edited while it is a draft, approved by the owner or the opportunity's lead,
 * then sent: a branded email with the PDF and a signed link to the portal, where the client comments by section,
 * approves it (name, title, email) or asks for changes. Changes lead to the next version, with "what changed"
 * (purpose proposal_changes, Claude Haiku). The binding signature is on the SOW (PR 5).
 *
 * Every version is a proposals row with signing_mode approve; legacy proposals (signing_mode legacy) keep the
 * one-time code gateway. AI results are applied by sync(), which the tab calls on every load and while it polls,
 * with the row locked, so each is applied once whoever is looking. Callers check the permission first.
 */
final class Proposals
{
    public const MAX_SECTIONS = 20;

    /** Characters in a section's text, a title, the lead's instructions, a client comment */
    public const MAX_BODY = 20000;

    public const MAX_TITLE = 200;

    public const MAX_NOTE = 2000;

    public const MAX_COMMENT = 2000;

    public const MAX_LINES = 40;

    /** Client comments a version takes in all, and from the portal in an hour */
    public const MAX_CLIENT_COMMENTS = 100;

    public const COMMENTS_PER_HOUR = 10;

    /** People one send can reach */
    public const MAX_RECIPIENTS = 20;

    public const VALIDITY_DAYS = 30;

    public const SCHEDULES = ['40_30_30', '50_50', '20_20_40_20', '100'];

    /** The section key of comments on the proposal as a whole */
    public const GENERAL = 'general';

    /** The sections a blank proposal starts with, in this order (titles from lang proposals.sections) */
    public const BLANK_SECTIONS = ['executive_summary', 'approach', 'scope', 'team', 'timeline'];

    /** Change the proposal: the right to manage opportunities, or being on this one's team, while it is open */
    public static function canEdit(?User $user, Opportunity $opportunity): bool
    {
        return OpportunityAccess::canManage($user, $opportunity) && ! $opportunity->isClosed();
    }

    /** The AI's draft retells the meetings' transcripts: only for someone who may read them (audit of PRs 2 and 3) */
    public static function canDraftWithAi(?User $user, Opportunity $opportunity): bool
    {
        return self::canEdit($user, $opportunity) && OpportunityAccess::canReadTranscripts($user, $opportunity);
    }

    /** Approve a version before it goes out: the owner or the opportunity's lead (decision 7) */
    public static function canApprove(?User $user, Opportunity $opportunity): bool
    {
        return OpportunityAccess::canApprove($user, $opportunity) && ! $opportunity->isClosed();
    }

    /** The opportunity's v2 proposals, latest version first */
    public static function versions(Opportunity $opportunity): Collection
    {
        return Proposal::where('opportunity_id', $opportunity->id)->where('signing_mode', 'approve')->orderByDesc('version')->get();
    }

    /** Proposals from before PR 4, signed with the one-time code */
    public static function legacy(Opportunity $opportunity): Collection
    {
        return Proposal::where('opportunity_id', $opportunity->id)->where('signing_mode', 'legacy')->orderByDesc('created_at')
            ->get(['id', 'opportunity_id', 'proposal_ref', 'title', 'status', 'total_value', 'currency', 'created_at']);
    }

    /**
     * Version 1, blank (the standard sections, empty, for the lead to write) or ready for the AI. Returns the
     * existing first version when there is one: a second click makes nothing.
     */
    public static function start(Opportunity $opportunity, string $currency, User $by): Proposal
    {
        return DB::transaction(function () use ($opportunity, $currency, $by) {
            Opportunity::whereKey($opportunity->id)->lockForUpdate()->first();
            if ($existing = self::versions($opportunity)->first()) {
                return $existing;
            }
            $vat = Vat::for($opportunity->organization, null, $opportunity->billing_entity_id);
            $proposal = Proposal::create([
                'organization_id' => $opportunity->organization_id,
                'opportunity_id' => $opportunity->id,
                'proposal_ref' => self::reference(),
                'version' => 1,
                'title' => Str::limit($opportunity->title, self::MAX_TITLE, ''),
                'sections' => array_map(fn ($key) => ['key' => $key, 'title' => __('proposals.sections.' . $key, [], $opportunity->language), 'body' => ''], self::BLANK_SECTIONS),
                'validity_days' => self::VALIDITY_DAYS,
                'subtotal' => 0,
                'vat_rate' => $vat['rate'],
                'vat_applied' => $vat['applies'],
                'billing_entity_id' => $vat['entity_id'],
                'total_value' => 0,
                'currency' => self::currencies()->contains($currency) ? $currency : 'USD',
                'payment_schedule_type' => self::SCHEDULES[0],
                'status' => Proposal::DRAFT,
                'signing_mode' => 'approve',
                'created_by' => $by->id,
                'updated_by' => $by->id,
            ]);
            OpportunityAccess::log('PROPOSAL_STARTED', "Started the proposal for “{$opportunity->title}” ({$proposal->proposal_ref}).", 'opportunities', $opportunity->id,
                [], ['proposal_id' => $proposal->id, 'version' => 1]);

            return $proposal;
        });
    }

    /**
     * Asks the AI for this version's text and line items, in the background. Throws AiUnavailable (no key, over the
     * budget) before anything is spent. A version already being drafted is left as it is (one draft per click).
     * The draft replaces the version's title, sections and line items when it arrives.
     */
    public static function draft(Proposal $proposal, string $instructions, User $by): bool
    {
        self::sync($proposal);
        if (! $proposal->isEditable() || self::running($proposal->ai_run_id)) {
            return false;
        }
        $opportunity = $proposal->opportunity;

        $run = Ai::queue('proposal', $proposal, [
            // Full transcripts once, here (6.2, cost rule 4); later documents read the notes and this proposal
            'context' => OpportunityContext::for($opportunity, include: [...OpportunityContext::DEFAULT, 'transcripts']),
            'instructions' => Str::limit(trim($instructions), self::MAX_NOTE, ''),
            'material' => self::draftMaterial($proposal, self::budgetGiven($instructions)),
            'language' => $opportunity->language,
        ]);
        $proposal->forceFill(['ai_run_id' => $run->id, 'updated_by' => $by->id])->save();

        OpportunityAccess::log('PROPOSAL_DRAFT_REQUESTED', "Asked the AI to draft version {$proposal->version} of the proposal for “{$opportunity->title}”.",
            'opportunities', $opportunity->id, [], ['proposal_id' => $proposal->id, 'version' => $proposal->version]);
        self::moveStage($opportunity, ['discovery', 'questionnaire'], 'proposal');
        self::sync($proposal);

        return true;
    }

    /** Asks the AI to say what changed since the previous version (for the client), in the background */
    public static function summariseChanges(Proposal $proposal, User $by): bool
    {
        self::sync($proposal);
        $previous = $proposal->previousVersion;
        if (! $previous || ! $proposal->isEditable() || self::running($proposal->changes_run_id)) {
            return false;
        }

        $run = Ai::queue('proposal_changes', $proposal, [
            'material' => "THE VERSION THE CLIENT HAS (version {$previous->version}):\n\n" . self::text($previous)
                . "\n\nTHE NEW VERSION (version {$proposal->version}):\n\n" . self::text($proposal),
            'language' => $proposal->opportunity->language,
        ]);
        $proposal->forceFill(['changes_run_id' => $run->id, 'updated_by' => $by->id])->save();
        self::sync($proposal);

        return true;
    }

    /**
     * Applies finished AI jobs: the draft (title, sections, line items) and what changed. True when it wrote
     * something, so a screen holding the version's fields reloads them. A failed job stays linked, so the tab can
     * say why, until it is dismissed.
     */
    public static function sync(Proposal $proposal): bool
    {
        $applied = false;
        foreach (['ai_run_id' => 'applyDraft', 'changes_run_id' => 'applyChanges'] as $column => $apply) {
            $runId = $proposal->{$column};
            if (! $runId || ! AiRun::whereKey($runId)->where('status', 'done')->exists()) {
                continue;
            }
            $applied = DB::transaction(function () use ($proposal, $column, $runId, $apply) {
                $locked = Proposal::whereKey($proposal->id)->lockForUpdate()->first();
                $run = AiRun::find($runId);
                if (! $locked || ! $run || $locked->{$column} !== $runId) {
                    return false;
                }
                if (! $locked->isEditable()) {
                    $locked->forceFill([$column => null])->save();

                    return false;
                }

                return self::$apply($locked, $run);
            }) || $applied;
        }
        if ($applied) {
            $proposal->refresh();
        }

        return $applied;
    }

    /**
     * A job finished (FileProposalAi on AiRunFinished, whoever ran it): it is filed at once, as the tab would, and a
     * whole draft rings the lead: "Proposal drafted" or "Proposal not drafted" with why, opening the Proposals tab. A
     * draft that ran while the page waited (the version wasn't linked to it yet) and "what changed" ring no one.
     */
    public static function fileAiOutput(AiRun $run): void
    {
        $column = ['proposal' => 'ai_run_id', 'proposal_changes' => 'changes_run_id'][$run->purpose] ?? null;
        if (! $column || $run->subject_type !== 'proposal' || ! $run->isFinished()) {
            return;
        }
        $proposal = Proposal::find($run->subject_id);
        if (! $proposal || $proposal->{$column} !== $run->id) {
            return;
        }

        self::sync($proposal);
        if ($column !== 'ai_run_id') {
            return;
        }
        $opportunity = $proposal->opportunity;
        $locale = config('app.staff_locale', 'en');
        if ($proposal->ai_run_id === null) {
            self::tellLead($opportunity, __('proposals.notify.ready_title', ['title' => $opportunity->title], $locale),
                __('proposals.notify.ready', ['n' => $proposal->version], $locale));
        } elseif (($run = $run->fresh())->status === 'failed') { // failed in the job, or nothing usable came back
            self::tellLead($opportunity, __('proposals.notify.failed_title', ['title' => $opportunity->title], $locale),
                __('proposals.notify.failed', ['message' => $run->errorMessage()], $locale));
        }
    }

    /** Clears a failed job's message from the version */
    public static function dismissFailure(Proposal $proposal, string $column): void
    {
        if (in_array($column, ['ai_run_id', 'changes_run_id'], true) && $proposal->{$column} && ! self::running($proposal->{$column})) {
            $proposal->forceFill([$column => null])->save();
        }
    }

    /**
     * Saves the lead's edits to a draft nobody has approved, while the AI isn't rewriting it. $data: title,
     * sections ([key, title, body]), lines ([code, description, quantity, unit_price]), currency,
     * payment_schedule_type, validity_days, changes_summary. False when the version can't be changed now. Its VAT
     * isn't the lead's to set: it follows the issuing company's switch until it is sent (followVat()).
     */
    public static function save(Proposal $proposal, array $data, User $by): bool
    {
        return DB::transaction(function () use ($proposal, $data, $by) {
            $locked = Proposal::whereKey($proposal->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->isEditable() || self::running($locked->ai_run_id)) {
                return false;
            }
            $sections = self::cleanSections((array) ($data['sections'] ?? []), keepEmpty: true);
            $lines = self::cleanLines((array) ($data['lines'] ?? []));
            $currency = self::currencies()->contains($data['currency'] ?? null) ? $data['currency'] : $locked->currency;

            $locked->forceFill([
                'title' => Str::limit(trim((string) ($data['title'] ?? $locked->title)), self::MAX_TITLE, '') ?: $locked->title,
                'sections' => $sections,
                'currency' => $currency,
                'payment_schedule_type' => in_array($data['payment_schedule_type'] ?? null, self::SCHEDULES, true) ? $data['payment_schedule_type'] : $locked->payment_schedule_type,
                'validity_days' => max(7, min(90, (int) ($data['validity_days'] ?? $locked->validity_days))),
                // While the AI writes "what changed", the text it files wins over a page that hasn't seen it yet
                'changes_summary' => match (true) {
                    $locked->version < 2 => null,
                    self::running($locked->changes_run_id) => $locked->changes_summary,
                    default => Str::limit(trim((string) ($data['changes_summary'] ?? '')), self::MAX_NOTE, '') ?: null,
                },
                'updated_by' => $by->id,
            ]);
            self::writeLines($locked, $lines);
            $proposal->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    /**
     * The next version, from the latest one the client has: its text, line items and terms copied for the lead to
     * change. Only one draft at a time, and not once the client approved.
     */
    public static function newVersion(Opportunity $opportunity, User $by): ?Proposal
    {
        return DB::transaction(function () use ($opportunity, $by) {
            self::lockOpportunity($opportunity->id);
            $latest = self::versions($opportunity)->first();
            if (! $latest || ! in_array($latest->status, [Proposal::SENT, Proposal::CHANGES_REQUESTED], true) || self::clientApproved($opportunity->id)) {
                return null;
            }
            $next = $latest->replicate(['ai_run_id', 'changes_run_id', 'approved_by', 'approved_at', 'sent_at', 'recipients', 'recipients_unsent',
                'client_approved_name', 'client_approved_title', 'client_approved_email', 'client_approved_at', 'changes_summary', 'deleted_by']);
            $next->forceFill([
                'version' => $latest->version + 1,
                'previous_version_id' => $latest->id,
                'status' => Proposal::DRAFT,
                'created_by' => $by->id,
                'updated_by' => $by->id,
            ])->save();
            self::writeLines($next, self::lines($latest)->map(fn ($l) => (array) $l)->all());

            OpportunityAccess::log('PROPOSAL_VERSION_STARTED', "Started version {$next->version} of the proposal for “{$opportunity->title}”.", 'opportunities', $opportunity->id,
                [], ['proposal_id' => $next->id, 'version' => $next->version]);

            return $next;
        });
    }

    /**
     * Sets a draft version aside (soft delete, kept for the record); nothing about it reached the client. Claimed under
     * the lock; an approved draft only by someone who may approve ($mayApprove: canApprove).
     */
    public static function discard(Proposal $proposal, User $by, bool $mayApprove = false): bool
    {
        $done = DB::transaction(function () use ($proposal, $by, $mayApprove) {
            self::lockOpportunity($proposal->opportunity_id);
            $locked = Proposal::whereKey($proposal->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== Proposal::DRAFT || $locked->sent_at !== null || ($locked->approved_at !== null && ! $mayApprove)) {
                return false;
            }
            $locked->forceFill(['deleted_by' => $by->id])->save();
            $locked->delete();

            return true;
        });
        if (! $done) {
            return false;
        }
        OpportunityAccess::log('PROPOSAL_VERSION_DISCARDED', "Discarded the draft of version {$proposal->version} of the proposal.", 'opportunities', $proposal->opportunity_id,
            ['proposal_id' => $proposal->id, 'version' => $proposal->version], []);

        return true;
    }

    /**
     * Approves a draft for sending (decision 7). Returns null when done, else why not: client_approved (the client
     * already approved a version of this opportunity), running (the AI is still writing), empty (no title or no
     * section with text), changes (a later version says what changed), state.
     */
    public static function approve(Proposal $proposal, User $by): ?string
    {
        self::sync($proposal);
        $why = DB::transaction(function () use ($proposal, $by) {
            self::lockOpportunity($proposal->opportunity_id);
            $locked = Proposal::whereKey($proposal->id)->lockForUpdate()->first();
            if ($locked && self::clientApproved($locked->opportunity_id)) {
                return 'client_approved';
            }
            if (! $locked || ! $locked->isEditable()) {
                return 'state';
            }
            if (self::running($locked->ai_run_id) || self::running($locked->changes_run_id)) {
                return 'running';
            }
            $written = collect((array) $locked->sections)->filter(fn ($s) => trim((string) ($s['body'] ?? '')) !== '');
            if (trim((string) $locked->title) === '' || $written->isEmpty()) {
                return 'empty';
            }
            if ($locked->version > 1 && trim((string) $locked->changes_summary) === '') {
                return 'changes';
            }
            self::followVat($locked);
            $locked->forceFill(['approved_by' => $by->id, 'approved_at' => now(), 'updated_by' => $by->id])->save();
            $proposal->setRawAttributes($locked->getAttributes(), true);

            return null;
        });
        if ($why === null) {
            OpportunityAccess::log('PROPOSAL_APPROVED', "Approved version {$proposal->version} of the proposal for sending.", 'opportunities', $proposal->opportunity_id,
                [], ['proposal_id' => $proposal->id, 'version' => $proposal->version]);
        }

        return $why;
    }

    /** An approved version that hasn't been sent goes back to draft, to change it */
    public static function reopen(Proposal $proposal, User $by): bool
    {
        $done = (bool) Proposal::whereKey($proposal->id)->where('status', Proposal::DRAFT)->whereNotNull('approved_at')->whereNull('sent_at')
            ->update(['approved_by' => null, 'approved_at' => null, 'updated_by' => $by->id, 'updated_at' => now()]);
        if ($done) {
            $proposal->refresh();
            OpportunityAccess::log('PROPOSAL_REOPENED', "Took version {$proposal->version} of the proposal back to draft.", 'opportunities', $proposal->opportunity_id,
                ['approved' => true], ['proposal_id' => $proposal->id, 'approved' => false]);
        }

        return $done;
    }

    /**
     * Who a proposal can be sent to, and who may read and comment on it in the portal: the organisation's active
     * contacts and the people invited to the opportunity's client meetings. Keyed by lower-case email.
     *
     * @return array<string, array{email: string, name: string, client_user_id: ?string}>
     */
    public static function people(Opportunity $opportunity): array
    {
        $people = [];
        $contacts = DB::table('client_users')->where('organization_id', $opportunity->organization_id)->where('status', 'Active')->whereNull('deleted_at')
            ->whereNotNull('email')->orderByDesc('is_primary_client')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email']);
        foreach ($contacts as $c) {
            $email = mb_strtolower(trim((string) $c->email));
            if ($email !== '' && ! isset($people[$email])) {
                $people[$email] = ['email' => $email, 'name' => trim($c->first_name . ' ' . $c->last_name) ?: $email, 'client_user_id' => $c->id];
            }
        }
        $attendees = DB::table('meeting_attendees')->join('meetings', 'meetings.id', '=', 'meeting_attendees.meeting_id')
            ->where('meetings.opportunity_id', $opportunity->id)->whereNull('meetings.deleted_at')->whereIn('meeting_attendees.role', Meetings::EMAILED_ROLES)
            ->orderBy('meeting_attendees.email')->get(['meeting_attendees.email', 'meeting_attendees.name']);
        foreach ($attendees as $a) {
            $email = mb_strtolower(trim((string) $a->email));
            if ($email !== '' && ! isset($people[$email])) {
                $people[$email] = ['email' => $email, 'name' => trim((string) $a->name) ?: $email, 'client_user_id' => null];
            }
        }

        return $people;
    }

    /** Someone HQ already knows for this work: one of people(), or someone the proposal was sent to */
    public static function knows(Proposal $proposal, string $email): ?array
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return null;
        }
        foreach ((array) $proposal->recipients as $r) {
            if (mb_strtolower((string) ($r['email'] ?? '')) === $email) {
                return $r;
            }
        }

        return self::people($proposal->opportunity)[$email] ?? null;
    }

    /** Only an active contact of the organisation itself approves a proposal; meeting guests read and comment */
    public static function isActiveContact(Opportunity $opportunity, string $email): bool
    {
        return DB::table('client_users')->where('organization_id', $opportunity->organization_id)->where('status', 'Active')->whereNull('deleted_at')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->exists();
    }

    /**
     * Approves a proposal and signs a statement of work for the organisation (owner, 2 Nov 2026; docs/QA-PORTAL.md D5):
     * an active contact whose portal role has projects.approve (Client Admin, which the primary contact always has, or
     * Client Approver). The other contacts and meeting guests read, comment and ask for changes.
     */
    public static function isApprover(Opportunity $opportunity, string $email): bool
    {
        $contact = ClientUser::where('organization_id', $opportunity->organization_id)->where('status', 'Active')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->first();

        return $contact !== null && Access::allows($contact, Access::CLIENT_PREFIX . 'projects.approve');
    }

    /** The version of this opportunity the client approved, if any: then nothing more is approved, sent or started */
    public static function clientApproved(string $opportunityId): ?Proposal
    {
        return Proposal::where('opportunity_id', $opportunityId)->where('signing_mode', 'approve')->where('status', Proposal::CLIENT_APPROVED)->first();
    }

    /**
     * Sends an approved version: the branded email with the PDF and the portal link, in the opportunity's language,
     * to the chosen people (only from people()). The row is claimed under a lock before any email goes, so a double
     * click or two staff at once send it once; nothing is sent once the client approved a version. Every address
     * starts on recipients_unsent and is crossed off as its email goes (a request cut short leaves exactly the ones
     * to retry). Returns the addresses it could not reach; when none was reached nothing counts as sent and the
     * version stays approved, ready to send again. Earlier versions the client had are then superseded.
     *
     * @param  list<string>  $emails
     */
    public static function send(Proposal $proposal, array $emails, User $by): array
    {
        $opportunity = $proposal->opportunity;
        $chosen = self::choose($opportunity, $emails);
        if (! $chosen) {
            throw new LogicException('Choose at least one person HQ knows for this work');
        }
        // The VAT of the day it goes (the switch may have changed since it was approved), then the PDF, rendered
        // before anything is claimed: if the PDF fails, nothing is sent
        self::refreshVat($proposal);
        $pdf = ProposalPdf::render($proposal);

        return self::whileMailing($proposal, function () use ($proposal, $opportunity, $chosen, $pdf, $by) {
            $claimed = DB::transaction(function () use ($proposal, $chosen, $by) {
                self::lockOpportunity($proposal->opportunity_id);
                $locked = Proposal::whereKey($proposal->id)->lockForUpdate()->first();
                if (! $locked || $locked->status !== Proposal::DRAFT || $locked->approved_at === null || $locked->sent_at !== null
                    || self::clientApproved($locked->opportunity_id)) {
                    return false;
                }
                $locked->forceFill(['status' => Proposal::SENT, 'sent_at' => now(), 'recipients' => $chosen, 'recipients_unsent' => array_column($chosen, 'email'),
                    'updated_by' => $by->id])->save();

                return true;
            });
            if (! $claimed) {
                throw new LogicException('Only an approved version that has not been sent can be sent');
            }
            $proposal->refresh();

            $unsent = self::mail($proposal, $chosen, $pdf);
            if (count($unsent) === count($chosen)) {
                // Nothing went: not sent, so it can be sent again as it is
                $proposal->forceFill(['status' => Proposal::DRAFT, 'sent_at' => null, 'recipients' => null, 'recipients_unsent' => null])->save();

                return $unsent;
            }
            $proposal->refresh();
            self::afterSend($proposal, $opportunity, count($chosen) - count($unsent), $by);

            return $unsent;
        });
    }

    /** What follows the first send of a version: earlier versions superseded, the log, the stage and the event */
    private static function afterSend(Proposal $proposal, Opportunity $opportunity, int $reached, User $by): void
    {
        Proposal::where('opportunity_id', $opportunity->id)->where('signing_mode', 'approve')->where('version', '<', $proposal->version)
            ->whereIn('status', [Proposal::SENT, Proposal::CHANGES_REQUESTED])->update(['status' => Proposal::SUPERSEDED, 'updated_at' => now()]);
        OpportunityAccess::log('PROPOSAL_SENT', sprintf('Sent version %d of the proposal to %d %s.', $proposal->version, $reached, $reached === 1 ? 'person' : 'people'),
            'opportunities', $opportunity->id, [], ['proposal_id' => $proposal->id, 'version' => $proposal->version]);
        self::moveStage($opportunity, ['discovery', 'questionnaire', 'proposal'], 'proposal_sent');
        ProposalSent::dispatch($proposal->id, $opportunity->id, $by->id);
    }

    /**
     * Sends a sent version again, only to the addresses it didn't reach (recipients_unsent). Under the same mail lock
     * as send(), so two clicks never mail them twice; an address no longer among the recipients is dropped.
     */
    public static function resend(Proposal $proposal, User $by): array
    {
        return self::whileMailing($proposal, function () use ($proposal) {
            $proposal->refresh();
            $emails = $proposal->status === Proposal::SENT ? array_map('mb_strtolower', (array) $proposal->recipients_unsent) : [];
            if (! $emails) {
                return [];
            }
            $recipients = array_values(array_filter((array) $proposal->recipients, fn ($r) => in_array(mb_strtolower((string) ($r['email'] ?? '')), $emails, true)));
            $proposal->forceFill(['recipients_unsent' => array_column($recipients, 'email') ?: null])->save();
            $unsent = $recipients ? self::mail($proposal, $recipients, ProposalPdf::render($proposal)) : [];
            if (count($unsent) < count($recipients)) {
                $reached = count($recipients) - count($unsent);
                OpportunityAccess::log('PROPOSAL_SENT', sprintf('Sent version %d of the proposal again to %d %s.', $proposal->version, $reached, $reached === 1 ? 'person' : 'people'),
                    'opportunities', $proposal->opportunity_id, [], ['proposal_id' => $proposal->id, 'version' => $proposal->version]);
            }

            return $unsent;
        }, fn () => (array) $proposal->fresh()->recipients_unsent);
    }

    /**
     * Sends the email with the link (and the PDF) again for a version that is with the client and still valid: to the
     * same people or others chosen from people(), who join its recipients. Under the mail lock; each address is on
     * recipients_unsent until its email goes. Returns the addresses it could not reach.
     *
     * @param  list<string>  $emails
     */
    public static function sendAgain(Proposal $proposal, array $emails, User $by): array
    {
        $chosen = self::choose($proposal->opportunity, $emails);
        if (! $chosen) {
            throw new LogicException('Choose at least one person HQ knows for this work');
        }
        $pdf = ProposalPdf::render($proposal);

        return self::whileMailing($proposal, function () use ($proposal, $chosen, $pdf, $by) {
            $claimed = DB::transaction(function () use ($proposal, $chosen, $by) {
                self::lockOpportunity($proposal->opportunity_id);
                $locked = Proposal::whereKey($proposal->id)->lockForUpdate()->first();
                if (! $locked || $locked->status !== Proposal::SENT || $locked->hasExpired()) {
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
            $proposal->refresh();
            $unsent = self::mail($proposal, $chosen, $pdf);
            $reached = count($chosen) - count($unsent);
            if ($reached > 0) {
                OpportunityAccess::log('PROPOSAL_SENT', sprintf('Sent the link to version %d of the proposal again to %d %s.', $proposal->version, $reached, $reached === 1 ? 'person' : 'people'),
                    'opportunities', $proposal->opportunity_id, [], ['proposal_id' => $proposal->id, 'version' => $proposal->version]);
            }

            return $unsent;
        });
    }

    /**
     * A client's comment on one section (or the proposal as a whole: GENERAL), from the portal. The caller checks
     * that the person is known (knows()) and rate-limits; null when the version no longer takes comments (answered,
     * replaced or past its validity) or has MAX_CLIENT_COMMENTS already.
     */
    public static function comment(Proposal $proposal, string $sectionKey, array $person, string $body): ?ProposalComment
    {
        if ($proposal->status !== Proposal::SENT || $proposal->hasExpired() || ! self::hasSection($proposal, $sectionKey)
            || ProposalComment::where('proposal_id', $proposal->id)->where('author_type', 'client')->count() >= self::MAX_CLIENT_COMMENTS) {
            return null;
        }
        $comment = ProposalComment::create([
            'proposal_id' => $proposal->id,
            'section_key' => $sectionKey,
            'author_type' => 'client',
            'author_name' => Str::limit(trim((string) $person['name']), 150, ''),
            'author_email' => mb_strtolower(trim((string) $person['email'])),
            'body' => Str::limit(trim($body), self::MAX_COMMENT, ''),
        ]);
        $opportunity = $proposal->opportunity;
        self::tellLead($opportunity, __('proposals.notify.comment_title', [], config('app.staff_locale', 'en')),
            __('proposals.notify.comment', ['name' => $comment->author_name, 'title' => $opportunity->title, 'version' => $proposal->version], config('app.staff_locale', 'en')));

        return $comment;
    }

    /** Staff mark a client's comment dealt with, or open it again */
    public static function resolve(ProposalComment $comment, bool $resolved, User $by): void
    {
        $comment->forceFill(['resolved_by' => $resolved ? $by->id : null, 'resolved_at' => $resolved ? now() : null, 'updated_by' => $by->id])->save();
    }

    /**
     * The client approves the version in the portal with one click (decision 9): name, title and email recorded,
     * and only by one of the organisation's approvers (isApprover). Claimed under the opportunity's lock, so it
     * happens once and for one version only: every other open version (a draft, or one with the client) is set aside
     * (Superseded). The opportunity moves to Proposal approved; the lead is told.
     */
    public static function approveForClient(Proposal $proposal, array $person, string $jobTitle): bool
    {
        if (! self::isApprover($proposal->opportunity, (string) $person['email'])) {
            return false;
        }
        $done = DB::transaction(function () use ($proposal, $person, $jobTitle) {
            self::lockOpportunity($proposal->opportunity_id);
            $locked = Proposal::whereKey($proposal->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== Proposal::SENT || $locked->hasExpired() || self::clientApproved($locked->opportunity_id)) {
                return false;
            }
            $locked->forceFill([
                'status' => Proposal::CLIENT_APPROVED,
                'client_approved_name' => Str::limit(trim((string) $person['name']), 150, ''),
                'client_approved_title' => Str::limit(trim($jobTitle), 150, ''),
                'client_approved_email' => mb_strtolower(trim((string) $person['email'])),
                'client_approved_at' => now(),
            ])->save();
            Proposal::where('opportunity_id', $locked->opportunity_id)->where('signing_mode', 'approve')->whereKeyNot($locked->id)
                ->whereIn('status', [Proposal::DRAFT, Proposal::SENT, Proposal::CHANGES_REQUESTED])->update(['status' => Proposal::SUPERSEDED, 'updated_at' => now()]);

            return true;
        });
        if (! $done) {
            return false;
        }
        $proposal->refresh();
        $opportunity = $proposal->opportunity;
        OpportunityAccess::log('PROPOSAL_APPROVED_BY_CLIENT', "{$proposal->client_approved_name} ({$proposal->client_approved_title}) approved version {$proposal->version} of the proposal.",
            'opportunities', $opportunity->id, [], ['proposal_id' => $proposal->id, 'version' => $proposal->version]);
        self::moveStage($opportunity, ['discovery', 'questionnaire', 'proposal', 'proposal_sent'], 'proposal_approved');
        self::tellLead($opportunity, __('proposals.notify.approved_title', [], config('app.staff_locale', 'en')),
            __('proposals.notify.approved', ['name' => $proposal->client_approved_name, 'title' => $opportunity->title, 'version' => $proposal->version], config('app.staff_locale', 'en')));
        ProposalApprovedByClient::dispatch($proposal->id, $opportunity->id);

        return true;
    }

    /**
     * The client asks for changes (with an optional message, kept as a comment on the whole proposal): only on the
     * version with them, while it is valid and no version was approved. The stage goes back only from Proposal sent.
     */
    public static function requestChanges(Proposal $proposal, array $person, string $message): bool
    {
        $done = DB::transaction(function () use ($proposal, $person, $message) {
            self::lockOpportunity($proposal->opportunity_id);
            $locked = Proposal::whereKey($proposal->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== Proposal::SENT || $locked->hasExpired() || self::clientApproved($locked->opportunity_id)) {
                return false;
            }
            if (trim($message) !== '') {
                ProposalComment::create([
                    'proposal_id' => $locked->id, 'section_key' => self::GENERAL, 'author_type' => 'client',
                    'author_name' => Str::limit(trim((string) $person['name']), 150, ''), 'author_email' => mb_strtolower(trim((string) $person['email'])),
                    'body' => Str::limit(trim($message), self::MAX_COMMENT, ''),
                ]);
            }
            $locked->forceFill(['status' => Proposal::CHANGES_REQUESTED])->save();

            return true;
        });
        if (! $done) {
            return false;
        }
        $proposal->refresh();
        $opportunity = $proposal->opportunity;
        OpportunityAccess::log('PROPOSAL_CHANGES_REQUESTED', "{$person['name']} asked for changes to version {$proposal->version} of the proposal.",
            'opportunities', $opportunity->id, [], ['proposal_id' => $proposal->id, 'version' => $proposal->version]);
        self::moveStage($opportunity, ['proposal_sent'], 'proposal');
        self::tellLead($opportunity, __('proposals.notify.changes_title', [], config('app.staff_locale', 'en')),
            __('proposals.notify.changes', ['name' => $person['name'], 'title' => $opportunity->title, 'version' => $proposal->version], config('app.staff_locale', 'en')));
        ProposalChangesRequested::dispatch($proposal->id, $opportunity->id);

        return true;
    }

    /** The version's line items, in order */
    public static function lines(Proposal $proposal): Collection
    {
        return DB::table('proposal_line_items')->where('proposal_id', $proposal->id)->orderBy('sort_order')->orderBy('id')
            ->get(['code', 'description', 'quantity', 'unit_price', 'total_price']);
    }

    /** The active items of the financial dictionary, by code */
    public static function dictionary(): Collection
    {
        return DB::table('mdm_financial_dictionary')->where('is_active', 1)->orderBy('category')->orderBy('description')
            ->get(['item_code', 'description', 'category', 'default_quantity', 'default_unit_rate_usd'])->keyBy('item_code');
    }

    /** Currencies there is a rate for (mdm_exchange_rates), USD first */
    public static function currencies(): Collection
    {
        return DB::table('mdm_exchange_rates')->orderByRaw("currency_code <> 'USD'")->orderBy('currency_code')->pluck('currency_code')->push('USD')->unique()->values();
    }

    /** A dictionary item as a line: its price in the currency (rate_to_usd per US dollar, as ProposalBuilder) */
    public static function lineFor(object $item, string $currency, ?float $quantity = null): array
    {
        $rate = $currency === 'USD' ? 1.0 : (float) (DB::table('mdm_exchange_rates')->where('currency_code', $currency)->value('rate_to_usd') ?: 1);
        $unit = trim((string) preg_replace('/^\s*\d+(?:[.,]\d+)?\s*/', '', (string) $item->default_quantity));
        $quantity ??= self::units((string) $item->default_quantity);
        $price = round((float) $item->default_unit_rate_usd * $rate, 2);

        return [
            'code' => (string) $item->item_code,
            'description' => (string) $item->description,
            'quantity' => trim(self::number($quantity) . ' ' . $unit),
            'unit_price' => $price,
        ];
    }

    /** How many units a quantity like "3 sprints" or "1.5" counts for (1 without a number) */
    public static function units(string $quantity): float
    {
        return preg_match('/^\s*(\d+(?:[.,]\d+)?)/', $quantity, $m) ? max(0, (float) str_replace(',', '.', $m[1])) : 1.0;
    }

    /** Subtotal, VAT and total of lines ([quantity, unit_price]) at a VAT rate */
    public static function totals(iterable $lines, float $vatRate): array
    {
        $subtotal = 0.0;
        foreach ($lines as $line) {
            $line = (array) $line;
            $subtotal += round(self::units((string) ($line['quantity'] ?? '')) * (float) ($line['unit_price'] ?? 0), 2);
        }
        $vat = round($subtotal * $vatRate / 100, 2);

        return ['subtotal' => round($subtotal, 2), 'vat' => $vat, 'total' => round($subtotal + $vat, 2)];
    }

    /** The version as plain text, for the AI (what changed, the next version's draft) */
    public static function text(Proposal $proposal): string
    {
        $out = ['Title: ' . $proposal->title];
        foreach ((array) $proposal->sections as $section) {
            $out[] = '';
            $out[] = '[' . ($section['key'] ?? '') . '] ' . ($section['title'] ?? '');
            $out[] = trim((string) ($section['body'] ?? ''));
        }
        $lines = self::lines($proposal);
        if ($lines->isNotEmpty()) {
            $out[] = '';
            $out[] = "[line_items] Line items ({$proposal->currency})";
            foreach ($lines as $l) {
                $out[] = "- {$l->code} {$l->description}: {$l->quantity} × " . number_format((float) $l->unit_price, 2, '.', ',');
            }
            $out[] = 'Total before VAT: ' . number_format((float) $proposal->subtotal, 2, '.', ',');
        }

        return implode("\n", $out);
    }

    /** True while an AI job is queued or running */
    public static function running(?string $runId): bool
    {
        return $runId !== null && AiRun::whereKey($runId)->whereIn('status', ['queued', 'running'])->exists();
    }

    public static function hasSection(Proposal $proposal, string $key): bool
    {
        return $key === self::GENERAL || collect((array) $proposal->sections)->contains(fn ($s) => ($s['key'] ?? null) === $key);
    }

    /**
     * Sections as HQ keeps them: trimmed, a snake_case key unique in the version (kept from the AI or the previous
     * version, so comments stay attached), at most MAX_SECTIONS. A section without a title is dropped; one without
     * text is kept only while editing.
     */
    public static function cleanSections(array $raw, bool $keepEmpty = false): array
    {
        $sections = [];
        $keys = [];
        foreach (array_values($raw) as $section) {
            if (! is_array($section) || count($sections) >= self::MAX_SECTIONS) {
                continue;
            }
            $title = Str::limit(trim((string) preg_replace('/\s+/u', ' ', (string) ($section['title'] ?? ''))), self::MAX_TITLE, '');
            $body = Str::limit(trim(str_replace("\r", '', (string) ($section['body'] ?? ''))), self::MAX_BODY, '');
            if ($title === '' || (! $keepEmpty && $body === '')) {
                continue;
            }
            $key = Str::limit(Str::snake(preg_replace('/[^A-Za-z0-9 _]/', ' ', Str::ascii((string) ($section['key'] ?? '')))), 60, '') ?: Str::limit(Str::snake(preg_replace('/[^A-Za-z0-9 ]/', ' ', Str::ascii($title))), 60, '');
            $key = trim(preg_replace('/_+/', '_', $key), '_') ?: 'section';
            if ($key === self::GENERAL) {
                $key = 'general_section';
            }
            $unique = $key;
            for ($i = 2; isset($keys[$unique]); $i++) {
                $unique = "{$key}_{$i}";
            }
            $keys[$unique] = true;
            $sections[] = ['key' => $unique, 'title' => $title, 'body' => $body];
        }

        return $sections;
    }

    /** "PRP-20261101-482": the reference every version of the proposal shares */
    private static function reference(): string
    {
        do {
            $ref = 'PRP-' . now('Africa/Lagos')->format('Ymd') . '-' . random_int(100, 999);
        } while (Proposal::withTrashed()->where('proposal_ref', $ref)->exists());

        return $ref;
    }

    /**
     * The VAT a version shows (Vat::for's answer): today's while it hasn't been sent (the opportunity's billing company,
     * its switch and start date decide), else what it recorded when it was sent, whatever has changed since.
     */
    public static function vat(Proposal $proposal): array
    {
        if ($proposal->status === Proposal::DRAFT && $proposal->sent_at === null) {
            return Vat::for($proposal->organization_id, null, $proposal->opportunity?->billing_entity_id);
        }

        return Vat::recorded($proposal->vat_applied, $proposal->vat_rate, $proposal->billing_entity_id, $proposal->organization_id);
    }

    /** Brings a version not sent yet to today's VAT, under the lock, when the switch has changed since it was saved */
    public static function refreshVat(Proposal $proposal): void
    {
        DB::transaction(function () use ($proposal) {
            $locked = Proposal::whereKey($proposal->id)->lockForUpdate()->first();
            if ($locked && self::followVat($locked)) {
                $locked->save();
                $proposal->setRawAttributes($locked->getAttributes(), true);
            }
        });
    }

    /**
     * A version not sent yet takes today's VAT (rate, vat_applied, the issuing company and the total from its
     * subtotal); a sent one keeps what it had. The caller saves. True when something changed.
     */
    private static function followVat(Proposal $proposal): bool
    {
        if ($proposal->status !== Proposal::DRAFT || $proposal->sent_at !== null) {
            return false;
        }
        $vat = Vat::for($proposal->organization_id, null, $proposal->opportunity?->billing_entity_id);
        $subtotal = (float) $proposal->subtotal;
        $total = round($subtotal + round($subtotal * $vat['rate'] / 100, 2), 2);
        $changed = round((float) $proposal->vat_rate, 2) !== round($vat['rate'], 2) || $proposal->vat_applied !== $vat['applies']
            || $proposal->billing_entity_id !== $vat['entity_id'] || round((float) $proposal->total_value, 2) !== $total;
        $proposal->forceFill(['vat_rate' => $vat['rate'], 'vat_applied' => $vat['applies'], 'billing_entity_id' => $vat['entity_id'], 'total_value' => $total]);

        return $changed;
    }

    /**
     * The lead's instructions set a budget or ceiling for our fees (owner, 2 Nov 2026): the draft must fit it, so the AI
     * is given the dictionary's prices. A budget word with any figure ("budget 15 million", "plafond 10 M"), or a
     * softer cue close to an amount in a currency ("around NGN 12M", "under $40,000"). Amounts about the client's
     * business ("tickets sell at NGN 5,000", "2 million users", "about 10k tickets") are not a budget.
     */
    public static function budgetGiven(string $instructions): bool
    {
        $currency = 'NGN|XAF|XOF|FCFA|USD|EUR|GBP|KES|ZAR|AED|INR|CAD|AUD|nairas?|dollars?|euros?';
        $amount = '(?:[₦$€£]\s*\d|\b(?:' . $currency . ')\s*\d|\d[\d,.\s]*(?:\s*(?:k|m|bn|thousands?|millions?|billions?)\b)?\s*(?:de\s+)?(?:' . $currency . ')\b)';
        $strong = '\b(?:budget|ceiling|cap|plafond|maximum|at most|no more than|not exceed|ne pas dépasser)\b[^.\n]{0,40}\d';
        $soft = '\b(?:around|about|under|up to|within|below|roughly|approximately|less than|environ|moins de|jusqu[\'’]à)\b[^.\n]{0,25}?' . $amount;

        return (bool) preg_match('/' . $strong . '|' . $soft . '/iu', $instructions);
    }

    /**
     * The financial dictionary for the AI (codes; with unit prices in the proposal's currency when the lead gave a
     * budget), then, for a later version, the one the client had and their comments
     */
    private static function draftMaterial(Proposal $proposal, bool $withPrices = false): string
    {
        $out = [$withPrices
            ? "FINANCIAL DICTIONARY (suggest line items by these codes only; unit prices in {$proposal->currency}, before VAT, are HQ’s and can’t change. If the lead’s instructions set a budget or ceiling for JBS Praxis’s fees, fit the line items within it; amounts about the client’s business are not a budget):"
            : 'FINANCIAL DICTIONARY (suggest line items by these codes only; HQ sets the prices):'];
        foreach (self::dictionary() as $item) {
            $price = $withPrices ? ' | unit price: ' . $proposal->currency . ' ' . number_format(self::lineFor($item, (string) $proposal->currency)['unit_price'], 2, '.', '') : '';
            $out[] = "- {$item->item_code} | {$item->description} | {$item->category} | default quantity: {$item->default_quantity}{$price}";
        }

        $previous = $proposal->previousVersion;
        if ($previous) {
            $out[] = '';
            $out[] = "THE VERSION THE CLIENT HAS (version {$previous->version}): keep what they didn’t question, and the section keys.";
            $out[] = self::text($previous);
            $comments = ProposalComment::where('proposal_id', $previous->id)->where('author_type', 'client')->orderBy('created_at')->get();
            if ($comments->isNotEmpty()) {
                $titles = collect((array) $previous->sections)->pluck('title', 'key');
                $out[] = '';
                $out[] = 'THE CLIENT’S COMMENTS ON IT (answer each one in the new version):';
                foreach ($comments as $c) {
                    $out[] = '- On ' . ($c->section_key === self::GENERAL ? 'the proposal as a whole' : '“' . ($titles[$c->section_key] ?? $c->section_key) . '”') . ': ' . $c->body;
                }
            }
        }

        return implode("\n", $out);
    }

    /** The AI's draft becomes the version: title, sections and the suggested line items, priced from the dictionary */
    private static function applyDraft(Proposal $locked, AiRun $run): bool
    {
        $sections = self::cleanSections((array) ($run->output['sections'] ?? []));
        if (! $sections) {
            // Nothing usable came back: the job counts as failed, so the lead can ask again
            $run->forceFill(['status' => 'failed', 'error' => 'ai.errors.invalid'])->save();

            return false;
        }
        $dictionary = self::dictionary()->keyBy(fn ($item) => mb_strtolower($item->item_code));
        $lines = [];
        foreach ((array) ($run->output['suggested_line_items'] ?? []) as $suggestion) {
            $item = $dictionary[mb_strtolower(trim((string) ($suggestion['code'] ?? '')))] ?? null;
            if ($item && ! isset($lines[$item->item_code]) && count($lines) < self::MAX_LINES) {
                $quantity = (float) ($suggestion['quantity'] ?? 0);
                $lines[$item->item_code] = self::lineFor($item, $locked->currency, $quantity > 0 ? min($quantity, 999) : null);
            }
        }

        $title = Str::limit(trim((string) ($run->output['title'] ?? '')), self::MAX_TITLE, '');
        $locked->forceFill(['title' => $title !== '' ? $title : $locked->title, 'sections' => $sections, 'ai_run_id' => null, 'updated_by' => $run->requested_by]);
        self::writeLines($locked, array_values($lines));

        return true;
    }

    /** What changed, as text the client reads: the summary, then one line per section */
    private static function applyChanges(Proposal $locked, AiRun $run): bool
    {
        $titles = collect((array) $locked->previousVersion?->sections)->pluck('title', 'key')->merge(collect((array) $locked->sections)->pluck('title', 'key'))
            ->put('line_items', __('proposals.pdf.investment', [], $locked->opportunity?->language ?? 'en'));
        $lines = [trim((string) ($run->output['summary'] ?? ''))];
        $items = [];
        foreach ((array) ($run->output['changes'] ?? []) as $change) {
            $text = trim((string) ($change['change'] ?? ''));
            if ($text !== '') {
                $key = (string) ($change['section_key'] ?? '');
                $items[] = '- ' . (isset($titles[$key]) ? $titles[$key] . ': ' : '') . $text;
            }
        }
        $summary = trim(implode("\n\n", array_filter([$lines[0], implode("\n", $items)])));
        if ($summary === '') {
            $run->forceFill(['status' => 'failed', 'error' => 'ai.errors.invalid'])->save();

            return false;
        }
        $locked->forceFill(['changes_summary' => Str::limit($summary, self::MAX_NOTE, ''), 'changes_run_id' => null, 'updated_by' => $run->requested_by])->save();

        return true;
    }

    /** Lines as HQ keeps them: a description, a quantity ("2 phases"), a unit price; at most MAX_LINES */
    private static function cleanLines(array $raw): array
    {
        $lines = [];
        foreach (array_values($raw) as $line) {
            if (! is_array($line) || count($lines) >= self::MAX_LINES) {
                continue;
            }
            $description = Str::limit(trim((string) ($line['description'] ?? '')), 255, '');
            if ($description === '') {
                continue;
            }
            $lines[] = [
                'code' => Str::limit(trim((string) ($line['code'] ?? '')), 20, '') ?: 'CUSTOM',
                'description' => $description,
                'quantity' => Str::limit(trim((string) ($line['quantity'] ?? '')), 50, '') ?: '1',
                'unit_price' => max(0, min(999999999, round((float) ($line['unit_price'] ?? 0), 2))),
            ];
        }

        return $lines;
    }

    /** Replaces the version's line items and sets its subtotal and total (VAT at its rate; today's while not sent: followVat()) */
    private static function writeLines(Proposal $proposal, array $lines): void
    {
        DB::table('proposal_line_items')->where('proposal_id', $proposal->id)->delete();
        foreach (array_values($lines) as $i => $line) {
            DB::table('proposal_line_items')->insert([
                'proposal_id' => $proposal->id, 'code' => $line['code'], 'description' => $line['description'], 'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'], 'total_price' => round(self::units($line['quantity']) * (float) $line['unit_price'], 2), 'sort_order' => $i,
            ]);
        }
        self::followVat($proposal);
        $totals = self::totals($lines, (float) $proposal->vat_rate);
        $proposal->forceFill(['subtotal' => $totals['subtotal'], 'total_value' => $totals['total']])->save();
    }

    /**
     * One branded email per person, with the PDF. The caller has put every address on recipients_unsent; each is
     * crossed off as soon as its email has gone, so a request cut short (a PHP timeout) leaves exactly the addresses
     * still to send. Returns the addresses that failed.
     */
    private static function mail(Proposal $proposal, array $recipients, string $pdf): array
    {
        $unsent = [];
        foreach ($recipients as $r) {
            try {
                Mail::to($r['email'], $r['name'] ?? null)->locale($proposal->opportunity->language)
                    ->send(new ProposalMail($proposal, $r['client_user_id'] ?? null, self::firstName($r), $pdf));
            } catch (Throwable $e) {
                Log::warning('Proposals: the email could not be sent', ['proposal' => $proposal->id, 'error' => $e->getMessage()]);
                $unsent[] = $r['email'];

                continue;
            }
            DB::transaction(function () use ($proposal, $r) {
                $locked = Proposal::whereKey($proposal->id)->lockForUpdate()->first();
                $left = array_values(array_filter((array) $locked?->recipients_unsent, fn ($e) => mb_strtolower((string) $e) !== mb_strtolower($r['email'])));
                $locked?->forceFill(['recipients_unsent' => $left ?: null])->save();
            });
        }

        return $unsent;
    }

    /** The chosen addresses that are among people(), once each, at most MAX_RECIPIENTS */
    private static function choose(Opportunity $opportunity, array $emails): array
    {
        $people = self::people($opportunity);

        return array_slice(array_values(array_filter(array_map(fn ($e) => $people[mb_strtolower(trim((string) $e))] ?? null,
            array_unique(array_map(fn ($e) => mb_strtolower(trim((string) $e)), $emails))))), 0, self::MAX_RECIPIENTS);
    }

    /**
     * Runs a send under the version's mail lock (an atomic cache lock), so a send, a resend and a "send again" never
     * mail the same version at once. When the lock is taken, $busy answers (or a LogicException says so).
     */
    private static function whileMailing(Proposal $proposal, callable $send, ?callable $busy = null): array
    {
        $lock = Cache::lock('proposal-mail:' . $proposal->id, 600);
        if (! $lock->get()) {
            return $busy ? $busy() : throw new LogicException('This version is being sent already');
        }
        try {
            return $send();
        } finally {
            $lock->release();
        }
    }

    /**
     * Locks the opportunity's row. Everything that changes which versions are open, sent or approved locks it first,
     * then the version: so two versions are never approved by the client, nor one sent after another was approved.
     */
    private static function lockOpportunity(string $opportunityId): void
    {
        Opportunity::whereKey($opportunityId)->lockForUpdate()->first();
    }

    private static function firstName(array $recipient): ?string
    {
        $name = trim((string) ($recipient['name'] ?? ''));

        return $name === '' || str_contains($name, '@') ? null : explode(' ', $name)[0];
    }

    private static function number(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    /** Moves the opportunity forward from one of $from (never back from won, lost, on hold or the SOW) */
    private static function moveStage(Opportunity $opportunity, array $from, string $to): void
    {
        $opportunity->refresh();
        if (! in_array($opportunity->stage, $from, true) || $opportunity->stage === $to) {
            return;
        }
        $old = $opportunity->stage;
        $opportunity->forceFill(['stage' => $to, 'updated_by' => Auth::guard('web')->id()])->save();
        OpportunityAccess::log('OPPORTUNITY_STAGE_CHANGED', "Moved “{$opportunity->title}” from {$old} to {$to}.", 'opportunities', $opportunity->id,
            ['stage' => $old], ['stage' => $to]);
    }

    /** A bell notification (and so a phone push) for the opportunity's lead, opening its Proposals tab */
    private static function tellLead(Opportunity $opportunity, string $title, string $message): void
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
            'link_url' => route('hq.crm.opportunity', ['opportunity' => $opportunity->id, 'tab' => 'proposals']),
            'is_read' => 0,
            'created_at' => now(),
        ]);
    }
}
