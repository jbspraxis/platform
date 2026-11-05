<?php

namespace App\Support\CompanyProfile;

/**
 * The book's shape: the grid every page is set on, and the order of its pages.
 *
 * A4 landscape, twelve columns, and one page per <div>, so nothing reflows across a page break and no
 * section can split badly: the renderer decides where a page ends, not dompdf. Three of the sections are
 * open sets — the problems we answer, the work we show and the logos on the wall — so those pages are
 * counted from the content rather than fixed, and the book runs to ten, eleven or twelve pages depending
 * on how much there is. The content the owner approved on 6 November 2026 sets as twelve.
 */
final class Layout
{
    /** A4 landscape, in points. */
    public const PAGE_WIDTH = 841.89;

    public const PAGE_HEIGHT = 595.28;

    public const MARGIN_X = 54.0;

    public const MARGIN_TOP = 46.0;

    public const MARGIN_BOTTOM = 52.0;

    /** Twelve columns with a 16pt gutter: the reading measure is five of them, about 62 characters. */
    public const COLUMNS = 12;

    public const GUTTER = 16.0;

    public const CONTENT_WIDTH = self::PAGE_WIDTH - (2 * self::MARGIN_X);

    /** The split in the problem pages: the rule turns teal where the answer starts. */
    public const PROBLEM_COLUMNS = 4;

    /**
     * The width of n columns, gutters included, always rounded down. Columns are set as inline blocks,
     * and a row whose widths add up to a hundredth of a point more than the row is wide wraps every
     * column onto a line of its own, so nothing here is ever allowed to round up.
     */
    public static function columnWidth(int $n): float
    {
        $column = (self::CONTENT_WIDTH - ((self::COLUMNS - 1) * self::GUTTER)) / self::COLUMNS;

        return floor((($n * $column) + (($n - 1) * self::GUTTER)) * 100) / 100;
    }

    /** The content width divided into $count equal columns with a gutter between them, rounded down. */
    public static function even(int $count): float
    {
        $count = max($count, 1);

        return floor(((self::CONTENT_WIDTH - (($count - 1) * self::GUTTER)) / $count) * 100) / 100;
    }

    /** The height a page has for content, between the margins. */
    public static function contentHeight(): float
    {
        return self::PAGE_HEIGHT - self::MARGIN_TOP - self::MARGIN_BOTTOM;
    }

    /**
     * The pages, in order. Each one names the partial that draws it, the surface it sits on and the
     * slice of content it carries.
     *
     * @return list<array{view: string, surface: string, data: array<string, mixed>}>
     */
    public static function pages(array $data): array
    {
        $pages = [
            ['view' => 'cover', 'surface' => 'paper', 'data' => $data['cover'] ?? []],
            ['view' => 'pov', 'surface' => 'paper', 'data' => [
                'pov' => $data['pov'] ?? [],
                'principles' => $data['method']['principles'] ?? [],
            ]],
            ['view' => 'pillars', 'surface' => 'paper', 'data' => $data['system'] ?? []],
        ];

        foreach (self::problemPages($data['problems'] ?? []) as $page) {
            $pages[] = ['view' => 'problems', 'surface' => 'paper', 'data' => $page];
        }

        foreach (self::practicePages($data['practices'] ?? [], $data['method'] ?? []) as $page) {
            $pages[] = ['view' => 'practices', 'surface' => 'paper', 'data' => $page];
        }
        foreach (self::teamPages(self::teams($data['teams'] ?? [])) as $page) {
            $pages[] = ['view' => 'teams', 'surface' => 'paper', 'data' => $page];
        }

        foreach (self::workPages($data['work'] ?? []) as $page) {
            $pages[] = $page;
        }

        $platforms = self::platforms($data['work']['items'] ?? []);
        foreach (self::clientPages($data['clients'] ?? [], $platforms) as $page) {
            $pages[] = ['view' => 'clients', 'surface' => 'paper', 'data' => $page];
        }

        $pages[] = ['view' => 'facts', 'surface' => 'ink', 'data' => [
            'security' => $data['security'] ?? [],
            'facts' => self::facts($data['facts'] ?? []),
        ]];
        $pages[] = ['view' => 'back', 'surface' => 'paper', 'data' => [
            'contact' => $data['contact'] ?? [],
            'meta' => $data['meta'] ?? [],
        ]];

        return $pages;
    }

