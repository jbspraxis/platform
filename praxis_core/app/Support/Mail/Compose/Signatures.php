<?php

namespace App\Support\Mail\Compose;

use App\Models\Mail\MailAccount;
use App\Models\User;
use App\Support\Mail\MailSettings;
use App\Support\WebsiteSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Email signatures (owner decision 5): one company design, built from HR records. The person's name and job
 * title come from their user and employee records (no department line: roles are access, not departments,
 * owner 30 Oct 2026), the phone from the
 * HR record unless they set their own numbers (mail_signatures, the only part a person edits). Logo and
 * company details come from mail_settings `signature.company`, over defaults from the website settings.
 *
 * Added when the message is sent (MessageBuilder): 'full' on new mail and forwards, 'slim' on replies, or
 * 'none'. On a shared mailbox the signature shows the person and the shared address, never their own.
 * Colours are literal hex from docs/BRAND.md section 13 (CSS variables do not work in email).
 */
final class Signatures
{
    public const STYLES = ['full' => 'Full', 'slim' => 'Short', 'none' => 'None'];

    /** Digits, spaces, brackets, dots and dashes, with an optional + in front (as WebsiteSettings) */
    public const PHONE = '/^\+?[0-9][0-9 ().\-]{4,22}[0-9]$/';

    /** @return array{name: string, descriptor: ?string, website: ?string, logo_url: ?string, show_logo: bool, address: ?string, phone: ?string} */
    public static function company(): array
    {
        $site = rescue(fn () => WebsiteSettings::all(), [], false);
        $cities = collect(data_get($site, 'company.offices', []))->pluck('city')->filter()->values()->all();
        $defaults = [
            'name' => (string) (data_get($site, 'company.trading_name') ?: 'JBS Praxis'),
            'descriptor' => 'Consulting and engineering for Africa’s institutions',
            'website' => (string) config('mail.brand.site_url', 'https://jbspraxis.com'),
            'logo_url' => (string) config('mail.brand.logo'),
            'show_logo' => true,
            'address' => $cities ? implode(' and ', array_filter([implode(', ', array_slice($cities, 0, -1)), end($cities)])) : null,
            'phone' => collect(data_get($site, 'contact.phones', []))->pluck('number')->filter()->first(),
        ];
        $saved = (array) MailSettings::get('signature.company', []);

        return array_merge($defaults, array_intersect_key($saved, $defaults));
    }

