<?php

namespace App\Support\CompanyProfile;

/**
 * The edits the Company profile editor makes to one section (docs/COMPANY_PROFILE.md section 5).
 *
 * Every change that is not words — adding an item, removing one, moving one, a Held or Show switch — happens
 * on the structure, which both languages share. That is why English and French can never end up with a
 * different number of case stories, or disagree about which credentials the firm holds.
 *
 * A path names a list inside a section: ['items'] is the section's own list, ['groups', '<group id>', 'items']
 * is the list inside one of its items.
 */
class Editor
{
    /** The empty section, as a new version starts life */
    public static function blankStructure(): array
    {
        return ['shared' => [], 'lists' => []];
    }

    public static function blankText(): array
    {
        return ['fields' => [], 'lists' => []];
    }

    /** Add an item to a list. The same item, with the same id, is added to both languages. */
    public static function add(array &$structure, array &$en, array &$fr, string $sectionKey, array $path): string
    {
        $spec = self::spec($sectionKey, $path);
        $id = Profile::newId();

        $list = &self::structureList($structure, $path);
        $list[] = self::newStructureItem($id, $spec);
        unset($list);

        $wordsEn = &self::textList($en, $path);
        $wordsEn[$id] = self::newTextItem($spec);
        unset($wordsEn);

        $wordsFr = &self::textList($fr, $path);
        $wordsFr[$id] = self::newTextItem($spec);
        unset($wordsFr);

        return $id;
    }

    /** Take an item out of a list, in both languages */
    public static function remove(array &$structure, array &$en, array &$fr, string $sectionKey, array $path, string $id): void
    {
        $list = &self::structureList($structure, $path);
        $list = array_values(array_filter($list, fn ($item) => ($item['id'] ?? null) !== $id));
        unset($list);

        $wordsEn = &self::textList($en, $path);
        unset($wordsEn[$id]);
        unset($wordsEn);

        $wordsFr = &self::textList($fr, $path);
        unset($wordsFr[$id]);
        unset($wordsFr);
    }

    /** Move an item one place up (-1) or down (1). Order lives in the structure, so both languages move. */
    public static function move(array &$structure, string $sectionKey, array $path, string $id, int $delta): void
    {
        $list = &self::structureList($structure, $path);
        $from = null;
        foreach ($list as $index => $item) {
            if (($item['id'] ?? null) === $id) {
                $from = $index;
                break;
            }
        }

        $to = $from === null ? null : $from + $delta;
        if ($from === null || $to === null || $to < 0 || $to >= count($list)) {
            return;
        }

        [$list[$from], $list[$to]] = [$list[$to], $list[$from]];
        $list = array_values($list);
    }

    /**
     * Every list item the section holds, flat, so a screen can ask "is this id still there?" without walking
     * the tree itself.
     *
     * @return list<string>
     */
    public static function ids(array $structure): array
    {
        $ids = [];
        $walk = function (array $lists) use (&$walk, &$ids): void {
            foreach ($lists as $items) {
                foreach ($items as $item) {
                    if (isset($item['id'])) {
                        $ids[] = (string) $item['id'];
                    }
                    $walk($item['lists'] ?? []);
                }
            }
        };
        $walk($structure['lists'] ?? []);

        return $ids;
    }

    /**
     * Make sure a section's structure and both its texts carry every field the section is defined to have,
     * and nothing it is not. Run on load and before every save, so an older version opened in a newer editor
     * does not lose fields or keep retired ones.
     */
    public static function reconcile(string $sectionKey, array &$structure, array &$en, array &$fr): void
    {
        $spec = Sections::get($sectionKey);

        $structure['shared'] ??= [];
        $structure['lists'] ??= [];
        foreach ($spec['shared'] ?? [] as $field => $_) {
            $structure['shared'][$field] = $structure['shared'][$field] ?? '';
        }
        $structure['shared'] = array_intersect_key($structure['shared'], $spec['shared'] ?? []);

        self::reconcileFields($spec['fields'] ?? [], $en);
        self::reconcileFields($spec['fields'] ?? [], $fr);

        self::reconcileLists($spec['lists'] ?? [], $structure['lists'], $en['lists'], $fr['lists']);
    }

    private static function reconcileFields(array $fields, array &$text): void
    {
        $text['fields'] ??= [];
        $text['lists'] ??= [];
        foreach ($fields as $field => $_) {
            $text['fields'][$field] = (string) ($text['fields'][$field] ?? '');
        }
        $text['fields'] = array_intersect_key($text['fields'], $fields);
    }

