<?php

namespace App\Support\Leads;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Leads and clients that look like the one being typed (owner's decision 4, docs/LEADS.md section 6): the same name
 * (case, spacing, punctuation and "Ltd"-style endings aside, or containing what is typed once it is 4 characters or
 * more), the same email (the organisation's or a contact's), or the same phone (its last 9 digits, so +234 803… and
 * 0803… match). The Add a lead form lists them with "Open it"; saving a new one anyway is allowed. PR 3's import uses
 * the same finder for "Already exists".
 */
final class Duplicates
{
    /** Endings that don't make two organisations different */
    private const NOISE = ['ltd', 'limited', 'plc', 'llc', 'inc', 'sa', 'sarl', 'sas', 'group', 'company', 'co', 'the', 'nigeria', 'cameroun', 'cameroon'];

    /**
     * Up to $limit matches, best first: ['id', 'name', 'status', 'lead_status', 'owner', 'matched' => ['name', 'email', 'phone']].
     * JBS Praxis itself and deleted organisations are never matched; $exceptId leaves the lead itself out.
     */
    public static function find(?string $name, array $emails = [], array $phones = [], ?string $exceptId = null, int $limit = 5): Collection
    {
        $hits = [];
        $add = function (iterable $ids, string $why) use (&$hits) {
            foreach ($ids as $id) {
                $hits[$id][$why] = true;
            }
        };
        $base = fn () => DB::table('organizations')->whereNull('organizations.deleted_at')->where('organizations.status', '!=', 'Internal')
            ->when($exceptId, fn ($q, $id) => $q->where('organizations.id', '!=', $id));

        // Name
        $key = self::nameKey((string) $name);
        if ($key !== '') {
            $typed = trim(preg_replace('/\s+/u', ' ', (string) $name));
            $like = '%' . addcslashes(mb_strtolower($typed), '%_\\') . '%';
            $candidates = $base()->where(function ($q) use ($typed, $like, $key) {
                $q->whereRaw('LOWER(organizations.name) LIKE ?', [$like]);
                // the first word of the key, to catch "Acme Ltd" for "ACME limited" without scanning every row
                $q->orWhereRaw('LOWER(organizations.name) LIKE ?', ['%' . addcslashes(explode(' ', $key)[0], '%_\\') . '%']);
            })->limit(200)->get(['organizations.id', 'organizations.name']);
            $add($candidates->filter(fn ($o) => self::sameName(self::nameKey($o->name), $key) || (mb_strlen($typed) >= 4 && str_contains(mb_strtolower($o->name), mb_strtolower($typed))))->pluck('id'), 'name');
        }

        // Email
        $emails = collect($emails)->map(fn ($e) => mb_strtolower(trim((string) $e)))->filter(fn ($e) => str_contains($e, '@'))->unique()->values();
        if ($emails->isNotEmpty()) {
            // The columns' collation ignores case: compared as they are, client_users.email's index is used
            $add($base()->whereIn('organizations.email', $emails)->pluck('organizations.id'), 'email');
            $add($base()->join('client_users', 'client_users.organization_id', '=', 'organizations.id')->whereNull('client_users.deleted_at')
                ->whereIn('client_users.email', $emails)->pluck('organizations.id'), 'email');
        }

        // Phone: digits only, compared on the last 9
        $keys = collect($phones)->map(fn ($p) => self::phoneKey($p))->filter()->unique()->values();
        if ($keys->isNotEmpty()) {
            $digits = fn (string $col) => "RIGHT(REGEXP_REPLACE(COALESCE({$col}, ''), '[^0-9]', ''), 9)";
            $add($base()->whereIn(DB::raw($digits('organizations.phone')), $keys)->pluck('organizations.id'), 'phone');
            $add($base()->join('client_users', 'client_users.organization_id', '=', 'organizations.id')->whereNull('client_users.deleted_at')
                ->whereIn(DB::raw($digits('client_users.phone')), $keys)->pluck('organizations.id'), 'phone');
        }

        if (! $hits) {
            return collect();
        }

        $rows = DB::table('organizations')->leftJoin('users', 'users.id', '=', 'organizations.assigned_to')
            ->whereIn('organizations.id', array_keys($hits))
            ->get(['organizations.id', 'organizations.name', 'organizations.status', 'organizations.lead_status', 'organizations.country', 'users.first_name', 'users.last_name']);

        return $rows->map(fn ($o) => [
            'id' => $o->id,
            'name' => $o->name,
            'status' => $o->status,
            'lead_status' => $o->lead_status,
            'country' => $o->country,
            'owner' => $o->first_name ? trim($o->first_name . ' ' . $o->last_name) : null,
            'matched' => array_keys($hits[$o->id]),
        ])->sortByDesc(fn ($o) => count($o['matched']) * 10 + (in_array('email', $o['matched'], true) ? 2 : 0) + (in_array('phone', $o['matched'], true) ? 1 : 0))
            ->take($limit)->values();
    }

    /** The same key, or one starts the other at a word ("kilo logistics" and "kilo logistics lagos"), the shorter of 4 characters or more */
    private static function sameName(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        [$short, $long] = mb_strlen($a) <= mb_strlen($b) ? [$a, $b] : [$b, $a];

        return mb_strlen($short) >= 4 && str_starts_with($long, $short . ' ');
    }

    /** "The ACME Ltd." and "acme limited" give the same key: lower case, letters and digits, noise words dropped */
    public static function nameKey(string $name): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($name), -1, PREG_SPLIT_NO_EMPTY);
        $kept = array_values(array_filter($words, fn ($w) => ! in_array($w, self::NOISE, true)));

        return implode(' ', $kept ?: $words);
    }

    /** The last 9 digits of a phone number (enough to tell numbers apart, whatever the country code), or null when shorter than 7 */
    public static function phoneKey(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return strlen($digits) >= 7 ? substr($digits, -9) : null;
    }
}
