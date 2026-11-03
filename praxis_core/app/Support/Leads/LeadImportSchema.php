<?php

namespace App\Support\Leads;

use App\Models\Organization;
use App\Models\User;
use App\Support\Import\Column;
use App\Support\Import\Group;
use App\Support\Import\ImportSchema;
use App\Support\Import\Option;
use App\Support\Import\Run;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Leads' Excel import (owner's decisions 5–7, docs/LEADS.md sections 6 and 9), the first schema of the shared
 * import engine. One row per contact; rows with the same organisation name are one lead. The rules are Add a lead's
 * (Leads::validate): organisation name, country, industry, and a phone or an email somewhere.
 *
 *   - "Already exists": a lead or client with the same name (Duplicates::find, kept only when the name keys are
 *     equal: "Kilo Logistics" is "KILO LOGISTICS LTD", but "Zenith" isn't "Zenith Bank Ghana", so contacts never go to
 *     the wrong organisation), or a lead this file's row already made. It is left as it is; the contacts it doesn't
 *     have yet are added (Contacts::add) with one `imported` line on its timeline.
 *   - New leads go through Leads::create with the dialog's owner (an Owner email column overrides it for its
 *     lead), source, source details and next step; contacts come in with source `import`.
 */
final class LeadImportSchema extends ImportSchema
{
    /** Validator keys (Leads::validate's lead.<field>) => the column it is about */
    private const LEAD_FIELDS = [
        'name' => 'organisation', 'country' => 'country', 'industry' => 'industry', 'phone' => 'org_phone', 'email' => 'org_email',
        'city' => 'city', 'address' => 'address',
    ];

    private const CONTACT_FIELDS = ['name' => 'contact_name', 'job_title' => 'job_title', 'email' => 'contact_email', 'phone' => 'contact_phone', 'deal_role' => 'deal_role'];

    public function key(): string
    {
        return 'leads';
    }

    public function title(): string
    {
        return 'Import leads';
    }

    public function noun(): array
    {
        return ['lead', 'leads'];
    }

    public function sheetName(): string
    {
        return 'Leads';
    }

    public function addedNoun(): array
    {
        return ['contact', 'contacts'];
    }

    public function canImport(?User $user): bool
    {
        return LeadAccess::canCreate($user) && LeadAccess::canEdit($user);
    }