    /**
     * The problems, cut into pages that fit. The groups stay whole where they can; a group longer than a
     * page carries on over the next one, under the same domain with a continuation mark, so a reader
     * never meets a domain label with nothing beneath it.
     *
     * @return list<array<string, mixed>>
     */
    public static function problemPages(array $problems): array
    {
        $groups = array_values($problems['groups'] ?? []);
        if ($groups === []) {
            return [];
        }

        // Filled first to find out how many pages the list needs, then filled again with every page
        // holding the same fraction of what it could, so the last page does not end up with two problems
        // next to a page of seven: that reads as running out rather than as a designed ending. The
        // smallest fraction that still fits the same number of pages is the most even one, and it is
        // found by halving rather than reasoned about, because the costs are not quite linear.
        $pages = self::fillProblems($groups, $problems, 1.0);
        $count = count($pages);

        if ($count > 1) {
            $low = 0.5;
            $high = 1.0;
            for ($step = 0; $step < 6; $step++) {
                $middle = ($low + $high) / 2;
                $tried = self::fillProblems($groups, $problems, $middle);
                if (count($tried) <= $count) {
                    $pages = $tried;
                    $high = $middle;
                } else {
                    $low = $middle;
                }
            }
        }

        $closing = (string) ($problems['closing'] ?? '');

        $last = count($pages) - 1;
        foreach ($pages as $index => $page) {
            $pages[$index] = $page + [
                'title' => $problems['title'] ?? '',
                'lead' => $problems['lead'] ?? '',
                'closing' => $index === $last ? $closing : '',
            ];
        }

        return $pages;
    }

    /**
     * Fill pages with the problems, each page taking $fraction of what it could hold.
     *
     * @return list<array<string, mixed>>
     */
    private static function fillProblems(array $groups, array $problems, float $fraction): array
    {
        // What the template spends, in points: the head with the section title and its lead, the quiet
        // running head on a later page, the domain label that sits above the first problem of its group
        // in the problem column, the air before a new domain, and the hairline between two problems.
        $head = self::headHeight((string) ($problems['lead'] ?? ''));
        $runningHead = 28.0;
        $label = 16.0;
        $groupGap = 12.0;
        $separator = 19.5;
        $slack = 8.0;

        $pages = [];
        $current = ['groups' => [], 'first' => true];
        $budget = (self::contentHeight() - $head - $slack) * $fraction;

        foreach ($groups as $group) {
            $items = array_values($group['items'] ?? []);
            $pending = ['domain' => $group['domain'] ?? '', 'items' => [], 'continued' => false];

            foreach ($items as $item) {
                $cost = self::problemHeight($item) + ($pending['items'] === [] ? $label + $groupGap : $separator);

                if ($cost > $budget && ($current['groups'] !== [] || $pending['items'] !== [])) {
                    if ($pending['items'] !== []) {
                        $current['groups'][] = $pending;
                        $pending = ['domain' => $group['domain'] ?? '', 'items' => [], 'continued' => true];
                    }
                    $pages[] = $current;
                    $current = ['groups' => [], 'first' => false];
                    $budget = (self::contentHeight() - $runningHead - $slack) * $fraction;
                    $cost = self::problemHeight($item) + $label;
                }

                $pending['items'][] = $item;
                $budget -= $cost;
            }

            if ($pending['items'] !== []) {
                $current['groups'][] = $pending;
            }
        }

        if ($current['groups'] !== []) {
            $pages[] = $current;
        }

        return $pages;
    }

    /**
     * Move the last thing on page $index onto the page after it. The renderer calls this when it has
     * test-rendered a page and found that it does not fit on one: counting gets a page close, and this
     * makes it exact, whatever the language does to the copy. Returns null for a page that has nothing to
     * give away, which is every page whose shape the design fixes.
     *
     * @param  list<array{view: string, surface: string, data: array<string, mixed>}>  $pages
     * @return list<array{view: string, surface: string, data: array<string, mixed>}>|null
     */
    public static function shrink(array $pages, int $index): ?array
    {
        $page = $pages[$index] ?? null;
        if ($page === null) {
            return null;
        }

        return match ($page['view']) {
            'problems' => self::shrinkProblems($pages, $index),
            'teams', 'work-grid' => self::shrinkItems($pages, $index),
            'clients' => self::shrinkClients($pages, $index),
            'practices' => self::shrinkPractices($pages, $index),
            default => null,
        };
    }

    /** The practices and the method share a page until the copy says they cannot; then each takes one. */
    private static function shrinkPractices(array $pages, int $index): ?array
    {
        $data = $pages[$index]['data'];
        if (($data['practices'] ?? []) === [] || ($data['method'] ?? []) === []) {
            return null;
        }

        $pages[$index]['data'] = ['practices' => $data['practices'], 'method' => []];
        array_splice($pages, $index + 1, 0, [[
            'view' => 'practices',
            'surface' => 'paper',
            'data' => ['practices' => [], 'method' => $data['method']],
        ]]);

        return $pages;
    }

