<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Mail\Mailables\Content;

/**
 * Cost of poor quality report: the estimate and the private report link. Sent from the calculator on
 * jbspraxis.com (through the site API) or re-sent from HQ (Live consultation, $fromHq).
 */
class CopqReportMail extends BrandedMail
{
    public $assessment;
    public $fromHq;

    /** $assessment is the copq_assessments row (company_name, executive_name, copq_currency, calculated_leakage, report_token, created_at). */
    public function __construct($assessment, bool $fromHq = false)
    {
        $this->assessment = $assessment;
        $this->fromHq = $fromHq;
    }

    protected function subjectLine(): string
    {
        $company = $this->company();

        return $company === null
            ? __('emails.copq.subject_plain')
            : __('emails.copq.subject', ['company' => $company]);
    }

    public function content(): Content
    {
        $locale = app()->getLocale();
        $prepared = Carbon::parse($this->assessment->created_at ?? now(), 'UTC')->setTimezone('Africa/Lagos');

        // The report page on the site, in the email's language (the portal's dossier.show is HQ's internal view)
        $reportUrl = rtrim(route('home'), '/') . ($locale === 'fr' ? '/fr' : '') . '/dossier/' . rawurlencode((string) $this->assessment->report_token);

        return new Content(view: 'emails.copq-report', with: [
            'reportUrl' => $reportUrl,
            'company' => $this->company(),
            'firstName' => $this->firstName(),
            'amount' => self::money((float) ($this->assessment->calculated_leakage ?? 0), $this->assessment->copq_currency ?? null, $locale),
            'preparedOn' => $prepared->locale($locale)->isoFormat(__('emails.common.date_format', [], $locale)),
        ]);
    }

    public static function samples(): array
    {
        $base = [
            'id' => 'sample-assessment',
            'report_token' => 'PRX-SAMPLE0DEMO0TOKEN0000000-2026',
            'organization_id' => 'sample-organisation',
            'executive_name' => 'Ada Okafor',
            'company_name' => 'Acme Ltd (demo)',
            'corporate_email' => 'ada.okafor@example.com',
            'copq_industry' => 'Logistics',
            'copq_currency' => 'NGN',
            'copq_turnover' => 2500000000,
            'calculated_leakage' => 186450000.40,
            'created_at' => '2026-10-29 14:05:00',
        ];

        return [
            'website' => new static((object) $base),
            'hq' => new static((object) array_merge($base, [
                'executive_name' => 'Ada Okafor',
                'copq_currency' => 'XAF',
                'calculated_leakage' => 312500000,
            ]), true),
            'minimal' => new static((object) array_merge($base, [
                'executive_name' => 'Executive Board',
                'company_name' => '',
                'copq_currency' => 'USD',
                'calculated_leakage' => 248300.75,
            ])),
        ];
    }

    /** The organisation's name, or null when the form left it blank. */
    private function company(): ?string
    {
        $company = trim((string) ($this->assessment->company_name ?? ''));

        return $company === '' ? null : $company;
    }

    /** First word of the contact's name; null for the code's stand-ins ("Executive", "Executive Board"). */
    private function firstName(): ?string
    {
        $first = strtok(trim((string) ($this->assessment->executive_name ?? '')), ' ');

        return $first === false || $first === '' || strcasecmp($first, 'Executive') === 0 ? null : $first;
    }

    /**
     * An amount per docs/BRAND.md section 4, whole units: EN "₦186,450,000", "312,500,000 FCFA", "US$248,301";
     * FR "186 450 000 ₦", "312 500 000 FCFA" (narrow no-break spaces). Other currencies use the intl defaults.
     */
    public static function money(float $amount, ?string $currency, string $locale): string
    {
        $fr = $locale === 'fr';
        $code = strtoupper(trim((string) $currency)) ?: 'USD';
        $amount = round($amount);

        $number = class_exists(\NumberFormatter::class)
            ? (new \NumberFormatter($fr ? 'fr_FR' : 'en_GB', \NumberFormatter::DECIMAL))->format($amount)
            : number_format($amount, 0, $fr ? ',' : '.', $fr ? "\u{202F}" : ',');
        $number = str_replace("\u{00A0}", "\u{202F}", $number);

        if ($code === 'NGN') {
            return $fr ? "{$number}\u{202F}₦" : "₦{$number}";
        }
        if ($code === 'XAF' || $code === 'XOF') {
            return $number . ($fr ? "\u{202F}" : "\u{00A0}") . 'FCFA';
        }
        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter($fr ? 'fr_FR' : 'en_GB', \NumberFormatter::CURRENCY);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);
            $formatted = $formatter->formatCurrency($amount, $code);
            if ($formatted !== false) {
                return $fr ? str_replace("\u{00A0}", "\u{202F}", $formatted) : $formatted;
            }
        }

        return $fr ? "{$number}\u{202F}{$code}" : "{$code}\u{00A0}{$number}";
    }
}