    public function columns(): array
    {
        $countries = array_combine(Countries::NAMES, Countries::NAMES);
        $industries = Leads::industries();

        return [
            new Column('organisation', 'Organisation name', required: true, width: 30,
                help: 'The organisation’s name. Rows with the same name become one lead.',
                example: 'Kilo Logistics Ltd', bad: 'Kilo (call Ada on Monday): only the name goes here',
                aliases: ['organization name', 'organisation', 'organization', 'company', 'company name', 'account name']),
            new Column('country', 'Country', required: true, width: 18, choices: $countries,
                help: 'Where the organisation is. Choose from the list, or type another country.',
                example: 'Nigeria', bad: 'NG or +234: write the country’s name'),
            new Column('industry', 'Industry', required: true, width: 26, choices: $industries ? array_combine($industries, $industries) : [],
                help: 'Its industry. Choose from the list, or type another.',
                example: 'Logistics & Supply Chain', bad: 'Big company: say what it does',
                aliases: ['sector']),
            new Column('org_phone', 'Organisation phone', oneOf: 'reach', width: 20,
                help: 'The organisation’s own number, e.g. its switchboard. Digits, spaces, +, brackets, dots and dashes only.',
                example: '+234 1 555 0100', bad: '0803 CALL ADA: no letters',
                aliases: ['company phone', 'office phone']),
            new Column('org_email', 'Organisation email', oneOf: 'reach', width: 26,
                help: 'A general address like info@… (a person’s address goes in Contact email).',
                example: 'info@kilo.test', bad: 'info at kilo dot com',
                aliases: ['company email', 'office email']),
            new Column('city', 'City', width: 16, help: 'Optional.', example: 'Lagos', aliases: ['town']),
            new Column('address', 'Address', width: 30, help: 'Optional. The street address.', example: '12 Marina Road, Lagos Island', aliases: ['street address']),
            new Column('contact_name', 'Contact name', width: 24,
                help: 'One person per row. Leave the contact columns empty for an organisation with nobody named yet.',
                example: 'Ada Obi', bad: 'Ada Obi and Bola Eze: give each person a row',
                aliases: ['name', 'full name', 'contact', 'contact person', 'person']),
            new Column('job_title', 'Job title', width: 22, help: 'Optional.', example: 'Operations director', aliases: ['title', 'position']),
            new Column('contact_email', 'Contact email', oneOf: 'reach', width: 28,
                help: 'Their own address. One email belongs to one person in HQ.',
                example: 'ada.obi@kilo.test', bad: 'ada.obi@ (incomplete)',
                aliases: ['email', 'email address', 'e-mail', 'work email']),
            new Column('contact_phone', 'Contact phone', oneOf: 'reach', width: 20,
                help: 'Their mobile or direct line.',
                example: '+234 803 555 0101', bad: '803555010 (too short to call)',
                aliases: ['phone', 'phone number', 'mobile', 'mobile phone', 'telephone', 'direct line']),
            new Column('deal_role', 'Deal role', width: 18, choices: Contacts::DEAL_ROLES, strict: true,
                help: 'Optional. Their part in the decision: Decision maker, Finance, Technical or User.',
                example: 'Decision maker', bad: 'Boss: choose from the list',
                aliases: ['role', 'deal role']),
            new Column('owner_email', 'Owner email', width: 28,
                help: 'Optional. Who follows this lead up: the address they sign in to HQ with. Leave it empty to use the owner you choose when importing. A lead already in HQ keeps its owner.',
                example: 'your.colleague@jbspraxis.com', bad: 'Ada: write their HQ email address',
                aliases: ['owner', 'lead owner', 'assigned to']),
        ];
    }

    public function guide(): array
    {
        return [
            'Fill in the second sheet, ‘Leads’: one row per contact. Rows with the same organisation name become one lead, and each row’s person one of its contacts (the first is the main contact).',
            'Columns marked * are required. For each organisation, fill in at least one of the columns marked +: a phone or an email, the organisation’s or a contact’s.',
            'Fill in the organisation’s own columns once, on its first row; its other rows only need the contact.',
            'An organisation already in HQ isn’t added twice: its new contacts are added to it. Rows that need fixing come back as an Excel file to fix and import again.',
            'Keep row 1 (the headers) as it is. Up to 1,000 rows a file.',
        ];
    }

    public function oneOfText(string $group): string
    {
        return 'A phone or an email: at least one of the four columns marked +';
    }

    public function examples(): array
    {
        return [
            ['organisation' => 'Kilo Logistics Ltd', 'country' => 'Nigeria', 'industry' => 'Logistics & Supply Chain', 'org_phone' => '+234 1 555 0100', 'org_email' => 'info@kilo.test',
                'city' => 'Lagos', 'address' => '12 Marina Road, Lagos Island', 'contact_name' => 'Ada Obi', 'job_title' => 'Operations director',
                'contact_email' => 'ada.obi@kilo.test', 'contact_phone' => '+234 803 555 0101', 'deal_role' => 'decision_maker'],
            ['organisation' => 'Kilo Logistics Ltd', 'contact_name' => 'Bola Eze', 'job_title' => 'Finance manager', 'contact_email' => 'bola.eze@kilo.test', 'deal_role' => 'finance'],
            ['organisation' => 'Lima Freight SARL', 'country' => 'Cameroon', 'industry' => 'Logistics & Supply Chain', 'org_phone' => '+237 6 55 55 01 02', 'city' => 'Douala'],
        ];
    }

