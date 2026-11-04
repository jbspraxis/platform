<?php

namespace App\Support\CompanyDocuments;

use App\Models\CompanyDeadline;
use App\Models\CompanyDocumentVersion;
use App\Models\User;
use App\Support\Finance\Vat;
use App\Support\Mdm\LegalEntities;
use App\Support\WebsiteSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The "Suggested details" panel (owner's decision 4, 4 Nov 2026; docs/COMPANY_DOCUMENTS.md): what a document says,
 * next to what HQ holds, field by field. Nothing changes until a person ticks rows and applies them.
 *
 *   fill      HQ has nothing yet: ticked by default
 *   same      they agree: nothing to do
 *   differs   they disagree: HQ keeps its value unless the person ticks the row (the CAC documents first issued
 *             carry a typo in the address, so a certificate never overwrites HQ by itself)
 *   absent    the document doesn't say
 *
 * Apply goes through the existing contracts: Legal entities (LegalEntities::update), Website settings
 * (WebsiteSettings::set: the legal name on the website and the registration row) and the calendar (Calendar::add),
 * each with its audit line, plus one line for the document saying what was applied and what HQ kept.
 */
final class Suggestions
{
    /** key => [group, label, input type, the field in Claude's reading] */
    public const FIELDS = [
        'entity.legal_name' => ['entity', 'Registered company name', 'text', 'company_name'],
        'entity.registration_number' => ['entity', 'Company registration number (RC)', 'text', 'rc_number'],
        'entity.tax_identification_number' => ['entity', 'Tax ID (NIU or TIN)', 'text', 'tin'],
        'entity.incorporated_on' => ['entity', 'Date of registration', 'date', 'registered_on'],
        'entity.company_type' => ['entity', 'Company type', 'text', 'company_type'],
        'entity.registered_address' => ['entity', 'Registered office address', 'textarea', 'registered_address'],
        'entity.share_capital' => ['entity', 'Share capital', 'text', 'share_capital'],
        'website.legal_name' => ['website', 'Legal name on jbspraxis.com', 'text', 'company_name'],
        'website.registration' => ['website', 'Registration number on jbspraxis.com', 'text', 'rc_number'],
        'document.issued_on' => ['document', 'Issue date', 'date', 'issued_on'],
        'document.expires_on' => ['document', 'Expiry or renewal date', 'date', 'expires_on'],
    ];

    public const GROUPS = ['entity' => 'Legal entities', 'website' => 'Website settings', 'document' => 'This document'];

    /** The registry's name on jbspraxis.com, per country (a registration row {country, label, number, status 'registered'}) */
    public const REGISTRY_LABELS = ['NG' => 'RC', 'CM' => 'RCCM'];

    /**
     * The rows for one version: $read is Claude's reading (null: the person types the details).
     *
     * @return array{rows: list<array>, deadlines: list<array>, notes: list<string>}
     */
    public static function build(CompanyDocumentVersion $version, User $viewer, ?array $read): array
    {
        $doc = $version->document;
        $entity = Companies::entity($doc->legal_entity_id);
        $read ??= [];
        $notes = [];

        $current = [];
        if ($entity) {
            foreach (array_keys(LegalEntities::RULES) as $field) {
                $value = trim((string) ($entity->{$field} ?? ''));
                $current["entity.{$field}"] = $value === '' ? null : ($field === 'incorporated_on' ? substr($value, 0, 10) : $value);
            }
            $site = WebsiteSettings::get('company', []);
            if ($entity->region_code === Vat::HOME) {
                $current['website.legal_name'] = $site['legal_name'] ?? null;
            }
            if (isset(WebsiteSettings::COUNTRIES[$entity->region_code])) {
                $row = collect($site['registrations'] ?? [])->firstWhere('country', $entity->region_code);
                $current['website.registration'] = ($row && $row['status'] === 'registered' && $row['number']) ? (string) $row['number'] : null;
            }
        } else {
            $notes[] = 'This document is filed under the head office, so it changes nothing in Legal entities or on the website.';
        }
        $current['document.issued_on'] = $doc->issued_on?->format('Y-m-d');
        $current['document.expires_on'] = $doc->expires_on?->format('Y-m-d');

        $canWebsite = $viewer->can('website.edit');
        $rows = [];
        foreach ($current as $key => $hq) {
            [$group, $label, $type, $source] = self::FIELDS[$key];
            $found = self::clean($type, (string) ($read[$source] ?? ''));
            $state = match (true) {
                $found === '' => 'absent',
                $hq === null => 'fill',
                self::same($key, $hq, $found) => 'same',
                default => 'differs',
            };
            $locked = $group === 'website' && ! $canWebsite ? 'Changing the website needs Website settings access.' : null;
            $rows[] = [
                'key' => $key, 'group' => $group, 'label' => $label, 'type' => $type,
                'hq' => $hq, 'found' => $found, 'state' => $state, 'locked' => $locked,
                'tick' => $state === 'fill' && $locked === null,
                'prefix' => $key === 'website.registration' ? (self::REGISTRY_LABELS[$entity->region_code] ?? null) : null,
            ];
        }

        if (trim((string) ($read['unreadable'] ?? '')) !== '') {
            $notes[] = 'Claude noted: ' . rtrim(trim($read['unreadable']), '.') . '.';
        }

        return ['rows' => $rows, 'deadlines' => self::deadlines($doc->legal_entity_id, (array) ($read['deadlines'] ?? [])), 'notes' => $notes];
    }

    /**
     * Applies the ticked rows (with the values the person may have corrected) and the ticked deadlines.
     * $values: key => value; $ticks: key => bool; $deadlines: [{title, due_on, repeats, tick}].
     *
     * @return array{applied: list<string>, kept: list<string>}
     *
     * @throws ValidationException (keys values.<key>, deadlines.<i>.<field>, apply)
     */
    public static function apply(CompanyDocumentVersion $version, User $by, ?array $read, array $values, array $ticks, array $deadlines): array
    {
        $doc = $version->document;
        $rows = collect(self::build($version, $by, $read)['rows'])->keyBy('key');

        $chosen = [];
        $errors = [];
        foreach ($rows as $key => $row) {
            if (empty($ticks[$key]) || $row['locked']) {
                continue;
            }
            $value = self::clean($row['type'], (string) ($values[$key] ?? ''));
            if ($value === '') {
                $errors["values.{$key}"] = "Type the {$row['label']} from the document, or untick it.";
                continue;
            }
            $check = Validator::make(['value' => $value], ['value' => self::rule($key)], [], ['value' => mb_strtolower($row['label'])]);
            if ($check->fails()) {
                $errors["values.{$key}"] = $check->errors()->first('value');
                continue;
            }
            $chosen[$key] = $value;
        }

        $newDeadlines = [];
        foreach (array_values($deadlines) as $i => $d) {
            if (empty($d['tick'])) {
                continue;
            }
            $item = ['company' => Companies::keyFor($doc->legal_entity_id), 'title' => trim((string) ($d['title'] ?? '')), 'due_on' => (string) ($d['due_on'] ?? ''),
                'repeats' => (string) ($d['repeats'] ?? 'once'), 'remind_days' => Documents::DEFAULT_REMIND_DAYS, 'notes' => null];
            $check = Validator::make($item, Calendar::rules(), [], Calendar::ATTRIBUTES);
            if ($check->fails()) {
                foreach ($check->errors()->messages() as $field => $messages) {
                    $errors["deadlines.{$i}.{$field}"] = $messages[0];
                }
                continue;
            }
            $newDeadlines[] = $item;
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        if (! $chosen && ! $newDeadlines) {
            throw ValidationException::withMessages(['apply' => 'Nothing is ticked, so nothing was applied. Tick the details to apply, or choose Keep HQ as it is.']);
        }

        $source = "from the document ‘{$doc->title}’, version {$version->number}";
        $kept = self::kept($rows->all(), array_keys($chosen));

        return DB::transaction(function () use ($version, $doc, $by, $chosen, $newDeadlines, $source, $kept, $rows) {
            $applied = [];

            $entityChanges = [];
            foreach ($chosen as $key => $value) {
                if (str_starts_with($key, 'entity.')) {
                    $entityChanges[substr($key, 7)] = $value;
                }
            }
            if ($entityChanges) {
                foreach (LegalEntities::update($doc->legal_entity_id, $entityChanges, $by->id, $source) as $field => $pair) {
                    $applied["entity.{$field}"] = $pair;
                }
            }

            $site = [];
            if (isset($chosen['website.legal_name'])) {
                $site['legal_name'] = $chosen['website.legal_name'];
                $applied['website.legal_name'] = [$rows['website.legal_name']['hq'], $chosen['website.legal_name']];
            }
            if (isset($chosen['website.registration'])) {
                $region = Companies::entity($doc->legal_entity_id)->region_code;
                $list = array_values((array) WebsiteSettings::get('company.registrations', []));
                $at = collect($list)->search(fn ($r) => ($r['country'] ?? null) === $region);
                $row = ['country' => $region, 'label' => self::REGISTRY_LABELS[$region] ?? ($at !== false ? $list[$at]['label'] : 'Registration'),
                    'number' => $chosen['website.registration'], 'status' => 'registered'];
                if ($at === false) {
                    $list[] = $row;
                } else {
                    $list[$at] = $row;
                }
                $site['registrations'] = $list;
                $applied['website.registration'] = [$rows['website.registration']['hq'], $row['label'] . ' ' . $row['number']];
            }
            if ($site) {
                WebsiteSettings::set(['company' => $site], $by->id);
            }

            $dates = [];
            foreach (['issued_on', 'expires_on'] as $field) {
                if (isset($chosen["document.{$field}"])) {
                    $dates[$field] = $chosen["document.{$field}"];
                    $applied["document.{$field}"] = [$rows["document.{$field}"]['hq'], $chosen["document.{$field}"]];
                }
            }
            if ($dates) {
                $doc->forceFill($dates + ['updated_by' => $by->id])->save();
            }

            foreach ($newDeadlines as $item) {
                $deadline = Calendar::add($item, $by, $doc->id);
                $applied['deadlines'][] = ['title' => $deadline->title, 'due_on' => $item['due_on'], 'repeats' => $item['repeats']];
            }

            $lines = [];
            $old = [];
            $new = [];
            foreach ($applied as $key => $pair) {
                if ($key === 'deadlines') {
                    $new['deadlines'] = $pair;
                    continue;
                }
                [$old[$key], $new[$key]] = $pair;
                $lines[] = self::FIELDS[$key][1] . ': ' . self::show(self::FIELDS[$key][2], (string) $pair[1]);
            }
            foreach ($applied['deadlines'] ?? [] as $d) {
                $lines[] = "Calendar: {$d['title']}, due " . Documents::day($d['due_on']);
            }

            $version->forceFill(['applied' => $applied, 'reviewed_at' => now(), 'reviewed_by' => $by->id])->save();
            Documents::log('COMPANY_DOCUMENT_DETAILS_APPLIED', $doc, 'Applied ' . $source . ' to ' . Companies::label($doc->legal_entity_id) . ': '
                . ($lines ? implode('; ', $lines) : 'nothing had changed') . '.' . ($kept ? ' ' . implode(' ', $kept) : ''), $old, $new, $by->id);

            return ['applied' => $lines, 'kept' => $kept];
        });
    }

    /** Nothing applied: HQ stays as it is, noted on the version and in the audit log */
    public static function setAside(CompanyDocumentVersion $version, User $by, ?array $read): void
    {
        $doc = $version->document;
        $kept = self::kept(self::build($version, $by, $read)['rows'], []);
        $version->forceFill(['applied' => [], 'reviewed_at' => now(), 'reviewed_by' => $by->id])->save();
        Documents::log('COMPANY_DOCUMENT_DETAILS_SET_ASIDE', $doc, "Kept HQ as it is for ‘{$doc->title}’, version {$version->number}: nothing applied."
            . ($kept ? ' ' . implode(' ', $kept) : ''), null, null, $by->id);
    }

    /** A value as people read it: dates as "3 May 2027" */
    public static function show(string $type, ?string $value): string
    {
        return $value === null || $value === '' ? '' : ($type === 'date' ? Documents::day($value) : $value);
    }

    /** "Kept HQ’s registered office address: the document says “…”." for each disagreement left unticked */
    private static function kept(array $rows, array $chosen): array
    {
        $out = [];
        foreach ($rows as $row) {
            if ($row['state'] === 'differs' && ! in_array($row['key'], $chosen, true)) {
                $out[] = 'Kept HQ’s ' . mb_strtolower($row['label']) . ': the document says “' . self::show($row['type'], $row['found']) . '”.';
            }
        }

        return $out;
    }

    /** Claude's suggested deadlines, each ticked unless it has passed or a deadline on that day is already in the calendar */
    private static function deadlines(?string $entityId, array $suggested): array
    {
        $existing = CompanyDeadline::whereNull('archived_at')
            ->when($entityId, fn ($q) => $q->where('legal_entity_id', $entityId), fn ($q) => $q->whereNull('legal_entity_id'))->get();
        $today = Documents::today();
        $out = [];
        foreach ($suggested as $d) {
            $title = trim((string) ($d['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $due = self::clean('date', (string) ($d['due_on'] ?? ''));
            $match = $due !== '' ? $existing->first(fn ($e) => $e->due_on->format('Y-m-d') === $due) : null;
            $past = $due !== '' && $due < $today;
            $out[] = [
                'title' => mb_substr($title, 0, 190), 'due_on' => $due,
                'repeats' => ($d['repeats'] ?? '') === 'yearly' ? 'yearly' : 'once',
                'source' => trim((string) ($d['source'] ?? '')),
                'tick' => $due !== '' && ! $match && ! $past,
                'note' => $match ? "Already in the calendar as ‘{$match->title}’." : ($past ? 'This date has passed.' : ($due === '' ? 'The document gives no date: add one to apply it.' : null)),
            ];
        }

        return $out;
    }

    /** The rule a ticked value must pass, the same as its destination's */
    private static function rule(string $key): array
    {
        return match (true) {
            str_starts_with($key, 'entity.') => array_values(array_diff(LegalEntities::RULES[substr($key, 7)], ['nullable'])),
            $key === 'website.legal_name' => ['required', 'string', 'max:120'],
            $key === 'website.registration' => ['required', 'string', 'max:60'],
            default => ['required', 'date_format:Y-m-d'],
        };
    }

    /** Text trimmed to one line of single spaces (an address keeps its line breaks); a date only as YYYY-MM-DD */
    private static function clean(string $type, string $value): string
    {
        $value = trim($value);
        if ($type === 'date') {
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)) ? $value : '';
        }

        return $type === 'textarea' ? preg_replace("/[ \t]+/", ' ', $value) : preg_replace('/\s+/u', ' ', $value);
    }

    /** The same once case, spaces and punctuation are set aside (and RC/RCCM before a registration number) */
    private static function same(string $key, string $a, string $b): bool
    {
        $norm = function (string $v) use ($key) {
            $v = preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($v));
            return in_array($key, ['entity.registration_number', 'website.registration'], true) ? preg_replace('/^(rccm|rc)/u', '', $v) : $v;
        };

        return $norm($a) === $norm($b);
    }
}
