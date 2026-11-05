<?php

namespace App\Support\CompanyProfile;

use App\Models\CompanyProfile\ProfileSection;
use App\Models\CompanyProfile\ProfileVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The company profile engine (docs/COMPANY_PROFILE.md).
 *
 * Reading:
 *   Profile::data('en')        the published version, in the shape company-profile/CONTRACT.md fixes, with
 *                              unheld credentials and disabled facts already gone. This is what the PDF, the
 *                              deck, the intro letter and every share link read.
 *   Profile::data('fr', 3)     the same for version 3, so a draft can be previewed before it is published.
 *   Profile::editorData(...)   everything, including what is switched off and every item's id. The editor
 *                              only. It never leaves HQ.
 *
 * Writing: a published version is never edited. startDraft() copies it, the draft is edited, submit() sends
 * it for review and publish() makes it the published one and supersedes the old.
 */
class Profile
{
    /**
     * The profile as the renderers want it (company-profile/CONTRACT.md).
     *
     * @param  string  $locale  en or fr
     * @param  int|null  $versionId  a version NUMBER (v3 is 3); null is the published version
     */
    public static function data(string $locale, ?int $versionId = null): array
    {
        return self::build(self::resolve($versionId), $locale, forRender: true);
    }

    /** Everything the editor needs: ids, unheld credentials, disabled facts. Never rendered, never shared. */
    public static function editorData(string $locale, ?int $versionId = null): array
    {
        return self::build(self::resolve($versionId), $locale, forRender: false);
    }

    public static function dataFor(ProfileVersion $version, string $locale, bool $forRender = true): array
    {
        return self::build($version, $locale, $forRender);
    }

    // ---------------------------------------------------------------- versions

    /** The one published version, if there is one */
    public static function published(): ?ProfileVersion
    {
        return ProfileVersion::where('status', ProfileVersion::PUBLISHED)->first();
    }

    /** The draft or in-review version. There is at most one at a time, on purpose. */
    public static function open(): ?ProfileVersion
    {
        return ProfileVersion::whereIn('status', [ProfileVersion::DRAFT, ProfileVersion::IN_REVIEW])
            ->orderByDesc('number')->first();
    }

    /** What the editor opens on: the version being worked on, else the published one, else nothing */
    public static function working(): ?ProfileVersion
    {
        return self::open() ?? self::published() ?? ProfileVersion::orderByDesc('number')->first();
    }

    private static function resolve(?int $number): ProfileVersion
    {
        $version = $number === null
            ? self::published()
            : ProfileVersion::where('number', $number)->first();

        if (! $version) {
            throw new MissingProfile($number === null
                ? 'No company profile has been published yet.'
                : "Company profile v{$number} does not exist.");
        }

        return $version;
    }

    /**
     * Start a version. With no published version this makes v1 from the blank structure; otherwise it copies
     * the published one word for word. If a draft is already open it is handed back untouched, because two
     * people editing two drafts would quietly lose one of them.
     */
    public static function startDraft(?string $note = null): ProfileVersion
    {
        if ($open = self::open()) {
            return $open;
        }

        $source = self::published();

        return DB::transaction(function () use ($source, $note) {
            $version = self::makeVersion($note);

            foreach (Sections::KEYS as $key) {
                $from = $source?->sections->firstWhere('key', $key);
                ProfileSection::create([
                    'version_id' => $version->id,
                    'key' => $key,
                    'structure' => $from?->structure ?? ['shared' => [], 'lists' => []],
                    'text_en' => $from?->text_en ?? ['fields' => [], 'lists' => []],
                    'text_fr' => $from?->text_fr ?? ['fields' => [], 'lists' => []],
                ]);
            }

            self::audit('COMPANY_PROFILE_DRAFT_STARTED', $source
                ? 'Started '.$version->label().' from '.$source->label()
                : 'Started '.$version->label(), $version);

            return $version->fresh('sections');
        });
    }

    public static function makeVersion(?string $note = null, string $status = ProfileVersion::DRAFT): ProfileVersion
    {
        $number = (int) ProfileVersion::max('number') + 1;

        return ProfileVersion::create([
            'number' => $number,
            'reference' => self::reference($number),
            'status' => $status,
            'created_by' => Auth::id(),
            'note' => $note,
        ]);
    }

    /** JBS-CP-2026-11-v3: the year and month it was started, and the version */
    public static function reference(int $number): string
    {
        return 'JBS-CP-'.now('Africa/Lagos')->format('Y-m').'-v'.$number;
    }