    private static function reconcileLists(array $specs, array &$structure, array &$en, array &$fr): void
    {
        $structure = array_intersect_key($structure, $specs);
        $en = array_intersect_key($en, $specs);
        $fr = array_intersect_key($fr, $specs);

        foreach ($specs as $list => $spec) {
            $structure[$list] = array_values($structure[$list] ?? []);
            $en[$list] ??= [];
            $fr[$list] ??= [];
            $ids = [];

            foreach ($structure[$list] as $index => $item) {
                $id = (string) ($item['id'] ?? Profile::newId());
                $ids[] = $id;
                $item['id'] = $id;

                if ($spec['string'] ?? false) {
                    $structure[$list][$index] = ['id' => $id];
                    $en[$list][$id] = (string) ($en[$list][$id] ?? '');
                    $fr[$list][$id] = (string) ($fr[$list][$id] ?? '');

                    continue;
                }

                $item['shared'] = array_intersect_key($item['shared'] ?? [], $spec['shared'] ?? []);
                foreach ($spec['shared'] ?? [] as $field => $fieldSpec) {
                    $item['shared'][$field] = $item['shared'][$field] ?? ($fieldSpec['default'] ?? null);
                }
                $item['lists'] ??= [];

                self::reconcileItemText($spec['text'] ?? [], $en[$list], $id);
                self::reconcileItemText($spec['text'] ?? [], $fr[$list], $id);

                if ($spec['lists'] ?? false) {
                    self::reconcileLists($spec['lists'], $item['lists'], $en[$list][$id]['lists'], $fr[$list][$id]['lists']);
                } else {
                    $item['lists'] = [];
                    $en[$list][$id]['lists'] = [];
                    $fr[$list][$id]['lists'] = [];
                }

                $structure[$list][$index] = $item;
            }

            // Words with no item left to belong to
            $en[$list] = array_intersect_key($en[$list], array_flip($ids));
            $fr[$list] = array_intersect_key($fr[$list], array_flip($ids));
        }
    }

    private static function reconcileItemText(array $fields, array &$list, string $id): void
    {
        $list[$id] ??= [];
        $list[$id]['text'] ??= [];
        foreach ($fields as $field => $_) {
            $list[$id]['text'][$field] = (string) ($list[$id]['text'][$field] ?? '');
        }
        $list[$id]['text'] = array_intersect_key($list[$id]['text'], $fields);
        $list[$id]['lists'] ??= [];
    }

    // ---------------------------------------------------------------- finding things

    /** The list spec a path points at */
    public static function spec(string $sectionKey, array $path): array
    {
        $spec = Sections::get($sectionKey);
        $lists = $spec['lists'] ?? [];
        $list = null;

        foreach (self::segments($path) as $segment) {
            $list = $lists[$segment['list']] ?? throw new \InvalidArgumentException(
                "Unknown list [{$segment['list']}] in company profile section [{$sectionKey}]."
            );
            $lists = $list['lists'] ?? [];
        }

        return $list ?? throw new \InvalidArgumentException("Empty list path in section [{$sectionKey}].");
    }

    /** @return list<array{list: string, id: ?string}> */
    private static function segments(array $path): array
    {
        $segments = [];
        for ($i = 0; $i < count($path); $i += 2) {
            $segments[] = ['list' => (string) $path[$i], 'id' => isset($path[$i + 1]) ? (string) $path[$i + 1] : null];
        }

        return $segments;
    }

    private static function &structureList(array &$structure, array $path): array
    {
        $node = &$structure;
        foreach (self::segments($path) as $segment) {
            $node['lists'] ??= [];
            $node['lists'][$segment['list']] ??= [];
            $node = &$node['lists'][$segment['list']];

            if ($segment['id'] === null) {
                return $node;
            }

            $found = null;
            foreach ($node as $index => $item) {
                if (($item['id'] ?? null) === $segment['id']) {
                    $found = $index;
                    break;
                }
            }
            if ($found === null) {
                throw new \InvalidArgumentException("No item [{$segment['id']}] in list [{$segment['list']}].");
            }
            $node = &$node[$found];
        }

        return $node;
    }

    private static function &textList(array &$text, array $path): array
    {
        $node = &$text;
        foreach (self::segments($path) as $segment) {
            $node['lists'] ??= [];
            $node['lists'][$segment['list']] ??= [];
            $node = &$node['lists'][$segment['list']];

            if ($segment['id'] === null) {
                return $node;
            }

            $node[$segment['id']] ??= ['text' => [], 'lists' => []];
            $node = &$node[$segment['id']];
        }

        return $node;
    }

    private static function newStructureItem(string $id, array $spec): array
    {
        if ($spec['string'] ?? false) {
            return ['id' => $id];
        }

        $shared = [];
        foreach ($spec['shared'] ?? [] as $field => $fieldSpec) {
            $shared[$field] = $fieldSpec['default'] ?? null;
        }

        $lists = [];
        foreach ($spec['lists'] ?? [] as $list => $_) {
            $lists[$list] = [];
        }

        return ['id' => $id, 'shared' => $shared, 'lists' => $lists];
    }

    private static function newTextItem(array $spec): array|string
    {
        if ($spec['string'] ?? false) {
            return '';
        }

        $text = [];
        foreach ($spec['text'] ?? [] as $field => $_) {
            $text[$field] = '';
        }

        $lists = [];
        foreach ($spec['lists'] ?? [] as $list => $_) {
            $lists[$list] = [];
        }

        return ['text' => $text, 'lists' => $lists];
    }
}
