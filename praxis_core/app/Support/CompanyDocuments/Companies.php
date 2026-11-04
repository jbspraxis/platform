<?php

namespace App\Support\CompanyDocuments;

use App\Support\Finance\Vat;
use Illuminate\Support\Facades\DB;

/**
 * The companies documents and deadlines are filed under (docs/COMPANY_DOCUMENTS.md): each legal entity in Master
 * data › Legal entities, Nigeria's first, and the head office. The head office is the GLOBAL legal entity when there
 * is one; until then it is 'group' (legal_entity_id null), for papers that belong to the whole group.
 */
final class Companies
{
    public const GROUP = 'group';

    /** @return list<array{key: string, id: ?string, region: ?string, name: string, label: string, active: bool}> */
    public static function all(): array
    {
        $rows = DB::table('mdm_legal_entities')
            ->orderByRaw("region_code = 'GLOBAL'")->orderByRaw('region_code = ? desc', [Vat::HOME])->orderBy('region_code')
            ->get(['id', 'region_code', 'legal_name', 'is_active']);

        $out = $rows->map(fn ($e) => [
            'key' => $e->id,
            'id' => $e->id,
            'region' => $e->region_code,
            'name' => $e->legal_name,
            'label' => $e->region_code === 'GLOBAL' ? "{$e->legal_name} (head office)" : "{$e->legal_name} ({$e->region_code})",
            'active' => (bool) $e->is_active,
        ])->all();

        // Group-wide papers: offered until a GLOBAL legal entity exists, and kept while anything is filed there
        if (! $rows->contains('region_code', 'GLOBAL')
            || DB::table('company_documents')->whereNull('legal_entity_id')->exists()
            || DB::table('company_deadlines')->whereNull('legal_entity_id')->exists()) {
            $out[] = ['key' => self::GROUP, 'id' => null, 'region' => null, 'name' => 'Head office', 'label' => 'Head office (group-wide)', 'active' => true];
        }

        return $out;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_column(self::all(), 'key');
    }

    public static function keyFor(?string $entityId): string
    {
        return $entityId ?? self::GROUP;
    }

    /** The legal entity id a filter or form key stands for (null: the head office, group-wide) */
    public static function idFor(string $key): ?string
    {
        return $key === self::GROUP || $key === '' ? null : $key;
    }

    public static function label(?string $entityId): string
    {
        foreach (self::all() as $c) {
            if ($c['id'] === $entityId) {
                return $c['label'];
            }
        }

        return $entityId === null ? 'Head office (group-wide)' : 'A company no longer in Legal entities';
    }

    /** The company's name without its country, for sentences ("JBS Praxis Limited: …") */
    public static function name(?string $entityId): string
    {
        return $entityId ? (string) (DB::table('mdm_legal_entities')->where('id', $entityId)->value('legal_name') ?? 'A former company') : 'Head office';
    }

    /** The legal entity's row, or null for the head office (group-wide) */
    public static function entity(?string $entityId): ?object
    {
        return $entityId ? DB::table('mdm_legal_entities')->where('id', $entityId)->first() : null;
    }
}
