<?php

namespace App\Support\Mail\Compose;

use App\Models\User;
use App\Support\Mail\MailAccess;
use App\Support\Mail\Mime\Headers;
use Illuminate\Support\Facades\DB;

/**
 * Addresses in the composer: reading what a person typed or pasted ("Ada <ada@x.com>, bob@y.com"), and
 * suggestions while they type: HQ staff, then people in mail they can read (MailAccess), most recent first.
 */
final class Recipients
{
    /**
     * @param  string|list<string|array{name?: ?string, email: string}>  $input
     * @return array{valid: list<array{name: ?string, email: string}>, invalid: list<string>}
     */
    public static function parse(string|array $input): array
    {
        $items = is_array($input) ? $input : preg_split('/[,;\n]+(?=(?:[^"]*"[^"]*")*[^"]*$)/', $input);
        $valid = [];
        $invalid = [];
        $seen = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $one = ['name' => self::name($item['name'] ?? null), 'email' => strtolower(trim((string) ($item['email'] ?? '')))];
            } else {
                $item = trim((string) $item);
                if ($item === '') {
                    continue;
                }
                $parsed = Headers::addresses($item)[0] ?? null;
                $one = $parsed ? ['name' => self::name($parsed['name'] ?? null), 'email' => strtolower(trim((string) $parsed['email']))] : ['name' => null, 'email' => $item];
            }
            if (! filter_var($one['email'], FILTER_VALIDATE_EMAIL)) {
                $invalid[] = is_array($item) ? (string) ($item['email'] ?? '') : $item;
                continue;
            }
            if (isset($seen[$one['email']])) {
                continue;
            }
            $seen[$one['email']] = true;
            $valid[] = $one;
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    /** "Ada Client <ada@client.com>" or the bare address */
    public static function format(array $address): string
    {
        $name = self::name($address['name'] ?? null);

        return $name ? "{$name} <{$address['email']}>" : (string) $address['email'];
    }

    /** @param  list<array{name: ?string, email: string}>  $list  everyone in $list not in $without (by address) */
    public static function without(array $list, array $without): array
    {
        $drop = array_map('strtolower', array_map(fn ($a) => is_array($a) ? $a['email'] : $a, $without));

        return array_values(array_filter($list, fn ($a) => ! in_array(strtolower($a['email']), $drop, true)));
    }

    /** @return list<array{name: ?string, email: string, kind: 'staff'|'contact'}> */
    public static function suggest(User $user, string $query, ?int $limit = null): array
    {
        $limit ??= (int) config('mail_compose.suggestions', 8);
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }
        $like = '%' . addcslashes($query, '%_\\') . '%';
        $out = [];

        $staff = DB::table('users')->where('status', 'Active')->whereNull('deleted_at')
            ->where(fn ($q) => $q->where('email', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", [$like]))
            ->orderBy('first_name')->limit($limit)->get(['first_name', 'last_name', 'email']);
        foreach ($staff as $s) {
            $out[strtolower($s->email)] = ['name' => trim($s->first_name . ' ' . $s->last_name) ?: null, 'email' => strtolower($s->email), 'kind' => 'staff'];
        }

        $accountIds = MailAccess::accountIds($user, 'read');
        if ($accountIds && count($out) < $limit) {
            $contacts = DB::table('mail_messages')->whereIn('account_id', $accountIds)->whereNotNull('from_email')
                ->where('is_outbound', false)
                ->where(fn ($q) => $q->where('from_email', 'like', $like)->orWhere('from_name', 'like', $like))
                ->groupBy('from_email')
                ->orderByRaw('MAX(received_at) DESC')
                ->limit($limit * 2)
                ->get([DB::raw('from_email'), DB::raw('MAX(from_name) as from_name')]);
            foreach ($contacts as $c) {
                $email = strtolower($c->from_email);
                if (! isset($out[$email]) && filter_var($email, FILTER_VALIDATE_EMAIL) && ! str_contains($email, 'noreply') && ! str_contains($email, 'no-reply')) {
                    $out[$email] = ['name' => self::name($c->from_name), 'email' => $email, 'kind' => 'contact'];
                }
            }
        }

        return array_slice(array_values($out), 0, $limit);
    }

    private static function name(?string $name): ?string
    {
        $name = trim(str_replace(['"', "\r", "\n", '<', '>'], '', (string) $name));

        return $name === '' ? null : mb_substr($name, 0, 120);
    }
}
