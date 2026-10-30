<?php

namespace App\Support;

/**
 * The public Website settings (GET /settings, docs/SITE_API.md section 4) shaped for the header and footer.
 * Tolerant of missing keys: the footer always renders, with the fallback email when HQ was never reached.
 * A registration whose status is "pending" (or has no number) is shown as "registration in progress".
 */
final class SiteSettings
{
    /**
     * @param  list<array{city: string, country: string}>  $offices
     * @param  list<array{label: string, country: string, number: ?string}>  $registrations
     * @param  list<array{label: string, number: string}>  $phones
     * @param  list<array{label: string, number: string}>  $whatsapp
     * @param  list<array{network: string, label: string, url: string}>  $social
     */
    private function __construct(
        public readonly string $companyName,
        public readonly ?string $legalName,
        public readonly string $email,
        public readonly array $offices,
        public readonly array $registrations,
        public readonly array $phones,
        public readonly array $whatsapp,
        public readonly array $social,
        public readonly bool $loaded,
    ) {
    }

    public static function fromApi(?array $data, string $fallbackEmail): self
    {
        $data ??= [];
        $company = is_array($data['company'] ?? null) ? $data['company'] : [];
        $contact = is_array($data['contact'] ?? null) ? $data['contact'] : [];

        $trading = self::str($company['trading_name'] ?? null);
        $legal = self::str($company['legal_name'] ?? null);

        $offices = [];
        foreach (self::list($company['offices'] ?? []) as $office) {
            $city = self::str($office['city'] ?? null);
            if ($city !== null) {
                $offices[] = ['city' => $city, 'country' => self::country($office['country'] ?? null)];
            }
        }

        $registrations = [];
        foreach (self::list($company['registrations'] ?? []) as $reg) {
            $label = self::str($reg['label'] ?? null);
            if ($label === null) {
                continue;
            }
            $number = self::str($reg['number'] ?? null);
            $pending = ($reg['status'] ?? null) === 'pending' || $number === null;
            $registrations[] = [
                'label' => $label,
                'country' => self::country($reg['country'] ?? null),
                'number' => $pending ? null : $number,
            ];
        }

        $numbers = function (string $key) use ($contact): array {
            $out = [];
            foreach (self::list($contact[$key] ?? []) as $phone) {
                $number = self::str($phone['number'] ?? null);
                if ($number !== null) {
                    $out[] = ['label' => self::str($phone['label'] ?? null) ?? '', 'number' => $number];
                }
            }

            return $out;
        };

        $social = [];
        // Instagram isn't a Website settings field yet: config('site.instagram_url') until it is
        foreach (['linkedin', 'x', 'youtube', 'instagram'] as $network) {
            $url = self::str($data['social'][$network] ?? ($network === 'instagram' ? config('site.instagram_url') : null));
            if ($url !== null && preg_match('~^https://~i', $url)) {
                $social[] = ['network' => $network, 'label' => __('site.social.' . $network), 'url' => $url];
            }
        }

        $email = self::str($contact['email'] ?? null);

        return new self(
            companyName: $trading ?? $legal ?? 'JBS Praxis',
            legalName: $legal,
            email: $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : $fallbackEmail,
            offices: $offices,
            registrations: $registrations,
            phones: $numbers('phones'),
            whatsapp: $numbers('whatsapp'),
            social: $social,
            loaded: $data !== [],
        );
    }

    /**
     * Registrations that have a number. A registration still in progress is not shown on the public pages
     * (owner, 30 Oct 2026: "registration in progress" in the footer doesn't look professional).
     *
     * @return list<array{label: string, country: string, number: string}>
     */
    public function registered(): array
    {
        return array_values(array_filter($this->registrations, fn (array $registration) => $registration['number'] !== null));
    }

    private static function str(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @return list<array<string, mixed>> */
    private static function list(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    private static function country(mixed $code): string
    {
        $code = strtoupper((string) self::str($code));
        if ($code === '') {
            return '';
        }
        $key = 'site.countries.' . $code;
        $name = __($key);

        return $name === $key ? $code : $name;
    }
}