    public static function submit(ProfileVersion $version, ?string $note = null): ProfileVersion
    {
        if ($version->status !== ProfileVersion::DRAFT) {
            throw new \RuntimeException('Only a draft can be sent for review.');
        }

        $version->update([
            'status' => ProfileVersion::IN_REVIEW,
            'submitted_by' => Auth::id(),
            'submitted_at' => now(),
            'note' => $note ?? $version->note,
        ]);

        self::audit('COMPANY_PROFILE_SUBMITTED', $version->label().' was sent for review', $version);

        return $version;
    }

    /** Send a version in review back to its author, with a reason */
    public static function returnToDraft(ProfileVersion $version, string $reason): ProfileVersion
    {
        if ($version->status !== ProfileVersion::IN_REVIEW) {
            throw new \RuntimeException('Only a version in review can be sent back.');
        }

        $version->update(['status' => ProfileVersion::DRAFT, 'note' => $reason]);
        self::audit('COMPANY_PROFILE_RETURNED', $version->label().' was sent back: '.$reason, $version);

        return $version;
    }

    /**
     * Approve and publish. The version that was published becomes superseded; it is never changed, so every
     * profile that ever went out can still be rebuilt exactly as it was sent.
     */
    public static function publish(ProfileVersion $version): ProfileVersion
    {
        if (! $version->isOpen()) {
            throw new \RuntimeException('Only a draft or a version in review can be published.');
        }

        return DB::transaction(function () use ($version) {
            ProfileVersion::where('status', ProfileVersion::PUBLISHED)
                ->update(['status' => ProfileVersion::SUPERSEDED, 'superseded_at' => now()]);

            $version->update([
                'status' => ProfileVersion::PUBLISHED,
                'reviewed_by' => Auth::id(),
                'published_at' => now(),
            ]);

            self::audit('COMPANY_PROFILE_PUBLISHED', 'Published '.$version->label().' ('.$version->reference.')', $version);

            return $version;
        });
    }

    /** Throw a draft away. Only a draft: nothing that has been published is ever deleted. */
    public static function discard(ProfileVersion $version): void
    {
        if (! $version->isOpen()) {
            throw new \RuntimeException('A published version cannot be deleted.');
        }

        DB::transaction(function () use ($version) {
            ProfileSection::where('version_id', $version->id)->delete();
            self::audit('COMPANY_PROFILE_DRAFT_DISCARDED', 'Threw away '.$version->label(), $version);
            $version->delete();
        });
    }

    // ---------------------------------------------------------------- reading

    private static function build(ProfileVersion $version, string $locale, bool $forRender): array
    {
        if (! Sections::isLocale($locale)) {
            throw new \InvalidArgumentException("The company profile has no [{$locale}] version.");
        }

        $version->loadMissing('sections');
        $out = ['meta' => [
            'version' => $version->label(),
            'published_at' => $version->published_at?->format('Y-m-d'),
            'locale' => $locale,
            'reference' => $version->reference,
        ]];

        if (! $forRender) {
            $out['meta']['status'] = $version->status;
            $out['meta']['version_number'] = $version->number;
        }

        foreach (Sections::KEYS as $key) {
            $section = $version->sections->firstWhere('key', $key);
            $out[$key] = self::section($key, $section, $locale, $forRender);
        }

        return $out;
    }

    private static function section(string $key, ?ProfileSection $section, string $locale, bool $forRender): array
    {
        $spec = Sections::get($key);
        $structure = $section?->structure ?? [];
        $text = $section?->text($locale) ?? [];

        $out = [];
        foreach ($spec['fields'] ?? [] as $field => $_) {
            $out[$field] = (string) ($text['fields'][$field] ?? '');
        }
        foreach ($spec['shared'] ?? [] as $field => $_) {
            $out[$field] = $structure['shared'][$field] ?? '';
        }
        foreach ($spec['lists'] ?? [] as $list => $listSpec) {
            $out[$list] = self::list($listSpec, $structure['lists'][$list] ?? [], $text['lists'][$list] ?? [], $forRender);
        }

        // The logo wall is not edited here: it comes from the logo library (PR 3) through the provider.
        if ($key === 'clients') {
            $out['logos'] = app(ClientLogos::class)->all();
        }

        return self::order($out, $spec['shape'] ?? null);
    }

