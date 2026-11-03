<?php

namespace App\Support\Leads;

/**
 * Where a lead came from (organizations.source), with a free line beside it (source_detail: who referred them, which
 * event). The website's three forms set theirs; a lead added in HQ starts as "Added in HQ" or what the form chose;
 * PR 3's Excel import sets "import" or the source chosen in its modal. Editable on the lead page.
 */
final class Sources
{
    public const LABELS = [
        'website_enquiry' => 'Website enquiry',
        'website_booking' => 'Website free audit booking',
        'cost_report' => 'Cost calculator on the website',
        'manual' => 'Added in HQ',
        'import' => 'Excel import',
        'referral' => 'Referral',
        'event' => 'Event',
        'outreach' => 'Our own outreach',
        'social' => 'Social media',
        'partner' => 'Partner',
        'other' => 'Other',
    ];

    /** Set by the website's forms (App\Services\Site): their leads go to the default owner */
    public const WEBSITE = ['website_enquiry', 'website_booking', 'cost_report'];

    /** What someone adding a lead by hand may pick (the website's own and the import are set by those) */
    public const BY_HAND = ['manual', 'referral', 'event', 'outreach', 'social', 'partner', 'other'];

    public static function label(?string $source): string
    {
        return self::LABELS[(string) $source] ?? 'Not recorded';
    }

    public static function isWebsite(?string $source): bool
    {
        return in_array($source, self::WEBSITE, true);
    }
}
