<?php

namespace App\Support\Mdm;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The registration details of a JBS Praxis company in Master data › Legal entities: the rules its form saves with
 * (App\Livewire\Hq\Mdm\LegalEntityRegistry) and the one way anything else changes them, with an audit line. Company
 * documents apply what a certificate says through update() once a person has ticked it (docs/COMPANY_DOCUMENTS.md).
 */
final class LegalEntities
{
    /** The fields a document can fill in, with the rules the Legal entities form uses for them */
    public const RULES = [
        'legal_name' => ['required', 'string', 'max:150'],
        // Blank while the company is being registered; filled in once it is
        'registration_number' => ['nullable', 'string', 'max:100'],
        'tax_identification_number' => ['nullable', 'string', 'max:100'],
        'incorporated_on' => ['nullable', 'date_format:Y-m-d'],
        'company_type' => ['nullable', 'string', 'max:100'],
        'share_capital' => ['nullable', 'string', 'max:100'],
        'registered_address' => ['required', 'string'],
    ];

    /** The labels people see on Legal entities, for messages and audit lines */
    public const LABELS = [
        'legal_name' => 'registered company name',
        'registration_number' => 'company registration number (RC)',
        'tax_identification_number' => 'tax ID (NIU or TIN)',
        'incorporated_on' => 'date of registration',
        'company_type' => 'company type',
        'share_capital' => 'share capital',
        'registered_address' => 'registered office address',
    ];

    /**
     * Changes some of a company's registration details. $changes: field => new value (fields of RULES only; empty
     * text becomes null). Validates the company as it would be, writes only what differs and one audit line
     * (LEGAL_ENTITY_UPDATED, $source says where the change came from). Returns field => [before, after].
     *
     * @throws ValidationException
     */
    public static function update(string $id, array $changes, ?string $userId, string $source): array
    {
        $entity = DB::table('mdm_legal_entities')->where('id', $id)->first();
        if (! $entity) {
            throw ValidationException::withMessages(['entity' => 'This company is no longer in Legal entities. Reload the page.']);
        }
        $changes = array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, array_intersect_key($changes, self::RULES));
        $current = array_map(fn ($v) => $v === null ? null : (string) $v, array_intersect_key((array) $entity, self::RULES));
        if (isset($current['incorporated_on'])) {
            $current['incorporated_on'] = substr($current['incorporated_on'], 0, 10);
        }
        $after = array_merge($current, $changes);
        Validator::make($after, self::RULES, [], self::LABELS)->validate();

        $diff = [];
        foreach ($changes as $field => $value) {
            if (($current[$field] ?? null) !== $value) {
                $diff[$field] = [$current[$field] ?? null, $value];
            }
        }
        if (! $diff) {
            return [];
        }

        DB::transaction(function () use ($entity, $diff, $userId, $source) {
            DB::table('mdm_legal_entities')->where('id', $entity->id)->update(array_map(fn ($pair) => $pair[1], $diff) + ['updated_at' => now()]);
            DB::table('system_audit_logs')->insert([
                'user_id' => $userId,
                'action' => 'LEGAL_ENTITY_UPDATED',
                'description' => "{$entity->legal_name} ({$entity->region_code}): " . implode(', ', array_map(fn ($f) => self::LABELS[$f], array_keys($diff)))
                    . " changed ({$source}).",
                'target_table' => 'mdm_legal_entities',
                'target_id' => $entity->id,
                'old_values' => json_encode(array_map(fn ($pair) => $pair[0], $diff)),
                'new_values' => json_encode(array_map(fn ($pair) => $pair[1], $diff)),
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);
        });

        return $diff;
    }
}