    private static function shrinkProblems(array $pages, int $index): ?array
    {
        $groups = array_values($pages[$index]['data']['groups'] ?? []);
        $last = count($groups) - 1;
        if ($last < 0) {
            return null;
        }
        if ($last === 0 && count($groups[0]['items']) < 2) {
            return null;   // one problem on the page: there is nothing smaller to make
        }

        $moved = array_pop($groups[$last]['items']);
        $domain = $groups[$last]['domain'];
        if ($groups[$last]['items'] === []) {
            array_pop($groups);
        }
        $pages[$index]['data']['groups'] = array_values($groups);

        if (($pages[$index + 1]['view'] ?? '') === 'problems') {
            $nextGroups = array_values($pages[$index + 1]['data']['groups']);
            if (($nextGroups[0]['domain'] ?? null) === $domain) {
                array_unshift($nextGroups[0]['items'], $moved);
            } else {
                array_unshift($nextGroups, ['domain' => $domain, 'items' => [$moved], 'continued' => true]);
            }
            $pages[$index + 1]['data']['groups'] = array_values($nextGroups);

            $closing = $pages[$index]['data']['closing'] ?? '';
            if ($closing !== '') {
                $pages[$index]['data']['closing'] = '';
                $pages[$index + 1]['data']['closing'] = $closing;
            }

            return $pages;
        }

        $data = $pages[$index]['data'];
        $pages[$index]['data']['closing'] = '';
        $data['groups'] = [['domain' => $domain, 'items' => [$moved], 'continued' => true]];
        $data['first'] = false;
        array_splice($pages, $index + 1, 0, [['view' => 'problems', 'surface' => 'paper', 'data' => $data]]);

        return $pages;
    }

    private static function shrinkItems(array $pages, int $index): ?array
    {
        $items = array_values($pages[$index]['data']['items'] ?? []);
        if (count($items) < 2) {
            return null;
        }

        $moved = array_pop($items);
        $pages[$index]['data']['items'] = $items;

        if (($pages[$index + 1]['view'] ?? '') === $pages[$index]['view']) {
            array_unshift($pages[$index + 1]['data']['items'], $moved);

            return $pages;
        }

        $data = $pages[$index]['data'];
        $data['items'] = [$moved];
        $data['first'] = false;
        // Anything that closes the section goes with the page that now ends it, not on both.
        $pages[$index]['data']['platforms'] = [];
        array_splice($pages, $index + 1, 0, [[
            'view' => $pages[$index]['view'],
            'surface' => $pages[$index]['surface'],
            'data' => $data,
        ]]);

        return $pages;
    }

    private static function shrinkClients(array $pages, int $index): ?array
    {
        $rows = array_values($pages[$index]['data']['rows'] ?? []);
        if (count($rows) < 2) {
            return null;
        }

        $moved = array_pop($rows);
        $pages[$index]['data']['rows'] = $rows;

        if (($pages[$index + 1]['view'] ?? '') === 'clients') {
            array_unshift($pages[$index + 1]['data']['rows'], $moved);

            return $pages;
        }

        $data = $pages[$index]['data'];
        $data['rows'] = [$moved];
        $data['first'] = false;
        array_splice($pages, $index + 1, 0, [['view' => 'clients', 'surface' => 'paper', 'data' => $data]]);

        return $pages;
    }

    /**
     * The head every section page opens with: the title in six columns beside its lead in six, whichever
     * is taller, then the gap and the rule. The French lead is often a line longer than the English one,
     * which is exactly why this is measured rather than assumed.
     */
    private static function headHeight(string $lead): float
    {
        return max(31.0, self::body($lead, self::columnWidth(6), 11.0) * 17.0) + 32.0;
    }

    /**
     * How tall one problem and its answer will set. Measured from the characters, because dompdf measures
     * far too late to lay pages out by: the problem runs in four columns of Newsreader at 10.5/15, the
     * answer in seven of Plus Jakarta Sans at 8.6/13, and the taller of the two sets the row.
     */
    private static function problemHeight(array $item): float
    {
        $problem = self::serif((string) ($item['problem'] ?? ''), self::columnWidth(self::PROBLEM_COLUMNS)) * 15.0;
        $answer = self::body((string) ($item['answer'] ?? ''), self::answerWidth()) * 13.0;

        return max($problem, $answer);
    }