    /** Save the company settings (people with Shared mailboxes: Create). Throws ValidationException. */
    public static function setCompany(array $values, User $by): array
    {
        $values = array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $values);
        $data = Validator::make($values, [
            'name' => ['required', 'string', 'max:80'],
            'descriptor' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'url:https,http', 'max:120'],
            'logo_url' => ['nullable', 'url:https', 'max:300'],
            'show_logo' => ['boolean'],
            'address' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'regex:' . self::PHONE],
        ], [
            'name.required' => 'Enter the company name.',
            'website.url' => 'Enter the website as a full address, starting with https://.',
            'logo_url.url' => 'Enter the logo’s full address, starting with https://.',
            'phone.regex' => 'Enter the phone number with digits and spaces, for example +234 800 000 0000.',
        ], ['logo_url' => 'logo address'])->validate();
        $data['show_logo'] = (bool) ($data['show_logo'] ?? false);

        MailSettings::set('signature.company', $data, $by->id);

        return self::company();
    }

    /** @return array{phone: ?string, mobile: ?string} the person's own numbers (empty when they never set them) */
    public static function numbers(User $user): array
    {
        $row = DB::table('mail_signatures')->where('user_id', $user->id)->first();

        return ['phone' => $row->phone ?? null, 'mobile' => $row->mobile ?? null];
    }

    /** A person changes their own numbers. Throws ValidationException. */
    public static function setNumbers(User $user, ?string $phone, ?string $mobile): void
    {
        $data = Validator::make(['phone' => self::blank($phone), 'mobile' => self::blank($mobile)], [
            'phone' => ['nullable', 'regex:' . self::PHONE],
            'mobile' => ['nullable', 'regex:' . self::PHONE],
        ], [
            'phone.regex' => 'Enter the office number with digits and spaces, for example +234 800 000 0000.',
            'mobile.regex' => 'Enter the mobile number with digits and spaces, for example +234 800 000 0000.',
        ])->validate();

        DB::table('mail_signatures')->upsert(
            [['user_id' => $user->id, 'phone' => $data['phone'], 'mobile' => $data['mobile'], 'created_at' => now(), 'updated_at' => now()]],
            ['user_id'],
            ['phone', 'mobile', 'updated_at'],
        );
    }

    /**
     * What HR knows about the person, for the signature.
     *
     * @return array{name: string, title: ?string, department: ?string, phone: ?string, mobile: ?string}
     */
    public static function person(User $user): array
    {
        $employee = DB::table('mdm_employees')->where('user_id', $user->id)->where('is_active', 1)->orderByDesc('updated_at')->first();
        $own = self::numbers($user);

        return [
            'name' => trim((string) $user->name) ?: (string) $user->email,
            'title' => self::blank($user->job_title) ?? self::blank($employee->job_classification ?? null),
            'department' => null, // signatures show the job title only (owner, 30 Oct 2026)
            'phone' => $own['phone'] ?? self::blank($user->phone),
            'mobile' => $own['mobile'],
        ];
    }

    /** The signature as HTML, ready to add below a message ('' for 'none') */
    public static function html(User $user, MailAccount $account, string $style = 'full'): string
    {
        if ($style === 'none') {
            return '';
        }
        $p = self::person($user);
        $c = self::company();
        $email = $account->email;
        $font = "font-family:'Plus Jakarta Sans',Arial,sans-serif;";
        $role = implode(' · ', array_filter([$p['title'], $p['department']]));
        $numbers = array_filter([
            $p['phone'] ? '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $p['phone'])) . '" style="color:#4A5468;text-decoration:none;">' . e($p['phone']) . '</a>' : null,
            $p['mobile'] ? '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $p['mobile'])) . '" style="color:#4A5468;text-decoration:none;">' . e($p['mobile']) . '</a>' : null,
        ]);
        $mail = '<a href="mailto:' . e($email) . '" style="color:#08707C;text-decoration:none;">' . e($email) . '</a>';
        $site = $c['website'] ? '<a href="' . e($c['website']) . '" style="color:#08707C;text-decoration:none;">' . e(preg_replace('~^https?://(www\.)?~i', '', rtrim($c['website'], '/'))) . '</a>' : null;

        if ($style === 'slim') {
            $line2 = implode(' · ', array_filter([e($c['name']), ...$numbers, $mail]));

            return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="' . $font . 'font-size:13px;line-height:20px;color:#4A5468;margin-top:16px;">'
                . '<tr><td style="' . $font . 'color:#0C142B;font-weight:600;">' . e($p['name']) . ($role !== '' ? '<span style="font-weight:400;color:#5E6679;"> · ' . e($role) . '</span>' : '') . '</td></tr>'
                . '<tr><td style="' . $font . '">' . $line2 . '</td></tr></table>';
        }

        $rows = '<tr><td style="' . $font . 'font-size:15px;line-height:22px;font-weight:600;color:#0C142B;">' . e($p['name']) . '</td></tr>';
        if ($role !== '') {
            $rows .= '<tr><td style="' . $font . 'font-size:13px;line-height:20px;color:#4A5468;">' . e($role) . '</td></tr>';
        }
        // The Praxis rule: Ink then Teal, two table cells (docs/BRAND.md section 13)
        $rows .= '<tr><td style="padding:10px 0;"><table role="presentation" cellpadding="0" cellspacing="0" border="0" width="240" style="width:240px;"><tr>'
            . '<td width="120" height="2" bgcolor="#0C142B" style="font-size:0;line-height:0;height:2px;">&nbsp;</td>'
            . '<td width="120" height="2" bgcolor="#0C8B9B" style="font-size:0;line-height:0;height:2px;">&nbsp;</td></tr></table></td></tr>';
        if ($c['show_logo'] && $c['logo_url']) {
            $rows .= '<tr><td style="padding:0 0 8px;"><img src="' . e($c['logo_url']) . '" width="132" height="36" alt="' . e($c['name']) . '" style="display:block;width:132px;height:36px;border:0;' . $font . 'font-size:15px;font-weight:600;color:#0C142B;"></td></tr>';
        } else {
            $rows .= '<tr><td style="' . $font . 'font-size:14px;line-height:20px;font-weight:600;color:#0C142B;padding:0 0 4px;">' . e($c['name']) . '</td></tr>';
        }
        $contact = array_filter([...$numbers, $mail, $site]);
        $rows .= '<tr><td style="' . $font . 'font-size:13px;line-height:20px;color:#4A5468;">' . implode(' · ', $contact) . '</td></tr>';
        $foot = array_filter([$c['descriptor'], $c['address'], ! $p['phone'] && ! $p['mobile'] ? $c['phone'] : null]);
        if ($foot) {
            $rows .= '<tr><td style="' . $font . 'font-size:12px;line-height:18px;color:#5E6679;padding-top:2px;">' . implode(' · ', array_map('e', $foot)) . '</td></tr>';
        }

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="' . $font . 'margin-top:20px;">' . $rows . '</table>';
    }

    /** The same signature as plain text ('' for 'none'); starts with the "-- " line mail apps recognise */
    public static function text(User $user, MailAccount $account, string $style = 'full'): string
    {
        if ($style === 'none') {
            return '';
        }
        $p = self::person($user);
        $c = self::company();
        $role = implode(' · ', array_filter([$p['title'], $p['department']]));
        $numbers = array_filter([$p['phone'], $p['mobile']]);

        if ($style === 'slim') {
            return "-- \n" . $p['name'] . ($role !== '' ? " · {$role}" : '') . "\n" . implode(' · ', array_filter([$c['name'], ...$numbers, $account->email]));
        }

        $lines = array_filter([
            $p['name'],
            $role ?: null,
            $c['name'],
            implode(' · ', array_filter([...$numbers, $account->email, $c['website'] ? preg_replace('~^https?://(www\.)?~i', '', rtrim($c['website'], '/')) : null])),
            implode(' · ', array_filter([$c['descriptor'], $c['address'], ! $numbers ? $c['phone'] : null])) ?: null,
        ]);

        return "-- \n" . implode("\n", $lines);
    }

    private static function blank(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
