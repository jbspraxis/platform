<?php

namespace App\Support\Leads;

use App\Models\ClientUser;
use App\Models\Mail\MailAccount;
use App\Models\User;
use App\Support\Integrations;
use App\Support\Mail\MailAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A lead's email (docs/LEADS.md section 9, owner's decision 12): HQ Mail to and from its contacts, matched by
 * address (client_users.email, lower case) both ways: mail from a contact, and mail to or copied to one.
 * Spam, Trash and Drafts don't count; a message in two folders or two mailboxes counts once.
 *
 *   LeadEmails::for($user, $orgId)   the messages this person may read (MailAccess: their own mailbox and the shared
 *                                    ones they are on), newest first, for the Emails tab
 *   LeadEmails::fileNew()            leads:file-emails (every five minutes): new mail since the last run becomes
 *                                    timeline lines (type email, related_type mail_message), for every mailbox
 *   LeadEmails::fileFor($orgId)      the same for one lead's past mail, when its Emails tab opens (a contact added later)
 *
 * Timeline lines say who wrote to whom and when, never the subject or the text: everyone with Leads access reads the
 * timeline, and personal mailboxes are private (docs/MAIL.md section 7.1). The Emails tab shows the rest to those who
 * may read the mailbox.
 *
 * Matching To and Cc reads every message of the mailboxes (an address inside to_text can't use an index), so it is
 * done as rarely as possible: the tab count is kept for COUNT_SECONDS (new mail, a contact's address or the person's
 * mailboxes change its key, so it shows at once), and fileFor() remembers how far it filed each lead.
 */
final class LeadEmails
{
    /** Folders whose mail never counts */
    public const SKIPPED_FOLDERS = ['spam', 'trash', 'drafts'];

    /** Most messages the Emails tab lists */
    public const LIMIT = 200;

    /** Messages one run of leads:file-emails reads */
    public const BATCH = 2000;

    /** integration_settings key: the last mail_messages.id leads:file-emails has read */
    public const CURSOR = 'leads.emails_cursor';

    /** How long the lead page keeps its Emails count: only mail moved or deleted since can make it wrong meanwhile */
    public const COUNT_SECONDS = 300;

    /** lower-case address => client_users.id, the lead's contacts that have an email */
    public static function addresses(string $organizationId): array
    {
        return DB::table('client_users')->where('organization_id', $organizationId)->whereNull('deleted_at')->whereNotNull('email')->where('email', '!=', '')
            ->pluck('id', 'email')->mapWithKeys(fn ($id, $email) => [mb_strtolower(trim($email)) => $id])->all();
    }

    /**
     * The lead's messages this person may read, newest first, one row per message: id, account_id, mailbox, thread_id,
     * subject, from_name, from_email, to (list of {name, email}), at, direction (in|out), snippet, has_attachments,
     * contact_id and contact_email (the contact it was from or to). Empty when Mail isn't open to them.
     */
    public static function for(?User $user, string $organizationId, int $limit = self::LIMIT): Collection
    {
        $addresses = self::addresses($organizationId);
        $accountIds = $addresses ? self::readable($user) : [];
        if (! $accountIds) {
            return collect();
        }
        $list = self::listed($addresses, $accountIds, $limit);
        // The tab has just listed them: the lead page's count follows (LeadPage renders again on lead-changed)
        if ($limit === self::LIMIT) {
            Cache::put(self::countKey($user, $organizationId, $addresses, $accountIds), $list->count(), self::COUNT_SECONDS);
        }

        return $list;
    }

    /**
     * How many messages the Emails tab lists for this person (up to LIMIT). Kept a few minutes: the key changes with
     * new mail, the lead's addresses and the person's mailboxes, so the lead page reads the mail once, not on every click.
     */
    public static function count(?User $user, string $organizationId): int
    {
        $addresses = self::addresses($organizationId);
        $accountIds = $addresses ? self::readable($user) : [];
        if (! $accountIds) {
            return 0;
        }

        return (int) Cache::remember(self::countKey($user, $organizationId, $addresses, $accountIds), self::COUNT_SECONDS,
            fn () => self::listed($addresses, $accountIds, self::LIMIT)->count());
    }

    /** The mailboxes this person may read, or none when Mail isn't open to them */
    private static function readable(?User $user): array
    {
        return MailAccess::canUse($user) ? MailAccess::accountIds($user, 'read') : [];
    }

    /** The count's cache key: the person, the lead, its addresses, the person's mailboxes and the newest message stored */
    private static function countKey(User $user, string $organizationId, array $addresses, array $accountIds): string
    {
        $latest = (int) DB::table('mail_messages')->max('id');

        return 'lead-emails-count:' . $user->id . ':' . $organizationId . ':' . md5(json_encode([$addresses, $accountIds, $latest]));
    }

    /** The lead's messages in these mailboxes, newest first, one row per message (see for()) */
    private static function listed(array $addresses, array $accountIds, int $limit): Collection
    {
        $mailboxes = MailAccount::whereIn('id', $accountIds)->get(['id', 'kind', 'email', 'display_name'])->keyBy('id');

        $out = [];
        foreach (self::matching(array_keys($addresses), $accountIds)->orderByDesc('m.received_at')->orderByDesc('m.id')->limit($limit * 3)->get() as $m) {
            $match = self::match($m, $addresses);
            $key = $m->message_id_hash ?: 'id:' . $m->id;
            if (! $match || isset($out[$key])) {
                continue;
            }
            $out[$key] = (object) [
                'id' => (int) $m->id,
                'account_id' => $m->account_id,
                'mailbox' => $mailboxes[$m->account_id]?->display_name ?? '',
                'thread_id' => $m->thread_id ? (int) $m->thread_id : null,
                'subject' => trim((string) $m->subject) !== '' ? $m->subject : '(no subject)',
                'from_name' => $m->from_name,
                'from_email' => $m->from_email,
                'to' => self::list($m->to),
                'at' => Carbon::parse($m->received_at ?? $m->sent_at),
                'direction' => $match['direction'],
                'snippet' => $m->snippet,
                'has_attachments' => (bool) $m->has_attachments,
                'contact_id' => $match['contact_id'],
                'contact_email' => $match['email'],
            ];
            if (count($out) >= $limit) {
                break;
            }
        }

        return collect(array_values($out));
    }

    /**
     * leads:file-emails: the mail HQ stored since the last run (every mailbox: a system job), each message to or from
     * a lead's contact recorded once per lead. Returns the lines written.
     */
    public static function fileNew(int $batch = self::BATCH): int
    {
        $after = (int) Integrations::get(self::CURSOR);
        $messages = self::base(null)->where('m.id', '>', $after)->orderBy('m.id')->limit($batch)->get();
        if ($messages->isEmpty()) {
            return 0;
        }
        $written = self::file($messages, self::everyLeadsAddresses());
        Integrations::set(self::CURSOR, (string) $messages->last()->id);

        return $written;
    }

    /**
     * One lead's mail from before the cursor (a contact added since): what fileNew() would have written. Returns the lines
     * written. It remembers how far it filed the lead and for which addresses: with the same addresses, only the mail
     * leads:file-emails has read since is checked again (nothing when the cursor hasn't moved). A message moved out of
     * Spam later is filed when the lead's addresses next change.
     */
    public static function fileFor(string $organizationId): int
    {
        $addresses = self::addresses($organizationId);
        if (! $addresses) {
            return 0;
        }
        $after = (int) Integrations::get(self::CURSOR);
        $hash = md5(json_encode($addresses));
        $done = Cache::get(self::filedKey($organizationId));
        $from = is_array($done) && ($done['hash'] ?? null) === $hash && (int) ($done['upto'] ?? 0) <= $after ? (int) $done['upto'] : 0;

        $written = 0;
        $upto = $after;
        if ($from < $after) {
            $messages = self::matching(array_keys($addresses), null)->where('m.id', '>', $from)->where('m.id', '<=', $after)->orderBy('m.id')->limit(self::BATCH)->get();
            $written = self::file($messages, array_map(fn ($id) => ['contact_id' => $id, 'organization_id' => $organizationId], $addresses));
            // A full batch: the next opening goes on from the last message read
            if ($messages->count() >= self::BATCH) {
                $upto = (int) $messages->last()->id;
            }
        }
        if ($done !== ['hash' => $hash, 'upto' => $upto]) {
            Cache::put(self::filedKey($organizationId), ['hash' => $hash, 'upto' => $upto], now()->addDays(7));
        }

        return $written;
    }

    /** Cache key: how far fileFor() filed a lead's mail, and for which addresses */
    private static function filedKey(string $organizationId): string
    {
        return 'lead-emails-filed:' . $organizationId;
    }

    /**
     * One line per message and lead (dedupe_key mail:<lead>:<message>): "Ada Obi emailed Gerald Nwoke", at the time
     * it arrived or was sent. The contact who wrote is the actor of mail in; the staff member who sent it (or the
     * personal mailbox's owner) of mail out. Lines already there, the contacts and the staff are read once per batch.
     *
     * @param  array<string, array{contact_id: string, organization_id: string}>  $addresses
     */
    private static function file(Collection $messages, array $addresses): int
    {
        if ($messages->isEmpty() || ! $addresses) {
            return 0;
        }
        $mailboxes = MailAccount::whereIn('id', $messages->pluck('account_id')->unique()->all())->get(['id', 'kind', 'display_name', 'owner_user_id'])->keyBy('id');

        // Each message's leads first, so what is already on the timeline is asked once
        $lines = [];
        foreach ($messages as $m) {
            $leads = [];
            foreach (self::people($m) as $p) {
                $hit = $addresses[$p['email']] ?? null;
                // Mail in is from a contact; mail out is to one (the sender is the mailbox itself)
                if ($hit && ($m->is_outbound ? $p['field'] !== 'from' : $p['field'] === 'from')) {
                    $leads[$hit['organization_id']] ??= $hit['contact_id'];
                }
            }
            foreach ($leads as $orgId => $contactId) {
                $lines[] = [$m, (string) $orgId, $contactId, 'mail:' . $orgId . ':' . ($m->message_id_hash ?: 'id' . $m->id)];
            }
        }
        if (! $lines) {
            return 0;
        }
        $have = [];
        foreach (array_chunk(array_values(array_unique(array_column($lines, 3))), 1000) as $keys) {
            $have += DB::table('lead_timeline')->whereIn('dedupe_key', $keys)->pluck('dedupe_key')->flip()->all();
        }
        $lines = array_values(array_filter($lines, fn ($l) => ! isset($have[$l[3]])));
        if (! $lines) {
            return 0;
        }
        $contacts = ClientUser::whereIn('id', array_values(array_unique(array_column($lines, 2))))->get()->keyBy('id');
        $sender = function (object $m) use ($mailboxes): ?string {
            $mailbox = $mailboxes[$m->account_id] ?? null;

            return $m->sent_by ?: ($mailbox?->kind === 'personal' ? $mailbox->owner_user_id : null);
        };
        $staffIds = collect($lines)->filter(fn ($l) => $l[0]->is_outbound)->map(fn ($l) => $sender($l[0]))->filter()->unique()->values()->all();
        $staff = $staffIds ? User::whereIn('id', $staffIds)->where('status', 'Active')->whereNull('deleted_at')->get()->keyBy('id') : collect();

        $written = 0;
        foreach ($lines as [$m, $orgId, $contactId, $key]) {
            // Two copies of one message in the same batch: the first one's line counts
            if (isset($have[$key])) {
                continue;
            }
            $have[$key] = true;
            $contact = $contacts[$contactId] ?? null;
            $mailbox = $mailboxes[$m->account_id] ?? null;
            $by = $m->is_outbound ? $sender($m) : null;
            $actor = $by ? ($staff[$by] ?? null) : null;
            $contactName = $contact ? (trim($contact->first_name . ' ' . $contact->last_name) ?: $contact->email) : 'A contact';
            $ours = $actor ? LeadOwner::name($actor) : ($mailbox?->display_name ?: 'JBS Praxis');
            LeadTimeline::record($orgId, 'email', $m->is_outbound ? "{$ours} emailed {$contactName}" : "{$contactName} emailed {$ours}", [
                'actor' => $m->is_outbound ? $actor : $contact,
                'client_user_id' => $contactId,
                'related_type' => 'mail_message',
                'related_id' => (string) $m->id,
                'at' => $m->received_at ?? $m->sent_at ?? now(),
                'payload' => ['direction' => $m->is_outbound ? 'out' : 'in', 'account_id' => $m->account_id, 'thread_id' => $m->thread_id],
                'dedupe_key' => $key,
            ]);
            $written++;
        }

        return $written;
    }

    /** Every lead's and client's contact address => the contact and its organisation */
    private static function everyLeadsAddresses(): array
    {
        return DB::table('client_users as c')->join('organizations as o', 'o.id', '=', 'c.organization_id')
            ->whereIn('o.status', LeadAccess::STATUSES)->whereNull('o.deleted_at')->whereNull('c.deleted_at')
            ->whereNotNull('c.email')->where('c.email', '!=', '')
            ->get(['c.id', 'c.email', 'c.organization_id'])
            ->mapWithKeys(fn ($c) => [mb_strtolower(trim($c->email)) => ['contact_id' => $c->id, 'organization_id' => $c->organization_id]])->all();
    }

    /** Messages in the folders that count, of these mailboxes (null: all) */
    private static function base(?array $accountIds)
    {
        return DB::table('mail_messages as m')->join('mail_folders as f', 'f.id', '=', 'm.folder_id')
            ->whereNotIn('f.role', self::SKIPPED_FOLDERS)->where('m.is_draft', false)
            ->when($accountIds !== null, fn ($q) => $q->whereIn('m.account_id', $accountIds))
            ->select('m.id', 'm.account_id', 'm.thread_id', 'm.message_id_hash', 'm.subject', 'm.from_name', 'm.from_email', 'm.to', 'm.cc',
                'm.received_at', 'm.sent_at', 'm.snippet', 'm.has_attachments', 'm.is_outbound', 'm.sent_by');
    }

    /** Messages from any of these addresses, or with one of them in To or Cc (to_text narrows; match() decides) */
    private static function matching(array $emails, ?array $accountIds)
    {
        return self::base($accountIds)->where(function ($q) use ($emails) {
            // The column's collation ignores case, so the addresses (lower case) compare as they are and the index is used
            $q->whereIn('m.from_email', $emails);
            foreach ($emails as $email) {
                $q->orWhere('m.to_text', 'like', '%' . addcslashes($email, '%_\\') . '%');
            }
        });
    }

    /** The contact a message is from (mail in) or to (mail out), by exact address; null when only a longer address contained it */
    private static function match(object $m, array $addresses): ?array
    {
        $people = self::people($m);
        foreach ($people as $p) {
            if ($p['field'] === 'from' && ! $m->is_outbound && isset($addresses[$p['email']])) {
                return ['direction' => 'in', 'contact_id' => $addresses[$p['email']], 'email' => $p['email']];
            }
        }
        foreach ($people as $p) {
            if ($p['field'] !== 'from' && isset($addresses[$p['email']])) {
                return ['direction' => $m->is_outbound ? 'out' : 'in', 'contact_id' => $addresses[$p['email']], 'email' => $p['email']];
            }
        }
        // Mail the mailbox itself didn't send, from a contact, seen again in Sent (a copy): mail in
        foreach ($people as $p) {
            if ($p['field'] === 'from' && isset($addresses[$p['email']])) {
                return ['direction' => 'in', 'contact_id' => $addresses[$p['email']], 'email' => $p['email']];
            }
        }

        return null;
    }

    /** @return list<array{field: string, email: string}> from, to and cc, lower case */
    private static function people(object $m): array
    {
        $out = [];
        if (filled($m->from_email)) {
            $out[] = ['field' => 'from', 'email' => mb_strtolower(trim($m->from_email))];
        }
        foreach (['to', 'cc'] as $field) {
            foreach (self::list($m->{$field}) as $a) {
                if (filled($a['email'] ?? null)) {
                    $out[] = ['field' => $field, 'email' => mb_strtolower(trim($a['email']))];
                }
            }
        }

        return $out;
    }

    /** A JSON address list as stored (string from the query builder, array from a model) */
    private static function list(mixed $value): array
    {
        $list = is_string($value) ? json_decode($value, true) : $value;

        return is_array($list) ? array_values(array_filter($list, 'is_array')) : [];
    }
}