    public function options(User $by, string $fileName): array
    {
        $step = NextStep::suggestion();
        $sources = ['import' => Sources::label('import')] + array_intersect_key(Sources::LABELS, array_flip(array_diff(Sources::BY_HAND, ['manual'])));

        return [
            Option::staff('owner_id', 'Owner', $by->id, 'Who follows up the new leads. A row’s Owner email overrides this for its lead.', none: 'No one: anyone can take them'),
            Option::choice('source', 'Where they came from', $sources, 'import'),
            Option::text('source_detail', 'Source details', $fileName, 'Shows on each new lead, e.g. the event or the list they came from.'),
            Option::text('next_step', 'Next step for each new lead', $step['what'], 'Every open lead has a next step. Leave it empty to give them one by one.'),
            Option::date('next_step_due', 'By', $step['due']),
        ];
    }

    public function checkOptions(array $options, User $by): array
    {
        if (($options['next_step'] ?? null) && ! ($options['next_step_due'] ?? null)) {
            return ['next_step_due' => 'Choose by when.'];
        }

        return [];
    }

    public function fill(array $row): array
    {
        // A dialling code typed as the country (+234, as the website's form once stored it) becomes the country
        if ($row['country'] !== '' && ($country = Countries::fromDialCode($row['country']))) {
            $row['country'] = $country;
        }
        $row['owner_email'] = mb_strtolower($row['owner_email']);

        return $row;
    }

