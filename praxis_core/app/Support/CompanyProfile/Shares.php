<?php

namespace App\Support\CompanyProfile;

use App\Models\CompanyProfile\ProfileShareLink;
use App\Models\CompanyProfile\ProfileShareOpen;
use App\Models\CompanyProfile\ProfileVersion;
use App\Models\User;
use App\Support\Hosts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Tracked share links for the company profile (docs/COMPANY_PROFILE.md section 14).
 *
 * A link is a long random token that opens the profile PDF on jbspraxis.com with no sign-in. Three things
 * matter and are easy to get wrong:
 *
 * - **The token is the only secret.** It is 48 random characters and never the version number, so a link
 *   cannot be guessed and nobody can walk the versions by changing a digit.
 * - **A link that has ended never serves the PDF.** Revoked or past its expiry, it answers with the ended
 *   page and nothing else; the check is here, once, so the public route cannot forget it.
 * - **The record says somebody opened it, not who.** The visitor is stored as a hash of the address and the
 *   app key, never the address, and the user agent is reduced to a browser and a platform.
 *
 * The PDF comes through the same renderer as the HQ download, so the held/enabled and naming rules of
 * company-profile/CONTRACT.md hold on a share link exactly as they do everywhere else.
 */
final class Shares
{
    /** How long after an open the same visitor is not counted or belled again */
    public const REPEAT_WINDOW_MINUTES = 30;

    /** Opens of one token in an hour before the public route answers "too many" */
    public const OPENS_PER_HOUR = 120;

    public const TOKEN_LENGTH = 48;

    // ------------------------------------------------------------------ making and ending links

    /** @param  array<string, mixed>  $attributes */
    public static function create(array $attributes, User $by): ProfileShareLink
    {
        $locale = Sections::isLocale((string) ($attributes['locale'] ?? 'en')) ? $attributes['locale'] : 'en';

        $version = null;
        if (! empty($attributes['version_id'])) {
            $version = ProfileVersion::find($attributes['version_id']);
            if (! $version) {
                throw new RuntimeException('That version of the profile is not there.');
            }
        }

        $link = ProfileShareLink::create([
            'token' => self::newToken(),
            'version_id' => $version?->id,
            'locale' => $locale,
            'recipient_name' => self::blankToNull($attributes['recipient_name'] ?? null, 150),
            'recipient_company' => self::blankToNull($attributes['recipient_company'] ?? null, 200),
            'expires_at' => $attributes['expires_at'] ?? null,
            'created_by' => $by->id,
        ]);

        Profile::audit(
            'COMPANY_PROFILE_SHARED',
            'Made a share link for ' . $link->forWhom() . ' (' . strtoupper($link->locale) . ')',
            $version,
        );

        return $link;
    }

    public static function revoke(ProfileShareLink $link, User $by): ProfileShareLink
    {
        if ($link->revoked_at !== null) {
            return $link;
        }

        $link->forceFill(['revoked_at' => now(), 'revoked_by' => $by->id])->save();

        Profile::audit(
            'COMPANY_PROFILE_SHARE_REVOKED',
            'Revoked the share link for ' . $link->forWhom(),
            $link->version,
        );

        return $link;
    }

    /** 48 random characters, and never one that is already out there */
    public static function newToken(): string
    {
        do {
            $token = Str::random(self::TOKEN_LENGTH);
        } while (ProfileShareLink::query()->where('token', $token)->exists());

        return $token;
    }

    public static function url(string $token): string
    {
        return 'https://' . Hosts::site() . '/profile/' . $token;
    }

    // ------------------------------------------------------------------ opening one

    /**
     * The link a token names, whatever state it is in, or null when the token is not one we gave out. The
     * shape of the token is checked first, so a wild string never reaches the database.
     */
    public static function find(string $token): ?ProfileShareLink
    {
        if (! preg_match('/^[A-Za-z0-9]{' . self::TOKEN_LENGTH . '}$/', $token)) {
            return null;
        }

        return ProfileShareLink::query()->where('token', $token)->first();
    }

