<?php

namespace App\Support\Campaigns\Outreach;

use App\Models\Campaigns\OutreachCampaign;
use App\Models\Campaigns\OutreachEmail;
use App\Models\Campaigns\OutreachProspect;
use App\Models\User;
use App\Support\Campaigns\Marketing;
use App\Support\Campaigns\Tracking;
use App\Support\Import\Column;
use App\Support\Import\Group;
use App\Support\Import\ImportSchema;
use App\Support\Import\Option;
use App\Support\Import\Run;
use App\Support\Leads\Countries;
use App\Support\Leads\Duplicates;
use App\Support\Leads\Leads;
use App\Support\MailLocale;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Outreach's Excel import (owner's decisions 5 and 6, 3 Nov 2026), through the shared engine (docs/LEADS.md section
 * 12, PR 3). One prospect per row. Required: company, contact first name, email, country, industry, and at least one
 * of "What we found" or the company website (Claude needs something specific to write about). Optional: contact last
 * name, job title, language (empty: from the country, French for French-speaking countries).
 *
 * "Already exists": a prospect with that address in any campaign (or the one this file's row made), a lead's or
 * client's contact with that address or an organisation with the same name in HQ (Duplicates), or an address on the
 * do-not-email list (Marketing::canEmail). Each new prospect gets its two emails (first and follow-up) waiting for
 * Claude (Drafts).
 */
final class ProspectImportSchema extends ImportSchema
{
    /** The campaign the Outreach page opened the dialog for (the dialog's default choice) */
    public const SESSION_KEY = 'outreach.import_campaign';

    public function key(): string
    {
        return 'prospects';
    }

    public function title(): string
    {
        return 'Import prospects';
    }

    public function noun(): array
    {
        return ['prospect', 'prospects'];
    }

    public function sheetName(): string
    {
        return 'Prospects';
    }

    public function canImport(?User $user): bool
    {
        return Outreach::canCreate($user) && $user && Outreach::importable($user) !== [];
    }

    public function columns(): array
    {
        $countries = array_combine(Countries::NAMES, Countries::NAMES);
        $industries = Leads::industries();

        return [
            new Column('company', 'Company', required: true, width: 28,
                help: 'The prospect’s company, as it writes its own name.',
                example: 'Kilo Logistics Ltd', bad: 'Kilo (big, call later): only the name goes here',
                aliases: ['company name', 'organisation', 'organization', 'organisation name', 'organization name', 'account name', 'entreprise', 'société', 'societe']),
            new Column('first_name', 'Contact first name', required: true, width: 18,
                help: 'The person you’re writing to: the email greets them by it.',
                example: 'Ada', bad: 'Mr A. or Sir: their first name',
                aliases: ['first name', 'firstname', 'given name', 'prénom', 'prenom']),
            new Column('last_name', 'Contact last name', width: 18, help: 'Optional. French emails greet with the full name when it is given.',
                example: 'Obi', aliases: ['last name', 'lastname', 'surname', 'family name', 'nom']),
            new Column('email', 'Email', required: true, width: 28,
                help: 'Their own work address. One prospect per address, across every campaign.',
                example: 'ada.obi@kilo.test', bad: 'info@kilo.test is fine for a small firm, but a person’s address gets replies',
                aliases: ['email address', 'e-mail', 'work email', 'contact email', 'adresse e-mail', 'courriel']),
            new Column('job_title', 'Job title', width: 22, help: 'Optional. Helps Claude pitch the email at their job.',
                example: 'Operations director', aliases: ['title', 'position', 'role', 'poste', 'fonction']),
            new Column('country', 'Country', required: true, width: 18, choices: $countries,
                help: 'Where the company is. Choose from the list, or type another country. It sets the language when the Language column is empty.',
                example: 'Nigeria', bad: 'NG or +234: write the country’s name'),
            new Column('industry', 'Industry', required: true, width: 26, choices: $industries ? array_combine($industries, $industries) : [],
                help: 'What the company does. Choose from the list, or type another.',
                example: 'Logistics & Supply Chain', bad: 'Big company: say what it does', aliases: ['sector', 'secteur']),
            new Column('findings', 'What we found', oneOf: 'research', width: 50,
                help: 'One or two specific things you found about the company: news, a new branch, a job post, a problem they mention. Claude builds the email’s one insight on it. Facts only: Claude repeats it.',
                example: 'Opened a second warehouse in Onne in September; hiring a finance manager to handle month-end closing.',
                bad: 'Good prospect, big company: say what you found',
                aliases: ['research', 'notes', 'insight', 'findings', 'what we know', 'ce que nous avons trouvé']),
            new Column('website', 'Company website', oneOf: 'research', width: 26,
                help: 'Their website’s address. HQ reads its first page’s words for Claude (no other page, nothing behind a sign-in).',
                example: 'kilo.test', bad: 'Kilo’s website: the address itself',
                aliases: ['website', 'web site', 'site', 'url', 'web', 'site web', 'site internet']),
            new Column('language', 'Language', width: 14, choices: ['en' => 'English', 'fr' => 'French'], strict: true,
                help: 'Optional: English or French, the language both emails are written in. Leave it empty and it follows the country: French for French-speaking countries such as Cameroon or Côte d’Ivoire, else English.',
                example: 'French', bad: 'FR-CM: choose from the list', aliases: ['langue', 'lang']),
        ];
    }

    public function guide(): array
    {
        return [
            'Fill in the second sheet, ‘Prospects’: one row per person you want to write to. Claude writes each one a personal email and one follow-up, which you review in HQ before anything is sent.',
            'Columns marked * are required. Fill in at least one of the two columns marked +, What we found or Company website: Claude needs something specific to say about each company. Both is best.',
            'Good: “Opened a second warehouse in Onne in September; hiring a finance manager.” Not like this: “Good prospect”, “Big company”, or a copy of their whole website.',
            'Someone already in HQ (a prospect, a lead or client’s contact, or a company that is a lead or client) isn’t added again, and nobody on the do-not-email list is. Rows that need fixing come back as an Excel file to fix and import again.',
            'Keep row 1 (the headers) as it is. Up to 1,000 rows a file.',
        ];
    }

    public function oneOfText(string $group): string
    {
        return 'At least one of What we found or Company website';
    }

    public function examples(): array
    {
        return [
            ['company' => 'Kilo Logistics Ltd', 'first_name' => 'Ada', 'last_name' => 'Obi', 'email' => 'ada.obi@kilo.test', 'job_title' => 'Operations director',
                'country' => 'Nigeria', 'industry' => 'Logistics & Supply Chain',
                'findings' => 'Opened a second warehouse in Onne in September; hiring a finance manager to handle month-end closing.', 'website' => 'kilo.test'],
            ['company' => 'Lima Freight SARL', 'first_name' => 'Brice', 'last_name' => 'Ngono', 'email' => 'b.ngono@lima.test', 'job_title' => 'Directeur financier',
                'country' => 'Cameroon', 'industry' => 'Logistics & Supply Chain', 'website' => 'https://www.lima.test'],
        ];
    }

    public function options(User $by, string $fileName): array
    {
        $campaigns = Outreach::importable($by);
        $default = session(self::SESSION_KEY);

        return [
            Option::choice('campaign_id', 'Campaign', $campaigns, isset($campaigns[$default]) ? $default : array_key_first($campaigns),
                'The prospects go into this campaign; Claude starts on their emails within a few minutes.'),
        ];
    }

    public function checkOptions(array $options, User $by): array
    {
        $campaign = OutreachCampaign::find($options['campaign_id'] ?? null);

        return $campaign && $campaign->status !== 'archived' && Outreach::canReview($by, $campaign) ? [] : ['campaign_id' => 'Choose one of your campaigns.'];
    }

    public function fill(array $row): array
    {
        // A dialling code typed as the country (+234) becomes the country
        if ($row['country'] !== '' && ($country = Countries::fromDialCode($row['country']))) {
            $row['country'] = $country;
        }
        $row['email'] = Marketing::normalize($row['email']);
        if ($row['website'] !== '' && ($url = Website::normalise($row['website']))) {
            $row['website'] = $url;
        }
        $row['language'] = match (mb_strtolower($row['language'])) {
            'français', 'francais', 'française', 'francaise' => 'fr',
            'anglais', 'eng' => 'en',
            default => $row['language'],
        };
        if ($row['language'] === '' && $row['country'] !== '') {
            $row['language'] = MailLocale::fromCountry($row['country']) ?? 'en';
        }

        return $row;
    }

    public function existing(Group $group, Run $run): ?array
    {
        $line = $group->first();
        $row = $group->rows[$line];
        $email = $row['email'];

        // A prospect this row of this file made, or one with the same address
        $prospect = OutreachProspect::where('dedupe_key', $run->dedupeKey($line))->first()
            ?? ($email !== '' ? OutreachProspect::where('email', $email)->first() : null);
        if ($prospect) {
            $campaign = OutreachCampaign::find($prospect->campaign_id);

            return ['id' => (string) $prospect->id, 'label' => 'Already a prospect in ‘' . ($campaign->name ?? 'another campaign') . '’',
                'url' => $campaign ? route('hq.campaigns.outreach.campaign', ['campaign' => $campaign->id]) : null];
        }
        if (! Marketing::isValid($email)) {
            return null; // check() says what to fix
        }
        if ($entry = Marketing::entry($email)) {
            return ['id' => $email, 'label' => 'On the do-not-email list (' . strtolower(Marketing::REASONS[$entry->reason] ?? $entry->reason) . ')', 'url' => null];
        }

        // A lead's or a client's contact with this address, or a company that is already a lead or client
        $match = Duplicates::find($row['company'] !== '' ? $row['company'] : null, [$email])
            ->first(fn ($m) => in_array('email', $m['matched'], true)
                || (in_array('name', $m['matched'], true) && Duplicates::nameKey($m['name']) === Duplicates::nameKey($row['company'])));
        if ($match) {
            $isLead = $match['status'] === 'Lead';

            return [
                'id' => $match['id'],
                'label' => ($isLead ? 'A lead in HQ: ' : 'In HQ (' . strtolower($match['status']) . '): ') . $match['name'],
                'url' => $isLead ? route('hq.crm.lead', ['organization' => $match['id']]) : null,
            ];
        }

        return null;
    }

    public function check(Group $group, Run $run): void
    {
        if ($group->existing) {
            return;
        }
        foreach ($group->rows as $line => $row) {
            foreach (['company' => 'Company', 'first_name' => 'Contact first name', 'email' => 'Email', 'country' => 'Country', 'industry' => 'Industry'] as $key => $label) {
                if ($row[$key] === '') {
                    $group->fail($line, $key, "Fill in the {$label}.");
                }
            }
            if ($row['email'] !== '' && ! Marketing::isValid($row['email'])) {
                $group->fail($line, 'email', 'This isn’t a full email address, like ada.obi@kilo.test.');
            } elseif ($row['email'] !== '') {
                if (isset($run->seen['emails'][$row['email']]) && $run->seen['emails'][$row['email']] !== $line) {
                    $group->fail($line, 'email', 'Row ' . $run->seen['emails'][$row['email']] . ' has the same email. Each prospect needs their own.');
                } else {
                    $run->seen['emails'][$row['email']] = $line;
                }
            }
            if ($row['findings'] === '' && $row['website'] === '') {
                $group->fail($line, 'findings', 'Fill in What we found or Company website (or both): Claude needs something specific to say about the company.');
                $group->fail($line, 'website', 'Fill in What we found or Company website (or both): Claude needs something specific to say about the company.');
            }
            if ($row['website'] !== '' && Website::normalise($row['website']) === null) {
                $group->fail($line, 'website', 'Write the website’s address, like kilo.test or https://kilo.test.');
            }
            if ($row['language'] !== '' && ! in_array($row['language'], ['en', 'fr'], true)) {
                $group->fail($line, 'language', 'Choose English or French, or leave it empty to follow the country.');
            }
            if (str_contains($row['first_name'], '@') || mb_strlen($row['first_name']) > 100) {
                $group->fail($line, 'first_name', 'Write only their first name.');
            }
            foreach (['company' => 190, 'last_name' => 100, 'job_title' => 150, 'country' => 100, 'industry' => 150, 'findings' => 2000] as $key => $max) {
                if (mb_strlen($row[$key]) > $max) {
                    $group->fail($line, $key, 'Keep this under ' . number_format($max) . ' characters.');
                }
            }
        }
    }

    public function create(Group $group, Run $run): ?string
    {
        $line = $group->first();
        $row = $group->rows[$line];
        $campaignId = (string) $run->option('campaign_id');

        try {
            return $this->insert($row, $line, $run, $campaignId);
        } catch (UniqueConstraintViolationException) {
            // Imported a moment ago by someone else (another file, another person)
            $group->failAll('This email became a prospect while you were importing. Look for it in Outreach.', 'email');

            return null;
        }
    }

    private function insert(array $row, int $line, Run $run, string $campaignId): string
    {
        return DB::transaction(function () use ($row, $line, $run, $campaignId) {
            $now = now();
            $prospect = OutreachProspect::create([
                'campaign_id' => $campaignId,
                'company' => $row['company'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'] !== '' ? $row['last_name'] : null,
                'job_title' => $row['job_title'] !== '' ? $row['job_title'] : null,
                'email' => $row['email'],
                'country' => $row['country'],
                'industry' => $row['industry'],
                'language' => $row['language'] === 'fr' ? 'fr' : 'en',
                'findings' => $row['findings'] !== '' ? $row['findings'] : null,
                'website' => $row['website'] !== '' ? $row['website'] : null,
                'dedupe_key' => $run->dedupeKey($line),
                'imported_by' => $run->by?->id,
            ]);
            foreach (['first', 'follow_up'] as $kind) {
                OutreachEmail::create([
                    'campaign_id' => $campaignId, 'prospect_id' => $prospect->id, 'kind' => $kind, 'status' => 'writing',
                    'token' => Tracking::newToken(), 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            return (string) $prospect->id;
        });
    }
}
