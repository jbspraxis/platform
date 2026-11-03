<?php

namespace App\Support\Leads;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A lead's status (organizations.lead_status, owner's decisions 5 and 6, docs/LEADS.md section 6): New → Contacted →
 * Qualified → Client, or Lost with a reason. It is independent of the lead's opportunities (a lead can have several,
 * some won and some lost). Client goes with organizations.status = Active Client and is reached two ways only:
 *
 *   markClientFromSigning()  the first signed proposal (legacy gateway) or SOW (Sows::sign), inside their transaction
 *   markClient()             by hand, by someone with crm.edit
 *
 * A client made by signing stays one; a client marked by hand can be moved back (a mistake), which makes it a Lead
 * again. Every change writes the timeline and the audit log.
 */
final class LeadStatus
{
    public const LABELS = ['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'client' => 'Client', 'lost' => 'Lost'];

    /** Open leads: each has a next step (PR 3's follow-ups list these) */
    public const OPEN = ['new', 'contacted', 'qualified'];

    public const LOST_REASONS = [
        'no_budget' => 'No budget',
        'no_need' => 'No need',
        'chose_someone_else' => 'Chose someone else',
        'no_reply' => 'No reply',
        'not_a_fit' => 'Not a fit',
        'other' => 'Other',
    ];

    public const CLIENT_VIA = ['signed' => 'Signed', 'manual' => 'Marked by hand', 'existing' => 'A client before the Leads revamp'];

    public static function label(?string $status): string
    {
        return self::LABELS[(string) $status] ?? 'New';
    }

    /** The pill colour (app.css): Client green, Lost red, Qualified teal, the rest neutral */
    public static function tone(?string $status): string
    {
        return match ($status) { 'client' => 'pill-success', 'lost' => 'pill-danger', 'qualified' => 'pill-info', default => 'pill-neutral' };
    }

    public static function isOpen(?string $status): bool
    {
        return in_array($status ?? 'new', self::OPEN, true);
    }

    /**
     * Move the lead to $status. Client goes through markClient, Lost through markLost (reason required, a note too for
     * "other"). Leaving Lost clears its reason; leaving a client marked by hand makes it a Lead again; a client made by
     * signing can't be moved. Returns false when nothing changed.
     *
     * @throws LeadRuleBroken
     */
    public static function change(Organization $lead, string $status, User $by, ?string $lostReason = null, ?string $lostNote = null): bool
    {
        if (! isset(self::LABELS[$status])) {
            throw new LeadRuleBroken('Choose one of the statuses in the list.', 'status');
        }
        if ($status === 'client') {
            return self::markClient($lead, $by);
        }
        if ($status === 'lost') {
            return self::markLost($lead, (string) $lostReason, $lostNote, $by);
        }

        return DB::transaction(function () use ($lead, $status, $by) {
            $row = self::lock($lead);
            if ($row->lead_status === $status) {
                return false;
            }
            $set = ['lead_status' => $status, 'updated_by' => $by->id, 'updated_at' => now()];
            if ($row->lead_status === 'client') {
                if ($row->client_via !== 'manual') {
                    throw new LeadRuleBroken($row->client_via === 'signed'
                        ? 'This lead became a client when it signed, so it stays a client.'
                        : 'This organisation was a client before the Leads revamp, so it stays a client.', 'status');
                }
                $set += ['status' => 'Lead', 'client_since' => null, 'client_via' => null, 'client_reference' => null];
            }
            if ($row->lead_status === 'lost') {
                $set += ['lost_reason' => null, 'lost_note' => null, 'lost_at' => null];
            }
            DB::table('organizations')->where('id', $row->id)->update($set);

            $from = self::label($row->lead_status);
            $to = self::label($status);
            LeadTimeline::record($row->id, 'status_changed', "Status changed from {$from} to {$to}.", [
                'actor' => $by, 'payload' => ['from' => $row->lead_status, 'to' => $status],
            ]);
            LeadAccess::log('LEAD_STATUS_CHANGED', "Moved the lead {$row->name} from {$from} to {$to}.", $row->id,
                ['lead_status' => $row->lead_status], ['lead_status' => $status]);
            $lead->refresh();

            return true;
        });
    }

    /**
     * Lost, with a reason from LOST_REASONS (and a note, required for "other"). A client can't be lost.
     *
     * @throws LeadRuleBroken
     */
    public static function markLost(Organization $lead, string $reason, ?string $note, ?User $by): bool
    {
        $note = trim((string) $note);
        if (! isset(self::LOST_REASONS[$reason])) {
            throw new LeadRuleBroken('Choose why the lead was lost.', 'lostReason');
        }
        if ($reason === 'other' && $note === '') {
            throw new LeadRuleBroken('Say in a few words why the lead was lost.', 'lostNote');
        }
        if (mb_strlen($note) > 2000) {
            throw new LeadRuleBroken('Keep the note under 2,000 characters.', 'lostNote');
        }

        return DB::transaction(function () use ($lead, $reason, $note, $by) {
            $row = self::lock($lead);
            if ($row->lead_status === 'client' || $row->status === 'Active Client') {
                throw new LeadRuleBroken('A client can’t be marked as lost.', 'status');
            }
            if ($row->lead_status === 'lost' && $row->lost_reason === $reason && (string) $row->lost_note === $note) {
                return false;
            }
            DB::table('organizations')->where('id', $row->id)->update([
                'lead_status' => 'lost', 'lost_reason' => $reason, 'lost_note' => $note !== '' ? $note : null,
                'lost_at' => $row->lead_status === 'lost' && $row->lost_at ? $row->lost_at : now(),
                // The old stage and disqualification reason, which Broadcasts and Free audits still read
                'pipeline_stage' => 'Lost', 'disqualification_reason' => self::LOST_REASONS[$reason] . ($note !== '' ? ": {$note}" : ''),
                'updated_by' => $by?->id, 'updated_at' => now(),
            ]);
            $why = self::LOST_REASONS[$reason];
            LeadTimeline::record($row->id, 'marked_lost', "Marked as lost: {$why}.", [
                'actor' => $by, 'body' => $note, 'payload' => ['from' => $row->lead_status, 'reason' => $reason],
            ]);
            LeadAccess::log('LEAD_LOST', "Marked the lead {$row->name} as lost: {$why}.", $row->id,
                ['lead_status' => $row->lead_status, 'lost_reason' => $row->lost_reason], ['lead_status' => 'lost', 'lost_reason' => $reason]);
            $lead->refresh();

            return true;
        });
    }

    /** By hand (crm.edit): the lead becomes an Active Client from now, "marked by hand" */
    public static function markClient(Organization $lead, User $by): bool
    {
        return DB::transaction(function () use ($lead, $by) {
            $row = self::lock($lead);
            if ($row->lead_status === 'client') {
                return false;
            }
            DB::table('organizations')->where('id', $row->id)->update([
                'status' => 'Active Client', 'lead_status' => 'client', 'client_since' => now(), 'client_via' => 'manual', 'client_reference' => null,
                'lost_reason' => null, 'lost_note' => null, 'lost_at' => null, 'updated_by' => $by->id, 'updated_at' => now(),
            ]);
            LeadTimeline::record($row->id, 'marked_client', 'Marked as a client by hand.', ['actor' => $by, 'payload' => ['from' => $row->lead_status, 'via' => 'manual']]);
            LeadAccess::log('LEAD_MARKED_CLIENT', "Marked the lead {$row->name} as a client.", $row->id,
                ['lead_status' => $row->lead_status, 'status' => $row->status], ['lead_status' => 'client', 'status' => 'Active Client']);
            $lead->refresh();

            return true;
        });
    }

    /**
     * The client signed a proposal ($what = 'proposal', the legacy gateway) or a statement of work ('sow'): called inside
     * the signing's transaction instead of setting status Active Client. The first signature sets client_since and
     * "signed"; a later one (or one after a hand mark) only adds its line to the timeline. Writes no audit line (the
     * signing writes its own) and rings no one (the signing does).
     */
    public static function markClientFromSigning(string $organizationId, string $what, ?string $reference, ?string $documentId = null, $at = null): void
    {
        $at = $at ? Carbon::parse($at) : now();
        $row = DB::table('organizations')->where('id', $organizationId)->lockForUpdate()->first();
        if (! $row) {
            return;
        }
        $first = $row->lead_status !== 'client';
        DB::table('organizations')->where('id', $organizationId)->update(array_merge(
            ['status' => 'Active Client', 'updated_at' => $at],
            $first ? ['lead_status' => 'client', 'client_since' => $at, 'client_via' => 'signed', 'client_reference' => $reference ? mb_substr($reference, 0, 60) : null,
                'lost_reason' => null, 'lost_note' => null, 'lost_at' => null] : [],
        ));
        $document = $what === 'sow' ? 'the statement of work' : 'the proposal';
        LeadTimeline::record($organizationId, $first ? 'marked_client' : 'signed', trim(($first ? 'Became a client: signed ' : 'Signed ') . "{$document} {$reference}") . '.', [
            'at' => $at, 'payload' => ['via' => 'signed', 'what' => $what, 'reference' => $reference, 'from' => $row->lead_status],
            'related_type' => $what === 'sow' ? 'statement_of_work' : 'proposal', 'related_id' => $documentId,
        ]);
    }

    /** How the lead became a client, for the page: "Signed SOW-20261101-001", "Marked by hand", … */
    public static function clientHow(object $lead): ?string
    {
        return match ($lead->client_via ?? null) {
            'signed' => trim('Signed ' . ($lead->client_reference ?? '')),
            'manual' => 'Marked by hand',
            'existing' => 'A client before the Leads revamp',
            default => null,
        };
    }

    private static function lock(Organization $lead): object
    {
        $row = DB::table('organizations')->where('id', $lead->id)->whereIn('status', LeadAccess::STATUSES)->lockForUpdate()->first();
        if (! $row) {
            throw new LeadRuleBroken('This lead is no longer in the Leads list.', 'status');
        }

        return $row;
    }
}