    /**
     * One list. The order and the switches come from the structure; the words come from the locale's text,
     * keyed by the item's id. An item the structure does not know about cannot appear.
     */
    private static function list(array $spec, array $structure, array $text, bool $forRender): array
    {
        $out = [];

        foreach ($structure as $item) {
            $id = (string) ($item['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $gate = $spec['gate'] ?? null;
            if ($forRender && $gate !== null && ! ($item['shared'][$gate] ?? false)) {
                continue;
            }

            if ($spec['string'] ?? false) {
                $value = (string) ($text[$id] ?? '');
                $out[] = $forRender ? $value : ['id' => $id, 'value' => $value];

                continue;
            }

            $row = $forRender ? [] : ['id' => $id];
            $words = $text[$id] ?? [];

            foreach ($spec['text'] ?? [] as $field => $_) {
                $row[$field] = (string) ($words['text'][$field] ?? '');
            }
            foreach ($spec['shared'] ?? [] as $field => $fieldSpec) {
                $row[$field] = $item['shared'][$field] ?? ($fieldSpec['default'] ?? null);
            }
            foreach ($spec['lists'] ?? [] as $child => $childSpec) {
                $row[$child] = self::list($childSpec, $item['lists'][$child] ?? [], $words['lists'][$child] ?? [], $forRender);
            }

            $out[] = self::order($row, $spec['shape'] ?? null, $forRender ? null : 'id');
        }

        return $out;
    }

    /** Put the keys in the order the contract shows them */
    private static function order(array $row, ?array $shape, ?string $first = null): array
    {
        if ($shape === null && $first === null) {
            return $row;
        }

        $out = [];
        foreach (array_filter([$first]) as $key) {
            if (array_key_exists($key, $row)) {
                $out[$key] = $row[$key];
            }
        }
        foreach ($shape ?? [] as $key) {
            if (array_key_exists($key, $row)) {
                $out[$key] = $row[$key];
            }
        }

        return $out + $row;
    }

    // ---------------------------------------------------------------- what changed

    /**
     * What is different between a version and the published one, as lines a person can read. Used by the
     * "changed since last published" hint on the editor and on the versions screen.
     *
     * @return list<array{section: string, label: string, locale: string, field: string, was: string, now: string}>
     */
    public static function changes(ProfileVersion $version, ?ProfileVersion $against = null): array
    {
        $against ??= self::published();

        if (! $against || $against->is($version)) {
            return [];
        }

        $changes = [];
        foreach (Sections::LOCALES as $locale) {
            $before = self::flatten(self::build($against, $locale, forRender: false));
            $after = self::flatten(self::build($version, $locale, forRender: false));

            foreach (array_keys($before + $after) as $path) {
                if (str_starts_with($path, 'meta.') || str_ends_with($path, '.id')) {
                    continue;
                }
                $was = $before[$path] ?? '';
                $now = $after[$path] ?? '';
                if ($was === $now) {
                    continue;
                }
                $section = strtok($path, '.');
                $changes[] = [
                    'section' => $section,
                    'label' => in_array($section, Sections::KEYS, true) ? Sections::label($section) : $section,
                    'locale' => $locale,
                    'field' => $path,
                    'was' => $was,
                    'now' => $now,
                ];
            }
        }

        return $changes;
    }

    /** @return array<string, string> dotted path => value, for the diff */
    private static function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $out += self::flatten($value, $path);
            } else {
                $out[$path] = is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value;
            }
        }

        return $out;
    }

    // ---------------------------------------------------------------- bits and pieces

    public static function newId(): string
    {
        return (string) Str::uuid();
    }

    private static bool $auditing = true;

    /**
     * Run something without writing to the audit log. For the seeder: a database being built has no one to
     * attribute anything to, and an empty audit log is how the rest of the suite knows a screen wrote nothing.
     */
    public static function withoutAudit(callable $callback): mixed
    {
        self::$auditing = false;

        try {
            return $callback();
        } finally {
            self::$auditing = true;
        }
    }

    public static function audit(string $action, string $description, ?ProfileVersion $version = null, array $values = []): void
    {
        if (! self::$auditing) {
            return;
        }

        rescue(fn () => DB::table('system_audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => Str::limit($description, 1900, ''),
            'target_table' => 'company_profile_versions',
            'target_id' => $version?->id,
            'new_values' => $values ? json_encode($values, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]), null, false);
    }
}