    /** The column the answers and the team details are set in: everything the problem column leaves. */
    public static function answerWidth(): float
    {
        return floor((self::CONTENT_WIDTH - self::columnWidth(self::PROBLEM_COLUMNS) - self::GUTTER) * 100) / 100;
    }

    /** Lines of body text in a column, from the same font metrics dompdf will set it with. */
    private static function body(string $text, float $width, float $size = 8.6): int
    {
        return Measure::instance()->lines($text, $width, 'Jakarta', $size);
    }

    /** Lines of Newsreader, at the cut the stylesheet uses for that size. */
    private static function serif(string $text, float $width, float $size = 10.5, string $family = 'Newsreader Text'): int
    {
        return Measure::instance()->lines($text, $width, $family, $size);
    }

    /**
     * The practices and the Praxis Method share a page, which is how the book is meant to read: what the
     * firm does, then how it does it. The French copy runs longer, and rather than squeezing the type
     * until it fits, the page is measured and splits in two when it has to.
     *
     * @return list<array<string, mixed>>
     */
    public static function practicePages(array $practices, array $method): array
    {
        $together = ['practices' => $practices, 'method' => $method];
        if ($practices === [] || $method === []) {
            return [$together];
        }

        $height = self::practicesHeight($practices) + 44.5 + self::methodHeight($method);

        return $height + 24.0 <= self::contentHeight()
            ? [$together]
            : [['practices' => $practices, 'method' => []], ['practices' => [], 'method' => $method]];
    }

    private static function practicesHeight(array $practices): float
    {
        $items = array_values($practices['items'] ?? []);
        $width = self::even(count($items));
        $tallest = 0.0;

        foreach ($items as $item) {
            $height = 13.5 + (self::serif((string) ($item['name'] ?? ''), $width, 12.5) * 16.5)
                + 9.0 + (self::body((string) ($item['summary'] ?? ''), $width) * 13.0);

            if (($item['capabilities'] ?? []) !== []) {
                $height += 10.0;
                foreach ($item['capabilities'] as $capability) {
                    $height += self::body((string) $capability, $width, 7.4) * 11.0;
                }
            }

            $tallest = max($tallest, $height);
        }

        return self::headHeight((string) ($practices['lead'] ?? '')) - 32.0 + 22.0 + $tallest;
    }

    private static function methodHeight(array $method): float
    {
        $stages = array_values($method['stages'] ?? []);
        $width = self::even(count($stages));
        $tallest = 0.0;

        foreach ($stages as $stage) {
            $tallest = max($tallest, 14.5 + (self::serif((string) ($stage['name'] ?? ''), $width, 12.5) * 16.5)
                + 8.0 + (self::body((string) ($stage['body'] ?? ''), $width) * 13.0));
        }

        $head = max(
            self::serif((string) ($method['title'] ?? ''), self::columnWidth(6), 19.0, 'Newsreader Headline') * 27.0,
            10.0 + (self::body((string) ($method['lead'] ?? ''), self::columnWidth(6)) * 13.0)
        );

        return $head + 18.0 + 12.0 + $tallest;
    }

    /**
     * The teams, cut into pages that fit. Four teams set on one page in English; the French summaries run
     * longer, so rather than trusting that they will, the page is counted the same way the problems are.
     *
     * @return list<array<string, mixed>>
     */
    public static function teamPages(array $teams): array
    {
        $items = array_values($teams['items'] ?? []);
        if ($items === []) {
            return [];
        }

        $pages = [];
        $current = [];
        $budget = self::contentHeight() - self::headHeight((string) ($teams['lead'] ?? '')) - 18.0 - 8.0;

        foreach ($items as $team) {
            $cost = self::teamHeight($team) + ($current === [] ? 0.0 : 21.5);

            if ($cost > $budget && $current !== []) {
                $pages[] = ['items' => $current, 'first' => $pages === []];
                $current = [];
                $budget = self::contentHeight() - 28.0 - 18.0 - 8.0;
                $cost = self::teamHeight($team);
            }

            $current[] = $team;
            $budget -= $cost;
        }

        if ($current !== []) {
            $pages[] = ['items' => $current, 'first' => $pages === []];
        }

        foreach ($pages as $index => $page) {
            $pages[$index] = $page + [
                'title' => $teams['title'] ?? '',
                'lead' => $teams['lead'] ?? '',
            ];
        }

        return $pages;
    }

