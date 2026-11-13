<?php

namespace App\Support\Leads;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Adding a lead and changing its details (owner's decisions 1–3 and 7, docs/LEADS.md section 6). The Add a lead form
 * and PR 3's Excel import both go through create(), so the rules are the same:
 *
 *   required    organisation name, country, industry, and at least one phone or email (the organisation's or a contact's)
 *   optional    city, address, the organisation's phone and email, contacts, language, source detail, next step,
 *               the cost of poor quality figures (all of them, or none)
 *
 *   Leads::create(['name' => 'Acme Ltd', 'country' => 'Nigeria', 'industry' => 'Banking', 'phone' => '+234…'],
 *                 [['name' => 'Ada Obi', 'email' => 'ada@acme.test', 'deal_role' => 'decision_maker']], $user);
 *
 * Website leads are created by App\Services\Site and handed to WebsiteLeads::received().
 */
final class Leads
{
    /** The lead's own fields (keys as create() takes them) under $prefix */
    public static function rules(string $prefix = ''): array
    {
        return [
            "{$prefix}name" => ['required', 'string', 'max:150'],
            "{$prefix}country" => ['required', 'string', 'max:100'],
            "{$prefix}city" => ['nullable', 'string', 'max:100'],
            "{$prefix}industry" => ['required', 'string', 'max:100'],
            "{$prefix}phone" => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 ().\-]{4,26}$/'],
            "{$prefix}email" => ['nullable', 'email', 'max:150'],
            "{$prefix}address" => ['nullable', 'string', 'max:2000'],
            "{$prefix}registration_number" => ['nullable', 'string', 'max:60'],
            "{$prefix}tax_number" => ['nullable', 'string', 'max:100'],
            "{$prefix}preferred_language" => ['nullable', Rule::in(['EN', 'FR'])],
            "{$prefix}source" => ['nullable', Rule::in(array_keys(Sources::LABELS))],
            "{$prefix}source_detail" => ['nullable', 'string', 'max:255'],
        ] + NextStep::rules($prefix);
    }

    public static function messages(string $prefix = ''): array
    {
        return [
            "{$prefix}name.required" => 'Enter the organisation’s name.',
            "{$prefix}name.max" => 'Keep the name under 150 characters.',
            "{$prefix}country.required" => 'Choose the country.',
            "{$prefix}country.max" => 'Keep the country under 100 characters.',
            "{$prefix}city.max" => 'Keep the city under 100 characters.',
            "{$prefix}industry.required" => 'Choose the industry.',
            "{$prefix}industry.max" => 'Keep the industry under 100 characters.',
            "{$prefix}phone.regex" => 'Enter a phone number with digits only, e.g. +234 800 000 0000.',
            "{$prefix}phone.max" => 'Keep the phone number under 30 characters.',
            "{$prefix}email.email" => 'Enter an email address like info@company.com.',
            "{$prefix}email.max" => 'Keep the email address under 150 characters.',
            "{$prefix}address.max" => 'Keep the address under 2,000 characters.',
            "{$prefix}preferred_language.in" => 'Choose English or French.',
            "{$prefix}source.in" => 'Choose where the lead came from.',
            "{$prefix}source_detail.max" => 'Keep this under 255 characters.',
        ] + NextStep::messages($prefix);
    }

    /** The message when nobody can be reached: no phone or email on the organisation or any contact */
    public const UNREACHABLE = 'Add at least one phone number or email address, for the organisation or a contact.';

    /** At least one phone or email, on the organisation or on a contact (decision 1) */
    public static function reachable(array $lead, array $contacts): bool
    {
        $filled = fn ($v) => trim((string) $v) !== '';

        return $filled($lead['phone'] ?? null) || $filled($lead['email'] ?? null)
            || collect($contacts)->contains(fn ($c) => $filled($c['phone'] ?? null) || $filled($c['email'] ?? null));
    }

    /**
     * Check a lead and its contacts with the rules above; errors keyed lead.<field> and contacts.<n>.<field>, plus
     * lead.phone for UNREACHABLE. Empty contact blocks are dropped first. PR 3's import shows these as "Needs fixing".
     */
    public static function validate(array $lead, array $contacts): array
    {
        $contacts = array_values(array_filter($contacts, fn ($c) => ! Contacts::isBlank($c)));
        $errors = Validator::make(['lead' => $lead, 'contacts' => $contacts],
            self::rules('lead.') + self::contactRules(),
            self::messages('lead.') + Contacts::messages('contacts.*.'))->errors()->toArray();
        if (! self::reachable($lead, $contacts)) {
            $errors['lead.phone'][] = self::UNREACHABLE;
        }

        return $errors;
    }

    private static function contactRules(): array
    {
        return collect(Contacts::rules('contacts.*.'))->all();
    }

    /**
     * Create the lead (status Lead, lead_status new) with its contacts in one transaction, owned by $options['owner_id']
     * (default: whoever adds it). The first contact marked main, else the first, is the main contact. One timeline line
     * "Lead added" lists the contacts. $options: owner_id, source (default manual), copq (the figures, optional),
     * contact_source (client_users.source, default hq), dedupe_key (an import's row).
     *
     * @throws ValidationException when a rule fails (keys as validate())
     * @throws LeadRuleBroken when a contact's email is someone else's
     */
    public static function create(array $lead, array $contacts, ?User $by, array $options = []): Organization
    {
        $contacts = array_values(array_filter($contacts, fn ($c) => ! Contacts::isBlank($c)));
        if ($errors = self::validate($lead, $contacts)) {
            throw ValidationException::withMessages($errors);
        }
        foreach ($contacts as $i => $c) {
            if (($e = trim((string) ($c['email'] ?? ''))) !== '' && ($taken = Contacts::emailTaken($e))) {
                throw ValidationException::withMessages(["contacts.{$i}.email" => "This email is already a contact of {$taken->organization_name}. Open that lead, or use another address."]);
            }
        }
        $emails = collect($contacts)->pluck('email')->map(fn ($e) => mb_strtolower(trim((string) $e)))->filter();
        if ($emails->count() !== $emails->unique()->count()) {
            throw ValidationException::withMessages(['contacts.1.email' => 'Two contacts have the same email address. Each person needs their own.']);
        }

        $value = fn ($k) => ($v = trim((string) ($lead[$k] ?? ''))) === '' ? null : $v;
        $ownerId = array_key_exists('owner_id', $options) ? $options['owner_id'] : $by?->id;
        $source = $options['source'] ?? $value('source') ?? 'manual';

        return DB::transaction(function () use ($lead, $contacts, $by, $options, $value, $ownerId, $source) {
            $org = Organization::create(array_filter([
                'name' => $value('name'),
                'status' => 'Lead',
                'lead_status' => 'new',
                'pipeline_stage' => 'Lead Captured',
                'assigned_to' => $ownerId,
                'country' => $value('country'),
                'city' => $value('city'),
                'industry' => $value('industry'),
                'phone' => $value('phone'),
                'email' => ($e = $value('email')) ? mb_strtolower($e) : null,
                'billing_address' => $value('address'),
                'preferred_language' => $value('preferred_language') ?? Countries::language($value('country')),
                'source' => $source,
                'source_detail' => $value('source_detail'),
                'next_step' => $value('next_step'),
                'next_step_due' => $value('next_step') ? $value('next_step_due') : null,
                'created_by' => $by?->id,
                'updated_by' => $by?->id,
            ], fn ($v) => $v !== null));

            $mainIndex = collect($contacts)->search(fn ($c) => ! empty($c['main']));
            $names = [];
            foreach ($contacts as $i => $c) {
                $contact = Contacts::add($org, ['main' => $i === ($mainIndex === false ? 0 : $mainIndex)] + $c, $by, $options['contact_source'] ?? 'hq', quiet: true);
                $names[] = $contact->full_name;
            }

            $label = mb_strtolower(Sources::label($source));
            LeadTimeline::record($org->id, 'created', "Lead added ({$label})" . ($names ? ', with ' . implode(', ', $names) : '') . '.', [
                'actor' => $by, 'payload' => ['source' => $source, 'owner' => $ownerId, 'contacts' => count($names)],
                'dedupe_key' => $options['dedupe_key'] ?? null,
            ]);
            if ($org->next_step) {
                LeadTimeline::record($org->id, 'next_step_set', "Next step: {$org->next_step}, by " . NextStep::day($org->next_step_due) . '.', ['actor' => $by]);
            }
            LeadAccess::log('LEAD_CREATED', "Added the lead {$org->name}.", $org->id, [],
                ['name' => $org->name, 'country' => $org->country, 'industry' => $org->industry, 'source' => $source, 'assigned_to' => $ownerId]);

            if (! empty($options['copq']) && Copq::started($options['copq'])) {
                $main = $org->contacts()->first();
                Copq::store($org, $options['copq'], $by, $main?->full_name, $main?->email);
            }

            return $org->fresh();
        });
    }

    /** The details the lead page edits: column => key in $values */
    public const DETAILS = [
        'name' => 'name', 'country' => 'country', 'city' => 'city', 'industry' => 'industry', 'phone' => 'phone', 'email' => 'email',
        'billing_address' => 'address', 'preferred_language' => 'preferred_language', 'source' => 'source', 'source_detail' => 'source_detail',
        // Printed on its invoices (13 Nov 2026)
        'registration_number' => 'registration_number', 'tax_identification_number' => 'tax_number',
    ];

    /**
     * Save the lead page's details (DETAILS; the owner goes through LeadOwner, the next step through NextStep).
     * Returns the columns that changed; one timeline line names them, the source change gets its own.
     *
     * @throws ValidationException
     */
    public static function updateDetails(Organization $lead, array $values, User $by): array
    {
        $rules = array_intersect_key(self::rules(), array_flip(array_values(self::DETAILS)));
        $rules['source'] = ['required', Rule::in(array_keys(Sources::LABELS))];
        Validator::make($values, $rules, self::messages() + ['source.required' => 'Choose where the lead came from.'])->validate();
        $hasContactWay = collect([$values['phone'] ?? null, $values['email'] ?? null])->contains(fn ($v) => trim((string) $v) !== '')
            || $lead->contacts()->where(fn ($q) => $q->whereNotNull('email')->where('email', '!=', '')->orWhere(fn ($p) => $p->whereNotNull('phone')->where('phone', '!=', '')))->exists();
        if (! $hasContactWay) {
            throw ValidationException::withMessages(['phone' => self::UNREACHABLE]);
        }

        $new = [];
        foreach (self::DETAILS as $column => $key) {
            $v = trim((string) ($values[$key] ?? ''));
            $new[$column] = $v === '' ? null : ($column === 'email' ? mb_strtolower($v) : $v);
        }
        $old = $lead->only(array_keys($new));
        $changed = array_keys(array_filter($new, fn ($v, $c) => (string) $v !== (string) ($old[$c] ?? ''), ARRAY_FILTER_USE_BOTH));
        if (! $changed) {
            return [];
        }
        DB::transaction(function () use ($lead, $new, $old, $changed, $by) {
            DB::table('organizations')->where('id', $lead->id)->update(array_intersect_key($new, array_flip($changed)) + ['updated_by' => $by->id, 'updated_at' => now()]);
            if (in_array('source', $changed, true) || in_array('source_detail', $changed, true)) {
                LeadTimeline::record($lead->id, 'source_changed', 'Source changed from ' . Sources::label($old['source']) . ' to ' . Sources::label($new['source'])
                    . ($new['source_detail'] ? " ({$new['source_detail']})" : '') . '.', ['actor' => $by, 'payload' => ['from' => $old['source'], 'to' => $new['source']]]);
            }
            $others = array_diff($changed, ['source', 'source_detail']);
            if ($others) {
                $labels = ['name' => 'name', 'country' => 'country', 'city' => 'city', 'industry' => 'industry', 'phone' => 'phone', 'email' => 'email',
                    'billing_address' => 'address', 'preferred_language' => 'language', 'registration_number' => 'company number',
                    'tax_identification_number' => 'tax number'];
                LeadTimeline::record($lead->id, 'details_changed', 'Details changed: ' . collect($others)->map(fn ($c) => $labels[$c] ?? $c)->implode(', ') . '.', [
                    'actor' => $by, 'payload' => ['old' => array_intersect_key($old, array_flip($others))],
                ]);
            }
            LeadAccess::log('LEAD_UPDATED', "Changed the details of the lead {$lead->name}.", $lead->id,
                array_intersect_key($old, array_flip($changed)), array_intersect_key($new, array_flip($changed)));
        });
        $lead->refresh();

        return $changed;
    }

    /** Industries from Master data, for the select */
    public static function industries(): array
    {
        return DB::table('mdm_copq_industries')->orderBy('industry_name')->pluck('industry_name')->all();
    }
}