    public function groupKey(array $row, int $line): string
    {
        $name = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $row['organisation'])));

        return $name === '' ? 'row:' . $line : 'org:' . $name;
    }

    public function existing(Group $group, Run $run): ?array
    {
        $name = $group->value('organisation');
        if ($name === '') {
            return null;
        }
        // A lead this row of this file made before (the same file uploaded again)
        $orgId = DB::table('lead_timeline')->where('dedupe_key', $run->dedupeKey($group->first()))->value('organization_id');
        if (! $orgId) {
            $key = Duplicates::nameKey($name);
            $match = Duplicates::find($name)->first(fn ($m) => in_array('name', $m['matched'], true) && Duplicates::nameKey($m['name']) === $key);
            $orgId = $match['id'] ?? null;
        }
        $org = $orgId ? DB::table('organizations')->where('id', $orgId)->whereNull('deleted_at')->first(['id', 'name', 'status']) : null;
        if (! $org) {
            return null;
        }
        $isLead = in_array($org->status, LeadAccess::STATUSES, true);

        return [
            'id' => $org->id,
            'label' => $isLead ? $org->name : $org->name . ' (' . strtolower($org->status) . ')',
            'url' => $isLead ? route('hq.crm.lead', ['organization' => $org->id]) : null,
        ];
    }

    public function check(Group $group, Run $run): void
    {
        $contacts = $this->contacts($group);
        $group->existing ? $this->checkExisting($group, $contacts, $run) : $this->checkNew($group, $contacts, $run);
    }

    /** A new lead: Add a lead's rules, each contact's email free, one owner */
    private function checkNew(Group $group, array $contacts, Run $run): void
    {
        $this->failWith($group, Leads::validate($this->lead($group), array_values($contacts)), array_keys($contacts));
        foreach ($contacts as $line => $c) {
            $this->checkEmail($group, $line, $c['email'], null, $run);
        }

        // The owner: an active member of staff, the same on every row of the lead
        $owner = null;
        foreach ($group->rows as $line => $row) {
            if ($row['owner_email'] === '') {
                continue;
            }
            if (! $this->staffByEmail($row['owner_email'])) {
                $group->fail($line, 'owner_email', 'No active HQ account has this email. Use the address they sign in with, or leave it empty for the owner chosen when importing.');
            } elseif ($owner && $owner[1] !== $row['owner_email']) {
                $group->fail($line, 'owner_email', "Row {$owner[0]} gives this organisation another owner. Use one owner email for all its rows, or leave it empty.");
            } else {
                $owner ??= [$line, $row['owner_email']];
            }
        }

        // Still reachable without the contacts that need fixing?
        $good = array_values(array_intersect_key($contacts, array_flip($group->goodLines())));
        if ($group->goodLines() && ! Leads::reachable($this->lead($group), $good)) {
            $group->failAll(Leads::UNREACHABLE, 'org_phone');
        }
    }

    /** A lead already in HQ: each row's contact is either there already, new (added on import), or needs fixing */
    private function checkExisting(Group $group, array $contacts, Run $run): void
    {
        if (! $group->existing['url']) {
            return; // not a lead any more (a past client…): left alone
        }
        $known = Contacts::of($group->existing['id']);
        foreach ($contacts as $line => $c) {
            $errors = Validator::make($c, Contacts::rules(), Contacts::messages())->errors()->toArray();
            foreach ($errors as $field => $messages) {
                $group->fail($line, self::CONTACT_FIELDS[$field] ?? null, $messages[0]);
            }
            if ($errors) {
                continue;
            }
            $email = mb_strtolower($c['email']);
            $there = $known->first(fn ($k) => ($email !== '' && mb_strtolower((string) $k->email) === $email)
                || ($email === '' && (mb_strtolower(trim($k->first_name . ' ' . $k->last_name)) === mb_strtolower($c['name'])
                    || (($p = Duplicates::phoneKey($c['phone'])) && $p === Duplicates::phoneKey($k->phone)))));
            if ($there) {
                continue;
            }
            if ($this->checkEmail($group, $line, $c['email'], $group->existing['id'], $run)) {
                $group->adds[] = $line;
            }
        }
    }

    /**
     * An email can't be given twice in the file, nor be someone else's contact already. Returns whether it is free.
     */
    private function checkEmail(Group $group, int $line, string $email, ?string $orgId, Run $run): bool
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return true;
        }
        if (isset($run->seen['emails'][$email]) && $run->seen['emails'][$email] !== $line) {
            $group->fail($line, 'contact_email', 'Row ' . $run->seen['emails'][$email] . ' has the same email. Each person needs their own.');

            return false;
        }
        $run->seen['emails'][$email] = $line;
        if ($taken = Contacts::emailTaken($email)) {
            $group->fail($line, 'contact_email', $taken->organization_id === $orgId
                ? 'This email belonged to a contact removed from this lead. Use another address.'
                : "This email is already a contact of {$taken->organization_name}. Open that lead, or use another address.");

            return false;
        }

        return true;
    }

    public function create(Group $group, Run $run): ?string
    {
        $contacts = array_intersect_key($this->contacts($group), array_flip($group->goodLines()));
        $owner = $run->option('owner_id');
        foreach ($group->goodLines() as $line) {
            if (($email = $group->rows[$line]['owner_email']) !== '' && ($user = $this->staffByEmail($email))) {
                $owner = $user->id;
                break;
            }
        }
        $lead = $this->lead($group) + ['source_detail' => $run->option('source_detail'), 'next_step' => $run->option('next_step'),
            'next_step_due' => $run->option('next_step') ? $run->option('next_step_due') : null];

        try {
            $org = Leads::create($lead, array_values($contacts), $run->by, [
                'owner_id' => $owner, 'source' => $run->option('source', 'import'), 'contact_source' => 'import', 'dedupe_key' => $run->dedupeKey($group->first()),
            ]);
        } catch (ValidationException $e) {
            $this->failWith($group, $e->errors(), array_keys($contacts));

            return null;
        } catch (LeadRuleBroken $e) {
            $group->failAll($e->getMessage());

            return null;
        }
        if ($owner) {
            $run->seen['owners'][$owner][] = $org->id;
        }

        return $org->id;
    }

    public function add(Group $group, Run $run): int
    {
        $org = LeadAccess::find($group->existing['id']);
        if (! $org) {
            return 0;
        }
        $contacts = $this->contacts($group);

        return DB::transaction(function () use ($group, $run, $org, $contacts) {
            $added = [];
            foreach ($group->adds as $line) {
                try {
                    $added[$line] = Contacts::add($org, $contacts[$line], $run->by, 'import', quiet: true);
                } catch (LeadRuleBroken $e) {
                    $group->fail($line, 'contact_email', $e->getMessage());
                }
            }
            if ($added) {
                $names = collect($added)->map->full_name->implode(', ');
                LeadTimeline::record($org->id, 'imported', 'Imported from ' . $run->fileName . ': ' . count($added) . ' new ' . (count($added) === 1 ? 'contact' : 'contacts') . ", {$names}.", [
                    'actor' => $run->by, 'client_user_id' => count($added) === 1 ? reset($added)->id : null,
                    'payload' => ['file' => $run->fileName, 'rows' => array_keys($added), 'contacts' => collect($added)->map->id->values()->all()],
                    'dedupe_key' => $run->dedupeKey(array_key_first($added)),
                ]);
            }

            return count($added);
        });
    }

    /** One bell per owner the import gave leads to (not the person importing): their page when it is one, else the list */
    public function finish(Run $run, array $created, int $added): void
    {
        foreach ($run->seen['owners'] ?? [] as $ownerId => $ids) {
            if ($ownerId === $run->by?->id) {
                continue;
            }
            $n = count($ids);
            LeadOwner::bell($ownerId, $n === 1 ? 'A lead was imported for you' : "{$n} leads were imported for you",
                LeadOwner::name($run->by) . ' imported ' . ($n === 1 ? 'a lead' : "{$n} leads") . " for you to follow up, from {$run->fileName}.",
                $ids[0], $n === 1 ? null : route('hq.crm.leads', ['owner' => $ownerId], false));
        }
    }

    /** The lead's own fields (Leads::create's keys), from the first row that gives each */
    private function lead(Group $group): array
    {
        $lead = [];
        foreach (self::LEAD_FIELDS as $field => $column) {
            $lead[$field] = $group->value($column);
        }

        return $lead;
    }

    /** Each row's contact (Contacts' keys), by row number; rows with no contact left out */
    private function contacts(Group $group): array
    {
        $contacts = [];
        foreach ($group->rows as $line => $row) {
            $c = [];
            foreach (self::CONTACT_FIELDS as $field => $column) {
                $c[$field] = $row[$column];
            }
            if (! Contacts::isBlank($c)) {
                $contacts[$line] = $c;
            }
        }

        return $contacts;
    }

    /** Leads::validate's errors on the rows and cells they are about ($lines: the contacts' rows, in order) */
    private function failWith(Group $group, array $errors, array $lines): void
    {
        foreach ($errors as $key => $messages) {
            $message = (string) $messages[0];
            if (preg_match('/^lead\.(\w+)$/', $key, $m)) {
                foreach ($messages as $message) {
                    if ($message === Leads::UNREACHABLE) {
                        // nobody can be reached: the four phone and email cells of the first row turn red
                        $group->failAll($message, 'org_phone');
                        foreach (['org_email', 'contact_email', 'contact_phone'] as $column) {
                            $group->fail($group->first(), $column, $message);
                        }
                    } else {
                        $group->failAll($message, self::LEAD_FIELDS[$m[1]] ?? null);
                    }
                }
            } elseif (preg_match('/^contacts\.(\d+)\.(\w+)$/', $key, $m) && isset($lines[(int) $m[1]])) {
                $group->fail($lines[(int) $m[1]], self::CONTACT_FIELDS[$m[2]] ?? null, $message);
            } else {
                $group->failAll($message);
            }
        }
    }

    private function staffByEmail(string $email): ?User
    {
        return User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->where('status', 'Active')->whereNull('deleted_at')->first();
    }
}
