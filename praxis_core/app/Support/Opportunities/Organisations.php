<?php

namespace App\Support\Opportunities;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Adding an organisation from the "Open an opportunity" form, without leaving it: the same record Master data
 * and the Leads register hold, created as a Lead with the profile fields the website's free audit also fills.
 */
final class Organisations
{
    /** Time zones offered where the firm's clients are; anything else is typed */
    public const TIME_ZONES = ['Africa/Lagos', 'Africa/Douala', 'Africa/Accra', 'Africa/Abidjan', 'Africa/Nairobi', 'Africa/Johannesburg', 'Africa/Cairo', 'Europe/Paris', 'Europe/London', 'America/New_York', 'Asia/Dubai'];

    /** The profile's optional fields: column => Livewire property */
    public const PROFILE = [
        'industry' => 'newOrgIndustry',
        'enterprise_stage' => 'newOrgStage',
        'tech_reality' => 'newOrgSystems',
        'governance_status' => 'newOrgGovernance',
        'turnover_bracket' => 'newOrgTurnover',
        'budget_estimation' => 'newOrgBudget',
        'billing_address' => 'newOrgBillingAddress',
        'tax_identification_number' => 'newOrgTaxId',
    ];

    /** Industries and systems from Master data, for the selects */
    public static function industries(): array
    {
        return DB::table('mdm_copq_industries')->orderBy('industry_name')->pluck('industry_name')->all();
    }

    public static function systems(): array
    {
        return DB::table('mdm_copq_architectures')->orderBy('display_name')->pluck('display_name', 'tech_key')->all();
    }

    /** Validation rules for the form's properties (prefix newOrg…) */
    public static function rules(): array
    {
        return [
            'newOrgName' => ['required', 'string', 'max:150'],
            'newOrgCountry' => ['required', 'string', 'max:100'],
            'newOrgCity' => ['nullable', 'string', 'max:100'],
            'newOrgLanguage' => ['required', Rule::in(['EN', 'FR'])],
            'newOrgIndustry' => ['nullable', 'string', 'max:100'],
            'newOrgStage' => ['nullable', 'string', 'max:100'],
            'newOrgSystems' => ['nullable', Rule::in(array_keys(self::systems()))],
            'newOrgGovernance' => ['nullable', 'string', 'max:100'],
            'newOrgTurnover' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'newOrgBudget' => ['nullable', 'string', 'max:50'],
            'newOrgBillingAddress' => ['nullable', 'string', 'max:2000'],
            'newOrgTaxId' => ['nullable', 'string', 'max:100'],
            'newOrgTimezone' => ['required', 'string', 'max:50', 'timezone:all'],
        ];
    }

    public static function messages(): array
    {
        return [
            'newOrgName.required' => __('opportunities.create.new_org.errors.name'),
            'newOrgName.max' => __('opportunities.create.new_org.errors.name_long'),
            'newOrgCountry.required' => __('opportunities.create.new_org.errors.country'),
            'newOrgCountry.max' => __('opportunities.create.new_org.errors.too_long'),
            'newOrgCity.max' => __('opportunities.create.new_org.errors.too_long'),
            'newOrgLanguage.required' => __('opportunities.create.errors.language'),
            'newOrgLanguage.in' => __('opportunities.create.errors.language'),
            'newOrgIndustry.max' => __('opportunities.create.new_org.errors.too_long'),
            'newOrgStage.max' => __('opportunities.create.new_org.errors.too_long'),
            'newOrgSystems.in' => __('opportunities.create.new_org.errors.systems'),
            'newOrgGovernance.max' => __('opportunities.create.new_org.errors.too_long'),
            'newOrgTurnover.numeric' => __('opportunities.create.new_org.errors.turnover'),
            'newOrgTurnover.min' => __('opportunities.create.new_org.errors.turnover'),
            'newOrgTurnover.max' => __('opportunities.create.new_org.errors.turnover'),
            'newOrgBudget.max' => __('opportunities.create.new_org.errors.too_long'),
            'newOrgBillingAddress.max' => __('opportunities.create.new_org.errors.address_long'),
            'newOrgTaxId.max' => __('opportunities.create.new_org.errors.too_long'),
            'newOrgTimezone.required' => __('opportunities.create.new_org.errors.timezone'),
            'newOrgTimezone.timezone' => __('opportunities.create.new_org.errors.timezone'),
            'newOrgTimezone.max' => __('opportunities.create.new_org.errors.timezone'),
        ];
    }

    /** An organisation that already has this name (case and spacing aside), so the form can offer it instead */
    public static function existing(string $name): ?object
    {
        $needle = mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
        if ($needle === '') {
            return null;
        }

        return DB::table('organizations')->whereNull('deleted_at')
            ->whereRaw('LOWER(TRIM(name)) = ?', [$needle])
            ->first(['id', 'name', 'status']);
    }

    /** Create the organisation as a Lead, assigned to whoever adds it. $values: column => value (see PROFILE). */
    public static function create(array $values, ?string $byUserId): Organization
    {
        $org = Organization::create(array_filter([
            'name' => trim($values['name']),
            'status' => 'Lead',
            'pipeline_stage' => 'Lead Captured',
            'assigned_to' => $byUserId,
            'country' => trim($values['country']),
            'city' => self::clean($values['city'] ?? null),
            'preferred_language' => strtoupper($values['preferred_language'] ?? 'EN'),
            'timezone' => $values['timezone'] ?? 'Africa/Lagos',
            'industry' => self::clean($values['industry'] ?? null),
            'enterprise_stage' => self::clean($values['enterprise_stage'] ?? null),
            'tech_reality' => self::clean($values['tech_reality'] ?? null),
            'governance_status' => self::clean($values['governance_status'] ?? null),
            'turnover_bracket' => self::clean($values['turnover_bracket'] ?? null),
            'budget_estimation' => self::clean($values['budget_estimation'] ?? null),
            'billing_address' => self::clean($values['billing_address'] ?? null),
            'tax_identification_number' => self::clean($values['tax_identification_number'] ?? null),
            'created_by' => $byUserId,
            'updated_by' => $byUserId,
        ], fn ($v) => $v !== null));

        OpportunityAccess::log('ORGANIZATION_CREATED', "Added the organisation {$org->name} while opening an opportunity.", 'organizations', $org->id,
            [], array_intersect_key($org->getAttributes(), array_flip(['name', 'status', 'country', 'city', 'preferred_language', 'industry'])));

        return $org;
    }

    private static function clean($value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' || $value === null ? null : (string) $value;
    }
}