    /** One team's row: the name and its experience on the left, everything else on the right. */
    private static function teamHeight(array $team): float
    {
        $right = self::answerWidth();
        $height = self::body((string) ($team['summary'] ?? ''), $right) * 13.0;

        if (($team['disciplines'] ?? []) !== []) {
            $height += 7.0 + (self::body(implode('  ·  ', $team['disciplines']), $right, 7.4) * 10.5);
        }

        if (($team['credentials'] ?? []) !== []) {
            $credentials = implode('  ·  ', array_map(
                fn ($credential) => trim(($credential['name'] ?? '').' ('.($credential['issuer'] ?? '').')'),
                $team['credentials']
            ));
            $height += 5.0 + (self::body('Credentials held  '.$credentials, $right, 7.4) * 10.5);
        }

        $left = (self::serif((string) ($team['name'] ?? ''), self::columnWidth(4), 12.5) * 16.5)
            + (($team['experience'] ?? '') !== '' ? 17.5 : 0.0);

        return max($height, $left);
    }

    /**
     * Selected work, on one page: every case as an entry, three to a row, which is how a reader takes
     * in breadth.
     *
     * The first case used to have a whole page of place photography to itself, with its story on a
     * Paper panel. The owner's twelve pages bought that page back: fifteen problems need three pages at
     * a size anyone would read, and this is the page that had to give. The entries carry each case's own
     * line and its outcome; the problem it started from and what was built for it are in the proposal,
     * where a reader who has got this far will ask for them. The platforms close the clients page.
     *
     * @return list<array<string, mixed>>
     */
    public static function workPages(array $work): array
    {
        $items = array_values($work['items'] ?? []);
        if ($items === []) {
            return [];
        }

        $pages = [];

        foreach (array_chunk($items, 6) as $index => $chunk) {
            $pages[] = [
                'view' => 'work-grid',
                'surface' => 'paper',
                'data' => [
                    'title' => $work['title'] ?? '',
                    'lead' => $work['lead'] ?? '',
                    'first' => $index === 0,
                    'items' => $chunk,
                ],
            ];
        }

        return $pages;
    }

    /**
     * The cases that have a screen of their own. A case whose client the copy describes rather than
     * names has none, which is right: there is no screen to show without naming the client.
     *
     * @return list<array<string, mixed>>
     */
    public static function platforms(array $items): array
    {
        $platforms = [];
        foreach ($items as $item) {
            $screen = Assets::screenForClient((string) ($item['client'] ?? ''));
            if ($screen !== null) {
                $platforms[] = $item + ['screen' => $screen];
            }
        }

        return $platforms;
    }

    /**
     * The logo wall: five to a row, four rows to a page, which keeps the wall quiet. A longer list runs
     * on to a second page rather than shrinking every mark to fit.
     *
     * @return list<array<string, mixed>>
     */
    public static function clientPages(array $clients, array $platforms = []): array
    {
        $logos = array_values(array_filter(
            $clients['logos'] ?? [],
            fn ($logo) => is_array($logo) && is_string($logo['path'] ?? null) && is_file($logo['path'])
        ));

        if ($logos === []) {
            return [];
        }

        $pages = [];
        $chunks = array_chunk($logos, 15);
        foreach ($chunks as $index => $chunk) {
            $pages[] = [
                'title' => $clients['title'] ?? '',
                'note' => $clients['note'] ?? '',
                'rows' => array_chunk($chunk, 5),
                'first' => $index === 0,
                // The platforms close the page, and the book's proof: each client's own system as its
                // team signs in to it. A logo wall says who hired us; the strip says what they got.
                'platforms' => $index === count($chunks) - 1 ? $platforms : [],
            ];
        }

        return $pages;
    }

    /**
     * Teams, with the credentials the owner holds. A credential whose switch is off never reaches a page,
     * and a team is never named after a person: the contract and docs/BRAND.md both say so.
     */
    public static function teams(array $teams): array
    {
        $teams['items'] = array_values(array_map(function (array $team) {
            $team['credentials'] = array_values(array_filter(
                $team['credentials'] ?? [],
                fn ($credential) => is_array($credential) && ($credential['held'] ?? false) === true
            ));

            return $team;
        }, $teams['items'] ?? []));

        return $teams;
    }

    /** Company facts, with the switched-off ones dropped: the Cameroon entity is off until the owner says. */
    public static function facts(array $facts): array
    {
        $facts['items'] = array_values(array_filter(
            $facts['items'] ?? [],
            fn ($fact) => is_array($fact) && ($fact['enabled'] ?? false) === true
        ));

        return $facts;
    }
}