    /**
     * The profile this link opens, in its own language: the version it was pinned to, or whatever is
     * published now. Raises MissingProfile when there is nothing published and nothing pinned.
     *
     * @return array<string, mixed>
     */
    public static function data(ProfileShareLink $link): array
    {
        $version = $link->version_id ? ProfileVersion::find($link->version_id) : null;

        return $version
            ? Profile::dataFor($version, $link->locale)
            : Profile::data($link->locale);
    }

    /** "JBS Praxis company profile JBS-CP-2026-11-v1 EN.pdf" */
    public static function filename(array $data, string $locale): string
    {
        $reference = str_replace(['/', '\\', '"'], '-', (string) ($data['meta']['reference'] ?? 'company-profile'));

        return 'JBS Praxis company profile ' . $reference . ' ' . strtoupper($locale) . '.pdf';
    }

    /**
     * Counts an open and rings the bell for whoever made the link, unless the same visitor opened it a few
     * minutes ago: a PDF viewer that asks twice, or somebody scrolling back, is one open, not three.
     *
     * Returns true when this open was counted.
     */
    public static function record(ProfileShareLink $link, ?string $agent, ?string $ip): bool
    {
        $visitor = self::visitorHash($ip);

        $recently = ProfileShareOpen::query()
            ->where('share_link_id', $link->id)
            ->where('visitor', $visitor)
            ->where('created_at', '>=', now()->subMinutes(self::REPEAT_WINDOW_MINUTES))
            ->exists();

        if ($recently) {
            return false;
        }

        ProfileShareOpen::create([
            'share_link_id' => $link->id,
            'visitor' => $visitor,
            'agent' => self::coarseAgent($agent),
            'created_at' => now(),
        ]);

        $link->forceFill([
            'opens' => $link->opens + 1,
            'last_opened_at' => now(),
        ])->save();

        self::bell($link);

        return true;
    }

    /** Counts this open against the token's own hourly allowance, and says whether it is one too many */
    public static function tooMany(ProfileShareLink $link): bool
    {
        $opens = ProfileShareOpen::query()
            ->where('share_link_id', $link->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return $opens >= self::OPENS_PER_HOUR;
    }

    /**
     * The address, hashed with the app key so the same visitor is recognised within the repeat window and
     * nothing anywhere stores where they were. An unknown address still hashes, to its own bucket.
     */
    public static function visitorHash(?string $ip): string
    {
        return hash('sha256', (string) config('app.key') . '|profile-share|' . (string) $ip);
    }

    /**
     * The user agent, reduced to what is worth knowing: "Chrome on Windows". Never the whole string, which
     * carries versions and build numbers that together name a device.
     */
    public static function coarseAgent(?string $agent): ?string
    {
        $agent = trim((string) $agent);
        if ($agent === '') {
            return null;
        }

        $browsers = [
            'Edg/' => 'Edge', 'OPR/' => 'Opera', 'Firefox/' => 'Firefox',
            'Chrome/' => 'Chrome', 'Safari/' => 'Safari',
        ];
        $platforms = [
            'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Android' => 'Android', 'Windows' => 'Windows',
            'Mac OS X' => 'Mac', 'Macintosh' => 'Mac', 'Linux' => 'Linux',
        ];

        $browser = 'A browser';
        foreach ($browsers as $needle => $name) {
            if (str_contains($agent, $needle)) {
                $browser = $name;
                break;
            }
        }

        foreach ($platforms as $needle => $name) {
            if (str_contains($agent, $needle)) {
                return $browser . ' on ' . $name;
            }
        }

        return $browser;
    }

    /** The HQ bell, for the person who made the link */
    private static function bell(ProfileShareLink $link): void
    {
        $active = DB::table('users')->where('id', $link->created_by)->where('status', 'Active')
            ->whereNull('deleted_at')->exists();
        if (! $active) {
            return;
        }

        rescue(fn () => DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $link->created_by,
            'title' => Str::limit('Company profile opened', 150, '…'),
            'message' => 'Your share link for ' . $link->forWhom() . ' was opened.',
            'link_url' => route('hq.company-profile.shares'),
            // notifications.type is an enum (System, Financial, Project, Message, QA_Update); anything else
            // makes the insert fail silently inside the rescue below and the bell never rings
            'type' => 'System',
            'is_read' => 0,
            'created_at' => now(),
        ]), null, false);
    }

    private static function blankToNull(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, $limit, '');
    }
}
