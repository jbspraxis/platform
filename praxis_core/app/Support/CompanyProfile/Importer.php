<?php

namespace App\Support\CompanyProfile;

use App\Models\CompanyProfile\ProfileSection;
use App\Models\CompanyProfile\ProfileVersion;
use Illuminate\Support\Facades\DB;

/**
 * Turns the two files the copy is written in — content.en.json and content.fr.json, both in the shape
 * company-profile/CONTRACT.md fixes — into a version in the database.
 *
 * The two files are read side by side: the nth case story in English is the nth case story in French, and
 * they become one item with one id. Switches (`held`, `enabled`) and values that are not words (`image`,
 * `cta_url`) are taken from the English file, because they are facts about the firm, not about a language.
 */
class Importer
{
    /** @return list<string> what did not line up between the two files; empty means a clean import */
    public array $warnings = [];

    public function import(array $en, array $fr, ?string $note = null, bool $publish = true): ProfileVersion
    {
        return DB::transaction(function () use ($en, $fr, $note, $publish) {
            $version = Profile::makeVersion($note);

            foreach (Sections::KEYS as $key) {
                $spec = Sections::get($key);
                $structure = Editor::blankStructure();
                $textEn = Editor::blankText();
                $textFr = Editor::blankText();

                $sectionEn = is_array($en[$key] ?? null) ? $en[$key] : [];
                $sectionFr = is_array($fr[$key] ?? null) ? $fr[$key] : [];

                foreach ($spec['fields'] ?? [] as $field => $_) {
                    $textEn['fields'][$field] = (string) ($sectionEn[$field] ?? '');
                    $textFr['fields'][$field] = (string) ($sectionFr[$field] ?? $sectionEn[$field] ?? '');
                }
                foreach ($spec['shared'] ?? [] as $field => $_) {
                    $structure['shared'][$field] = $sectionEn[$field] ?? $sectionFr[$field] ?? '';
                }

                foreach ($spec['lists'] ?? [] as $list => $listSpec) {
                    $this->list(
                        $listSpec,
                        is_array($sectionEn[$list] ?? null) ? array_values($sectionEn[$list]) : [],
                        is_array($sectionFr[$list] ?? null) ? array_values($sectionFr[$list]) : [],
                        "{$key}.{$list}",
                        $structure['lists'][$list],
                        $textEn['lists'][$list],
                        $textFr['lists'][$list],
                    );
                }

                Editor::reconcile($key, $structure, $textEn, $textFr);

                ProfileSection::create([
                    'version_id' => $version->id,
                    'key' => $key,
                    'structure' => $structure,
                    'text_en' => $textEn,
                    'text_fr' => $textFr,
                ]);
            }

            if ($publish) {
                Profile::publish($version);
            }

            Profile::audit('COMPANY_PROFILE_IMPORTED', 'Imported '.$version->label().' from the content files', $version, [
                'warnings' => $this->warnings,
            ]);

            return $version->fresh('sections');
        });
    }

    private function list(array $spec, array $en, array $fr, string $where, ?array &$structure, ?array &$textEn, ?array &$textFr): void
    {
        $structure = [];
        $textEn = [];
        $textFr = [];

        // A list that carries no words at all (credentials: a name, an issuer and a switch) is taken from the
        // English file alone, so the French file need not repeat it and a different count is not a mistake.
        $hasWords = ($spec['string'] ?? false) || ($spec['text'] ?? []) || ($spec['lists'] ?? []);

        if ($hasWords && count($fr) !== count($en)) {
            $this->warnings[] = "{$where}: English has ".count($en).', French has '.count($fr)
                .'. The French items past '.count($en).' were left out.';
        }

        foreach ($en as $index => $itemEn) {
            $itemFr = $fr[$index] ?? $itemEn;
            $id = Profile::newId();

            if ($spec['string'] ?? false) {
                $structure[] = ['id' => $id];
                $textEn[$id] = (string) (is_array($itemEn) ? '' : $itemEn);
                $textFr[$id] = (string) (is_array($itemFr) ? '' : $itemFr);

                continue;
            }

            $itemEn = is_array($itemEn) ? $itemEn : [];
            $itemFr = is_array($itemFr) ? $itemFr : [];

            $shared = [];
            foreach ($spec['shared'] ?? [] as $field => $fieldSpec) {
                $shared[$field] = array_key_exists($field, $itemEn)
                    ? $itemEn[$field]
                    : ($itemFr[$field] ?? ($fieldSpec['default'] ?? null));
            }

            $wordsEn = ['text' => [], 'lists' => []];
            $wordsFr = ['text' => [], 'lists' => []];
            foreach ($spec['text'] ?? [] as $field => $_) {
                $wordsEn['text'][$field] = (string) ($itemEn[$field] ?? '');
                $wordsFr['text'][$field] = (string) ($itemFr[$field] ?? $itemEn[$field] ?? '');
            }

            $lists = [];
            foreach ($spec['lists'] ?? [] as $child => $childSpec) {
                $this->list(
                    $childSpec,
                    is_array($itemEn[$child] ?? null) ? array_values($itemEn[$child]) : [],
                    is_array($itemFr[$child] ?? null) ? array_values($itemFr[$child]) : [],
                    "{$where}[{$index}].{$child}",
                    $lists[$child],
                    $wordsEn['lists'][$child],
                    $wordsFr['lists'][$child],
                );
            }

            $structure[] = ['id' => $id, 'shared' => $shared, 'lists' => $lists];
            $textEn[$id] = $wordsEn;
            $textFr[$id] = $wordsFr;
        }
    }

    /** Read a pair of content files off disk; returns [en, fr] */
    public static function readFiles(string $directory): array
    {
        $read = function (string $path): array {
            if (! is_file($path)) {
                return [];
            }
            $data = json_decode((string) file_get_contents($path), true);

            return is_array($data) ? $data : [];
        };

        return [
            $read(rtrim($directory, '/\\').'/content.en.json'),
            $read(rtrim($directory, '/\\').'/content.fr.json'),
        ];
    }
}
