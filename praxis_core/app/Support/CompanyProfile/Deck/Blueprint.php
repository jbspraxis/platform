<?php

namespace App\Support\CompanyProfile\Deck;

use App\Support\CompanyProfile\Assets;
use App\Support\CompanyProfile\PdfRenderer;

/**
 * The twenty-four slides: what each one reads from the contract, where it puts it and how it moves.
 *
 * This is the deck's design. docs/company-profile/DECK.md is the same list in prose, slide by slide, and
 * the two are meant to be read together; change one and change the other.
 *
 * The shape of it, which is the book's shape at a different pace: a cover, a point of view, what we build,
 * the problems, the practices and the method, the teams, the work, the clients, the ground we stand on,
 * and a way to start. Two devices carry it. **The Praxis rule is the continuity**: a section opener draws
 * it under its statement, and every slide of that section carries the same rule, moved to the top of the
 * page — so the Morph transition slides it up and the section's one graphic element holds the family
 * together. **The build is the argument**: on a problem slide the problems arrive before their answers,
 * on the method the stages arrive in order, and on the cover the bar stack draws bar by bar.
 *
 * The rules in company-profile/CONTRACT.md are kept here, not downstream: a credential that is not held
 * and a fact that is not enabled are dropped before anything is measured, so they cannot reach a slide.
 */
final class Blueprint
{
    public const SLIDES = 24;

    /** The highest a section's rule is ever drawn, and the gap between it and the content it heads. */
    private const RULE_TOP = 84.0;

    private const RULE_GAP = 36.0;

    private const CONTENT_Y = self::RULE_TOP + self::RULE_GAP;

    /**
     * Draw a section's rule above a block of this height, with the pair set in the live area rather than
     * clinging to the top of it, and answer where the content starts.
     *
     * The rule is shapes 2 and 3 on every slide of a section, so it has to be drawn before anything else;
     * that is also why every slide works its heights out before it draws a thing.
     */
    private function section(Slide $slide, float $blockHeight, float $split, bool $onInk = false): float
    {
        $top = max(self::RULE_TOP, self::centred($blockHeight + self::RULE_GAP, self::RULE_TOP));
        $slide->rule(Style::MARGIN_X, $top, Style::CONTENT_WIDTH, $split, $onInk);

        return $top + self::RULE_GAP;
    }

    /**
     * Where a block of this height starts, so that it sits in the live area rather than clinging to the
     * top of it. A 16:9 slide is shallow and wide: a column measured from the top leaves a third of the
     * slide empty under it, which reads as a slide that was not finished rather than as air.
     */
    private static function centred(float $height, float $top = self::CONTENT_Y): float
    {
        // A quarter of what is left over, not half: a slide with little on it should sit high and have
        // air under it, the way the book's pages do. Half puts a two-line pillar in the middle of nowhere.
        return $top + max(0.0, (Style::floorY() - $top - $height) * 0.25);
    }

    private array $words;

    public function __construct(
        private readonly array $data,
        private readonly string $locale = 'en',
    ) {
        $this->words = PdfRenderer::words($locale);
    }

    /** @return list<Slide> the deck, in order */
    public function slides(): array
    {
        $slides = [
            $this->cover(),
            $this->povStatement(),
            $this->povArgument(),
            $this->systemStatement(),
            $this->pillars(0, 2, Animate::CROSSFADE),
            $this->pillars(2, 4, Animate::MORPH),
            $this->problemsOpener(),
            $this->problemGroups(0, 2, Animate::CROSSFADE),
            $this->problemGroups(2, 4, Animate::MORPH),
            $this->problemGroups(4, 6, Animate::MORPH),
            $this->problemsClosing(),
            $this->practicesOpener(),
            $this->practices(0, 2, Animate::CROSSFADE),
            $this->practices(2, 4, Animate::MORPH),
            $this->methodStages(),
            $this->methodPrinciples(),
            $this->teamsOpener(),
            $this->teams(0, 2, Animate::CROSSFADE),
            $this->teams(2, 4, Animate::MORPH),
            $this->work(0, 3, true),
            $this->work(3, 6, false),
            $this->clients(),
            $this->ground(),
            $this->back(),
        ];

        foreach ($slides as $i => $slide) {
            $slide->number($i + 1);
        }

        return $slides;
    }

    /* ================================================================= the slides */

    /** 1. The cover: the lockup, the line, the bar stack, the descriptor, and a great deal of air. */
    private function cover(): Slide
    {
        $slide = $this->slide('cover');
        $cover = $this->data['cover'] ?? [];

        $lockup = $this->image($slide, Assets::brand('horizontal-colour.jpg'), Style::MARGIN_X, Style::MARGIN_TOP, 146.0);

        $headline = $slide->text(Style::MARGIN_X, 196.0, Style::col(9), [
            ['text' => (string) ($cover['headline'] ?? ''), 'role' => 'cover', 'colour' => Style::INK],
        ], ['name' => 'headline']);

        $bars = $slide->barStack(Style::MARGIN_X, 330.0, 272.0);

        $descriptor = $slide->text(Style::MARGIN_X, 412.0, Style::col(6), [
            ['text' => (string) ($cover['descriptor'] ?? ''), 'role' => 'lead', 'colour' => Style::INK],
        ], ['name' => 'descriptor']);

        $this->stamp($slide);

        // The one orchestrated moment: the mark, the line, the device drawing bar by bar, then the promise
        $slide->animate($lockup, Animate::FADE, 400);
        $slide->animate($headline, Animate::RISE, 600, 120);
        $slide->animate($bars, Animate::WIPE, 170, 160);
        $slide->animate($descriptor, Animate::FADE, 500, 160);

        return $slide;
    }

    /** 2. The point of view, as one statement and nothing else. */
    private function povStatement(): Slide
    {
        $pov = $this->data['pov'] ?? [];

        return $this->opener('pov', (string) ($pov['title'] ?? ''), null, Animate::CROSSFADE, 4);
    }

    /** 3. The argument, with the statement morphing up into a heading beside it. */
    private function povArgument(): Slide
    {
        $slide = $this->slide('pov-argument');
        $pov = $this->data['pov'] ?? [];

        $statement = [['text' => (string) ($pov['title'] ?? ''), 'role' => 'title', 'colour' => Style::INK]];
        $argument = [];
        foreach (array_slice((array) ($pov['paragraphs'] ?? []), 0, 3) as $i => $paragraph) {
            $argument[] = ['text' => (string) $paragraph, 'role' => 'body', 'colour' => Style::SLATE, 'before' => $i === 0 ? 0 : 15];
        }

        $top = $this->section($slide, max(
            Slide::heightOf($statement, Style::col(4)),
            Slide::heightOf($argument, Style::col(7)),
        ), Style::col(4));

        $slide->text(Style::MARGIN_X, $top, Style::col(4), $statement, ['name' => 'statement']);

        // One box, not three: PowerPoint stacks the paragraphs itself, so the gaps between them are the
        // gaps the design asked for rather than the gaps a measurement rounded up to
        $argumentId = $slide->text(Style::x(6), $top, Style::col(7), $argument, ['name' => 'argument']);
        $slide->animate($argumentId, Animate::RISE, 500, 120);

        $this->chrome($slide, 3);
        $slide->transition(Animate::MORPH);

        return $slide;
    }

    /** 4. What we build, as a statement with the lead under it. */
    private function systemStatement(): Slide
    {
        $system = $this->data['system'] ?? [];

        return $this->opener('system', (string) ($system['title'] ?? ''), (string) ($system['lead'] ?? ''), Animate::CROSSFADE, 5);
    }

    /** 5–6. The pillars, two to a slide, under their own hairlines. Not cards. */
    private function pillars(int $from, int $to, string $transition): Slide
    {
        $slide = $this->slide('pillars');

        $pillars = array_values(array_slice((array) ($this->data['system']['pillars'] ?? []), $from, $to - $from));
        $width = Style::even(2);
        $gap = 0;

        $blocks = array_map(fn ($pillar) => [
            ['text' => (string) ($pillar['title'] ?? ''), 'role' => 'subhead', 'colour' => Style::INK],
            ['text' => (string) ($pillar['body'] ?? ''), 'role' => 'body', 'colour' => Style::SLATE, 'before' => 10],
        ], $pillars);
        $top = $this->section($slide, 18.0 + self::tallest($blocks, $width), Style::col(5));

        foreach ($blocks as $i => $block) {
            $x = Style::evenX($i, 2);
            $hair = $slide->hairline($x, $top, $width);
            $id = $slide->text($x, $top + 18.0, $width, $block, ['name' => 'pillar']);

            $slide->animate($hair, Animate::WIPE, 320, $gap);
            $slide->animate($id, Animate::RISE, 450, 60);
            $gap = 160;
        }

        $this->chrome($slide, 0);
        $slide->transition($transition);

        return $slide;
    }

    /** 7. The hard problems: the statement and the lead. An open set — the domains are listed, never counted. */
    private function problemsOpener(): Slide
    {
        $problems = $this->data['problems'] ?? [];

        return $this->opener('problems', (string) ($problems['title'] ?? ''), (string) ($problems['lead'] ?? ''), Animate::CROSSFADE, Style::SPLIT_COLUMNS);
    }

    /**
     * 8–10. Two domains to a slide: the problem on the left, the answer on the right, under the one rule
     * that turns teal exactly where the answers begin. The problems arrive before their answers, which is
     * the slide's argument said as motion.
     */
    private function problemGroups(int $from, int $to, string $transition): Slide
    {
        $slide = $this->slide('problems');
        $split = Style::col(Style::SPLIT_COLUMNS);

        $left = Style::col(Style::SPLIT_COLUMNS - 1);
        $right = Style::col(Style::COLUMNS - Style::SPLIT_COLUMNS);
        $rightX = Style::MARGIN_X + $split + Style::GUTTER;

        $groups = array_slice((array) ($this->data['problems']['groups'] ?? []), $from, $to - $from);
        $y = $this->section($slide, $this->problemsHeight($groups, $left, $right), $split);
        $gap = 0;

        foreach ($groups as $group) {
            $domain = [['text' => (string) ($group['domain'] ?? ''), 'role' => 'domain', 'colour' => Style::DEEP_TEAL]];
            $id = $slide->text(Style::MARGIN_X, $y, $left, $domain, ['name' => 'domain']);
            $slide->animate($id, Animate::FADE, 320, $gap);
            $y += Slide::heightOf($domain, $left) + 8.0;
            $gap = 120;

            foreach ((array) ($group['items'] ?? []) as $item) {
                $problem = [['text' => (string) ($item['problem'] ?? ''), 'role' => 'small', 'colour' => Style::INK]];
                $answer = [['text' => (string) ($item['answer'] ?? ''), 'role' => 'small', 'colour' => Style::SLATE]];

                $problemId = $slide->text(Style::MARGIN_X, $y, $left, $problem, ['name' => 'problem']);
                $answerId = $slide->text($rightX, $y, $right, $answer, ['name' => 'answer']);

                $slide->animate($problemId, Animate::FADE, 260, 60);
                $slide->animate($answerId, Animate::RISE, 340, 90);

                $y += max(Slide::heightOf($problem, $left), Slide::heightOf($answer, $right)) + 11.0;
            }

            $y += 7.0;
        }

        $this->chrome($slide, 0);
        $slide->transition($transition);

        return $slide;
    }

    /** 11. The closing line of the open set, on the one Ink slide of the section. */
    private function problemsClosing(): Slide
    {
        $slide = $this->slide('problems-closing');
        $slide->ink();

        $text = (string) ($this->data['problems']['closing'] ?? '');
        $block = [['text' => $text, 'role' => 'statement', 'colour' => Style::ON_INK]];
        $width = Style::col(9);
        $height = Slide::heightOf($block, $width);
        $top = (Style::HEIGHT - $height - 60.0) / 2;

        $id = $slide->text(Style::MARGIN_X, $top, $width, $block, ['name' => 'closing']);
        $rule = $slide->rule(Style::MARGIN_X, $top + $height + 28.0, Style::col(6), Style::col(3), true);

        $this->chrome($slide, 0, true);

        $slide->animate($id, Animate::RISE, 700);
        $slide->animate($rule, Animate::WIPE, 450, 120);
        $slide->transition(Animate::CROSSFADE, 800);

        return $slide;
    }

    /** 12. Four practices, one system. */
    private function practicesOpener(): Slide
    {
        $practices = $this->data['practices'] ?? [];

        return $this->opener('practices', (string) ($practices['title'] ?? ''), (string) ($practices['lead'] ?? ''), Animate::CROSSFADE, 6);
    }

    /** 13–14. Two practices to a slide: what it is, and what it covers. */
    private function practices(int $from, int $to, string $transition): Slide
    {
        $slide = $this->slide('practices');

        $items = array_values(array_slice((array) ($this->data['practices']['items'] ?? []), $from, $to - $from));
        $width = Style::even(2);
        $gap = 0;

        $blocks = [];
        $covers = [];
        foreach ($items as $practice) {
            $blocks[] = [
                ['text' => (string) ($practice['name'] ?? ''), 'role' => 'subhead', 'colour' => Style::INK],
                ['text' => (string) ($practice['summary'] ?? ''), 'role' => 'body', 'colour' => Style::SLATE, 'before' => 10],
            ];
            $cover = [['text' => $this->words['capabilities'], 'role' => 'caption', 'colour' => Style::MUTED]];
            foreach ((array) ($practice['capabilities'] ?? []) as $capability) {
                $cover[] = ['text' => (string) $capability, 'role' => 'small', 'colour' => Style::SLATE, 'before' => 5];
            }
            $covers[] = $cover;
        }

        $tallest = 0.0;
        foreach ($blocks as $i => $block) {
            $tallest = max($tallest, Slide::heightOf($block, $width) + 18.0 + Slide::heightOf($covers[$i], $width));
        }
        $top = $this->section($slide, 18.0 + $tallest, Style::col(6));

        foreach ($blocks as $i => $block) {
            $x = Style::evenX($i, 2);
            $hair = $slide->hairline($x, $top, $width);
            $id = $slide->text($x, $top + 18.0, $width, $block, ['name' => 'practice']);

            $y = $top + 18.0 + Slide::heightOf($block, $width) + 18.0;
            $coverId = $slide->text($x, $y, $width, $covers[$i], ['name' => 'capabilities']);

            $slide->animate($hair, Animate::WIPE, 320, $gap);
            $slide->animate($id, Animate::RISE, 450, 60);
            $slide->animate($coverId, Animate::FADE, 400, 90);
            $gap = 150;
        }

        $this->chrome($slide, 0);
        $slide->transition($transition);

        return $slide;
    }

    /**
     * 15. The Praxis Method: the four stages in order, with the rule's split between Design and Build —
     * the point where the thinking becomes the thing, which is what praxis means.
     */
    private function methodStages(): Slide
    {
        $slide = $this->slide('method');
        $method = $this->data['method'] ?? [];

        $head = [
            ['text' => (string) ($method['title'] ?? ''), 'role' => 'title', 'colour' => Style::INK],
            ['text' => (string) ($method['lead'] ?? ''), 'role' => 'lead', 'colour' => Style::SLATE, 'before' => 12],
        ];
        $headId = $slide->text(Style::MARGIN_X, Style::MARGIN_TOP, Style::col(8), $head, ['name' => 'method-head']);
        $y = Style::MARGIN_TOP + Slide::heightOf($head, Style::col(8)) + 34.0;

        $rule = $slide->rule(Style::MARGIN_X, $y, Style::CONTENT_WIDTH, Style::CONTENT_WIDTH / 2);
        $y += 30.0;

        $stages = array_values((array) ($method['stages'] ?? []));
        $width = Style::even(max(count($stages), 1));
        $gap = 120;

        $slide->animate($headId, Animate::RISE, 500);
        $slide->animate($rule, Animate::WIPE, 520, 120);

        foreach ($stages as $i => $stage) {
            $block = [
                ['text' => (string) ($stage['name'] ?? ''), 'role' => 'subhead', 'colour' => Style::INK],
                ['text' => (string) ($stage['body'] ?? ''), 'role' => 'small', 'colour' => Style::SLATE, 'before' => 8],
            ];
            $id = $slide->text(Style::evenX($i, count($stages)), $y, $width, $block, ['name' => 'stage']);
            $slide->animate($id, Animate::RISE, 380, $gap);
            $gap = 110;
        }

        $this->chrome($slide, 0);
        $slide->transition(Animate::CROSSFADE);

        return $slide;
    }

    /** 16. The three principles the method rests on. */
    private function methodPrinciples(): Slide
    {
        $slide = $this->slide('principles');

        $principles = array_values((array) ($this->data['method']['principles'] ?? []));
        $count = max(count($principles), 1);
        $width = Style::even($count);
        $gap = 0;

        $blocks = array_map(fn ($principle) => [
            ['text' => (string) ($principle['title'] ?? ''), 'role' => 'subhead', 'colour' => Style::INK],
            ['text' => (string) ($principle['body'] ?? ''), 'role' => 'body', 'colour' => Style::SLATE, 'before' => 10],
        ], $principles);
        $top = $this->section($slide, 18.0 + self::tallest($blocks, $width), Style::CONTENT_WIDTH / 2);

        foreach ($blocks as $i => $block) {
            $x = Style::evenX($i, $count);
            $hair = $slide->hairline($x, $top, $width);
            $id = $slide->text($x, $top + 18.0, $width, $block, ['name' => 'principle']);

            $slide->animate($hair, Animate::WIPE, 300, $gap);
            $slide->animate($id, Animate::RISE, 420, 60);
            $gap = 130;
        }

        $this->chrome($slide, 0);
        $slide->transition(Animate::MORPH);

        return $slide;
    }

    /** 17. The teams: by discipline, never by name. */
    private function teamsOpener(): Slide
    {
        $teams = $this->data['teams'] ?? [];

        return $this->opener('teams', (string) ($teams['title'] ?? ''), (string) ($teams['lead'] ?? ''), Animate::CROSSFADE, 7);
    }

    /**
     * 18–19. Two teams to a slide: what they do, the disciplines they cover, how long they have done it
     * and the credentials they hold. A credential that is not held never reaches this slide.
     */
    private function teams(int $from, int $to, string $transition): Slide
    {
        $slide = $this->slide('teams');

        $items = array_values(array_slice((array) ($this->data['teams']['items'] ?? []), $from, $to - $from));
        $width = Style::even(2);
        $gap = 0;

        $heads = [];
        $details = [];
        foreach ($items as $team) {
            $heads[] = [
                ['text' => (string) ($team['name'] ?? ''), 'role' => 'subhead', 'colour' => Style::INK],
                ['text' => (string) ($team['summary'] ?? ''), 'role' => 'small', 'colour' => Style::SLATE, 'before' => 9],
            ];
            $details[] = $this->teamDetail($team);
        }

        $tallest = 0.0;
        foreach ($heads as $i => $head) {
            $tallest = max($tallest, Slide::heightOf($head, $width) + 16.0 + Slide::heightOf($details[$i], $width));
        }
        $top = $this->section($slide, 18.0 + $tallest, Style::col(7));

        foreach ($items as $i => $team) {
            $x = Style::evenX($i, 2);
            $hair = $slide->hairline($x, $top, $width);

            $block = $heads[$i];
            $id = $slide->text($x, $top + 18.0, $width, $block, ['name' => 'team']);
            $y = $top + 18.0 + Slide::heightOf($block, $width) + 16.0;

            $detail = $details[$i];
            $detailId = $slide->text($x, $y, $width, $detail, ['name' => 'team-detail']);

            $slide->animate($hair, Animate::WIPE, 300, $gap);
            $slide->animate($id, Animate::RISE, 420, 60);
            $slide->animate($detailId, Animate::FADE, 400, 80);
            $gap = 150;
        }

        $this->chrome($slide, 0);
        $slide->transition($transition);

        return $slide;
    }

    /**
     * 20–21. Selected work: each case as an entry — the client, the sector, its own line and what the
     * system changed. A client is named only as the profile names them.
     */
    private function work(int $from, int $to, bool $opener): Slide
    {
        $slide = $this->slide('work');
        $work = $this->data['work'] ?? [];

        $items = array_values(array_slice((array) ($work['items'] ?? []), $from, $to - $from));
        $width = Style::even(3);
        $blocks = array_map(fn ($item) => $this->caseBlock($item), $items);
        $cases = 16.0 + self::tallest($blocks, $width);

        $y = self::CONTENT_Y;
        if ($opener) {
            $head = [
                ['text' => (string) ($work['title'] ?? ''), 'role' => 'title', 'colour' => Style::INK],
                ['text' => (string) ($work['lead'] ?? ''), 'role' => 'lead', 'colour' => Style::SLATE, 'before' => 12],
            ];
            $headId = $slide->text(Style::MARGIN_X, Style::MARGIN_TOP, Style::col(8), $head, ['name' => 'work-head']);
            $ruleY = Style::MARGIN_TOP + Slide::heightOf($head, Style::col(8)) + 30.0;
            $rule = $slide->rule(Style::MARGIN_X, $ruleY, Style::CONTENT_WIDTH, Style::col(8));
            $y = self::centred($cases, $ruleY + 32.0);
            $slide->animate($headId, Animate::RISE, 500);
            $slide->animate($rule, Animate::WIPE, 480, 120);
        } else {
            $y = $this->section($slide, $cases, Style::col(8));
        }

        $gap = 120;

        foreach ($blocks as $i => $block) {
            $x = Style::evenX($i, 3);
            $hair = $slide->hairline($x, $y, $width);
            $id = $slide->text($x, $y + 16.0, $width, $block, ['name' => 'case']);

            $slide->animate($hair, Animate::WIPE, 280, $gap);
            $slide->animate($id, Animate::RISE, 400, 50);
            $gap = 110;
        }

        $this->chrome($slide, 0);
        $slide->transition($opener ? Animate::CROSSFADE : Animate::MORPH);

        return $slide;
    }

    /**
     * 22. The clients. The wall is the HQ logo library's own transparent PNGs; a logo the library has no
     * PNG for is left off rather than placed as something it is not, which is the rule the PDF keeps too.
     * With no library on the machine the wall is the names instead, exactly as the profile writes them.
     */
    private function clients(): Slide
    {
        $slide = $this->slide('clients');
        $clients = $this->data['clients'] ?? [];

        $head = [['text' => (string) ($clients['title'] ?? ''), 'role' => 'title', 'colour' => Style::INK]];
        $headId = $slide->text(Style::MARGIN_X, Style::MARGIN_TOP, Style::col(8), $head, ['name' => 'clients-head']);
        $ruleY = Style::MARGIN_TOP + Slide::heightOf($head, Style::col(8)) + 26.0;
        $rule = $slide->rule(Style::MARGIN_X, $ruleY, Style::CONTENT_WIDTH, Style::col(5));

        $slide->animate($headId, Animate::RISE, 500);
        $slide->animate($rule, Animate::WIPE, 480, 120);

        $logos = $this->wall();
        $columns = count($logos) > 8 ? 5 : 3;
        $cell = Style::even($columns);
        $rowHeight = 92.0;
        $rows = (int) ceil(count($logos) / max($columns, 1));
        $top = self::centred($rows * $rowHeight, $ruleY + 36.0);
        $gap = 150;

        foreach ($logos as $i => $logo) {
            $x = Style::evenX($i % $columns, $columns);
            $y = $top + (intdiv($i, $columns) * $rowHeight);
            $id = null;

            if (isset($logo['path'])) {
                $size = Images::size($logo['path']);
                if ($size !== null && $size['height'] > 0) {
                    $width = min($cell, 48.0 * $size['width'] / $size['height']);
                    $height = $width * $size['height'] / $size['width'];
                    $id = $slide->picture($logo['path'], $x + (($cell - $width) / 2), $y + ((56.0 - $height) / 2), $width, $height, 'logo');
                }
            }

            // No PNG for this one: the name, set the way the wall would have set the mark
            $id ??= $slide->text($x, $y + 8.0, $cell, [
                ['text' => (string) $logo['name'], 'role' => 'subhead', 'colour' => Style::INK],
            ], ['name' => 'client']);

            $slide->animate($id, Animate::FADE, 320, $gap);
            $gap = 70;
        }

        $this->chrome($slide, 0);
        $slide->transition(Animate::CROSSFADE);

        return $slide;
    }

    /**
     * 23. The ground we stand on: security and the company's own facts, on the deck's second Ink slide.
     * A fact whose switch is off — the Cameroon entity, by default — never reaches it.
     */
    private function ground(): Slide
    {
        $slide = $this->slide('ground');
        $slide->ink();

        $security = $this->data['security'] ?? [];
        $facts = $this->data['facts'] ?? [];

        $left = Style::col(7);
        $right = Style::col(4);
        $rightX = Style::x(9);

        $head = [['text' => (string) ($security['title'] ?? ''), 'role' => 'title', 'colour' => Style::ON_INK]];
        $headId = $slide->text(Style::MARGIN_X, Style::MARGIN_TOP, $left, $head, ['name' => 'security-head']);
        $rule = $slide->rule(Style::MARGIN_X, Style::MARGIN_TOP + Slide::heightOf($head, $left) + 22.0, $left, Style::col(3), true);

        $slide->animate($headId, Animate::RISE, 500);
        $slide->animate($rule, Animate::WIPE, 450, 100);

        $y = Style::MARGIN_TOP + Slide::heightOf($head, $left) + 52.0;
        $cell = (($left - Style::GUTTER) / 2);
        $gap = 130;

        foreach (array_values((array) ($security['items'] ?? [])) as $i => $item) {
            $x = Style::MARGIN_X + (($i % 2) * ($cell + Style::GUTTER));
            $block = [
                ['text' => (string) ($item['title'] ?? ''), 'role' => 'label', 'colour' => Style::ON_INK],
                ['text' => (string) ($item['body'] ?? ''), 'role' => 'small', 'colour' => Style::ON_INK_SECONDARY, 'before' => 5],
            ];
            $row = intdiv($i, 2);
            $id = $slide->text($x, $y + ($row * 118.0), $cell, $block, ['name' => 'security-item']);
            $slide->animate($id, Animate::FADE, 360, $gap);
            $gap = 90;
        }

        $factHead = [['text' => (string) ($facts['title'] ?? ''), 'role' => 'label', 'colour' => Style::ON_INK]];
        $factBlock = $factHead;
        foreach (self::enabledFacts($facts) as $fact) {
            $factBlock[] = ['text' => (string) ($fact['label'] ?? ''), 'role' => 'caption', 'colour' => Style::ON_INK_SECONDARY, 'before' => 9];
            $factBlock[] = ['text' => (string) ($fact['value'] ?? ''), 'role' => 'small', 'colour' => Style::ON_INK, 'before' => 1];
        }
        $factId = $slide->text($rightX, Style::MARGIN_TOP, $right, $factBlock, ['name' => 'facts']);
        $slide->animate($factId, Animate::RISE, 450, 120);

        $this->chrome($slide, 0, true);
        $slide->transition(Animate::CROSSFADE, 800);

        return $slide;
    }

    /** 24. The back cover: what to do next, where to find us, and the brand's closing line. */
    private function back(): Slide
    {
        $slide = $this->slide('back');
        $contact = $this->data['contact'] ?? [];
        $meta = $this->data['meta'] ?? [];

        $cta = [['text' => (string) ($contact['cta'] ?? ''), 'role' => 'statement', 'colour' => Style::INK]];
        $ctaId = $slide->text(Style::MARGIN_X, Style::MARGIN_TOP + 10.0, Style::col(6), $cta, ['name' => 'cta']);
        $y = Style::MARGIN_TOP + 10.0 + Slide::heightOf($cta, Style::col(6)) + 26.0;

        $rule = $slide->rule(Style::MARGIN_X, $y, Style::col(6), Style::col(3));
        $y += 28.0;

        $speak = [['text' => $this->words['speak'], 'role' => 'body', 'colour' => Style::SLATE]];
        $speakId = $slide->text(Style::MARGIN_X, $y, Style::col(6), $speak, ['name' => 'speak']);
        $y += Slide::heightOf($speak, Style::col(6)) + 22.0;

        $lines = [];
        foreach ([
            (string) ($contact['website'] ?? ''),
            (string) ($contact['email'] ?? ''),
            (string) ($contact['sales_email'] ?? ''),
            (string) ($contact['phone'] ?? ''),
            implode('  ·  ', array_map('strval', (array) ($contact['offices'] ?? []))),
        ] as $line) {
            if (trim($line) !== '') {
                $lines[] = ['text' => $line, 'role' => 'small', 'colour' => Style::INK, 'before' => 4];
            }
        }
        $contactId = $lines === [] ? null : $slide->text(Style::MARGIN_X, $y, Style::col(6), $lines, ['name' => 'contact']);

        $logo = $this->image($slide, Assets::brand('stacked-colour.jpg'), Style::x(10), Style::MARGIN_TOP + 16.0, 118.0);

        $hair = $slide->hairline(Style::MARGIN_X, 404.0, Style::CONTENT_WIDTH);
        $closingId = $slide->text(Style::MARGIN_X, 426.0, Style::col(8), [
            ['text' => (string) ($contact['closing'] ?? ''), 'role' => 'title', 'colour' => Style::INK],
        ], ['name' => 'closing']);

        $slide->text(Style::x(9), 430.0, Style::col(4), [
            ['text' => $this->words['published'].' '.(string) ($meta['published_at'] ?? ''), 'role' => 'meta', 'colour' => Style::MUTED, 'align' => 'r'],
            ['text' => (string) ($meta['reference'] ?? ''), 'role' => 'meta', 'colour' => Style::MUTED, 'align' => 'r'],
        ], ['name' => 'stamp']);

        $slide->animate($ctaId, Animate::RISE, 600);
        $slide->animate($rule, Animate::WIPE, 450, 100);
        $slide->animate($speakId, Animate::FADE, 400, 80);
        $slide->animate($contactId, Animate::FADE, 400, 80);
        $slide->animate($logo, Animate::FADE, 400, 60);
        $slide->animate($hair, Animate::WIPE, 420, 120);
        $slide->animate($closingId, Animate::RISE, 520, 60);

        $slide->transition(Animate::CROSSFADE, 800);

        return $slide;
    }

    /* ================================================================== the parts */

    /**
     * A section opener: the statement, the lead under it when the section has one, and the Praxis rule
     * drawn beneath them. The rule is shapes 2 and 3 on every slide of the section, so Morph carries it
     * up to the top of the slides that follow.
     */
    private function opener(string $name, string $statement, ?string $lead, string $transition, int $splitColumns): Slide
    {
        $slide = $this->slide($name.'-opener');

        $block = [['text' => $statement, 'role' => 'statement', 'colour' => Style::INK]];
        $width = Style::col(9);
        $height = Slide::heightOf($block, $width);

        $lead = $lead !== null && trim($lead) !== '' ? $lead : null;
        $leadBlock = $lead === null ? [] : [['text' => $lead, 'role' => 'lead', 'colour' => Style::SLATE]];
        $leadHeight = $leadBlock === [] ? 0.0 : Slide::heightOf($leadBlock, Style::col(7)) + 30.0;

        // The whole opener as one composition, set a little above the optical centre of the slide
        $total = $height + 30.0 + 2.0 + $leadHeight;
        $top = Style::MARGIN_TOP + max(0.0, (Style::floorY() - Style::MARGIN_TOP - $total) * 0.32);

        $ruleY = $top + $height + 30.0;
        $rule = $slide->rule(Style::MARGIN_X, $ruleY, Style::CONTENT_WIDTH, Style::col($splitColumns));

        $statementId = $slide->text(Style::MARGIN_X, $top, $width, $block, ['name' => 'statement']);
        $slide->animate($statementId, Animate::RISE, 650);
        $slide->animate($rule, Animate::WIPE, 520, 140);

        if ($leadBlock !== []) {
            $leadId = $slide->text(Style::MARGIN_X, $ruleY + 30.0, Style::col(7), $leadBlock, ['name' => 'lead']);
            $slide->animate($leadId, Animate::FADE, 450, 120);
        }

        $this->chrome($slide, 0);
        $slide->transition($transition);

        return $slide;
    }

    /**
     * The tallest of a set of blocks set in the same column width.
     *
     * @param  list<list<array<string, mixed>>>  $blocks
     */
    private static function tallest(array $blocks, float $width): float
    {
        $tallest = 0.0;
        foreach ($blocks as $block) {
            $tallest = max($tallest, Slide::heightOf($block, $width));
        }

        return $tallest;
    }

    /** How tall a run of domains and their problem-and-answer pairs is, before it is placed. */
    private function problemsHeight(array $groups, float $left, float $right): float
    {
        $height = 0.0;
        foreach ($groups as $group) {
            $height += Slide::heightOf([['text' => (string) ($group['domain'] ?? ''), 'role' => 'domain']], $left) + 8.0;
            foreach ((array) ($group['items'] ?? []) as $item) {
                $height += max(
                    Slide::heightOf([['text' => (string) ($item['problem'] ?? ''), 'role' => 'small']], $left),
                    Slide::heightOf([['text' => (string) ($item['answer'] ?? ''), 'role' => 'small']], $right),
                ) + 11.0;
            }
            $height += 7.0;
        }

        return $height;
    }

    /** What a team shows under its name: the disciplines, the experience and the credentials it holds. */
    private function teamDetail(array $team): array
    {
        $detail = [
            ['text' => $this->words['disciplines'], 'role' => 'caption', 'colour' => Style::MUTED],
            ['text' => implode(' · ', array_map('strval', (array) ($team['disciplines'] ?? []))), 'role' => 'small', 'colour' => Style::SLATE, 'before' => 4],
            ['text' => $this->words['experience'].' · '.(string) ($team['experience'] ?? ''), 'role' => 'caption', 'colour' => Style::MUTED, 'before' => 12],
        ];

        $held = self::heldCredentials($team);
        if ($held !== []) {
            $detail[] = ['text' => $this->words['credentials'], 'role' => 'caption', 'colour' => Style::MUTED, 'before' => 12];
            foreach ($held as $credential) {
                $detail[] = [
                    'runs' => [
                        ['text' => (string) ($credential['name'] ?? '')],
                        ['text' => '  '.(string) ($credential['issuer'] ?? ''), 'colour' => Style::MUTED],
                    ],
                    'role' => 'small',
                    'colour' => Style::SLATE,
                    'before' => 4,
                ];
            }
        }

        return $detail;
    }

    /** One case as an entry: the client, the sector, its own line and what the system changed. */
    private function caseBlock(array $item): array
    {
        return [
            ['text' => (string) ($item['client'] ?? ''), 'role' => 'subhead', 'colour' => Style::INK, 'before' => 0],
            ['text' => (string) ($item['sector'] ?? ''), 'role' => 'caption', 'colour' => Style::MUTED, 'before' => 3],
            ['text' => (string) ($item['title'] ?? ''), 'role' => 'body', 'colour' => Style::INK, 'before' => 11],
            ['text' => $this->words['outcome'], 'role' => 'caption', 'colour' => Style::MUTED, 'before' => 12],
            ['text' => (string) ($item['outcome'] ?? ''), 'role' => 'small', 'colour' => Style::SLATE, 'before' => 3],
        ];
    }

    private function slide(string $name): Slide
    {
        return new Slide($name, $this->locale === 'fr' ? 'fr-FR' : 'en-GB');
    }

    /** The reference and the slide's number, in the same place on every slide, as the book carries them. */
    private function chrome(Slide $slide, int $number, bool $onInk = false): void
    {
        $colour = $onInk ? Style::ON_INK_SECONDARY : Style::MUTED;
        $reference = (string) ($this->data['meta']['reference'] ?? '');

        $slide->text(Style::MARGIN_X, Style::HEIGHT - 34.0, Style::col(8), [
            ['text' => trim('JBS Praxis  ·  '.$reference, ' ·'), 'role' => 'meta', 'colour' => $colour],
        ], ['name' => 'reference', 'chrome' => true]);

        unset($number);
    }

    /** The cover's and the back cover's stamp: which version this is, and when it was published. */
    private function stamp(Slide $slide): void
    {
        $meta = $this->data['meta'] ?? [];

        $slide->text(Style::x(9), Style::HEIGHT - 48.0, Style::col(4), [
            ['text' => $this->words['published'].' '.(string) ($meta['published_at'] ?? ''), 'role' => 'meta', 'colour' => Style::MUTED, 'align' => 'r'],
            ['text' => (string) ($meta['reference'] ?? ''), 'role' => 'meta', 'colour' => Style::MUTED, 'align' => 'r'],
        ], ['name' => 'stamp', 'chrome' => true]);
    }

    /** A picture placed at a given width, keeping its own shape. */
    private function image(Slide $slide, string $path, float $x, float $y, float $width): ?int
    {
        $size = Images::size($path);
        if ($size === null || $size['width'] === 0) {
            return null;
        }

        return $slide->picture($path, $x, $y, $width, $width * $size['height'] / $size['width'], 'lockup');
    }

    /**
     * What goes on the wall: the library's logos when it has them, and otherwise the clients the profile
     * itself names in its work. Nothing here invents a client or renames one.
     *
     * @return list<array{name: string, path?: string}>
     */
    private function wall(): array
    {
        $wall = [];
        foreach ((array) ($this->data['clients']['logos'] ?? []) as $logo) {
            $name = trim((string) ($logo['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $png = $logo['png'] ?? $logo['path'] ?? null;
            $entry = ['name' => $name];
            if (is_string($png) && Images::extension($png) !== null) {
                $entry['path'] = $png;
            }
            $wall[] = $entry;
        }

        if ($wall !== []) {
            return array_slice($wall, 0, 15);
        }

        foreach ((array) ($this->data['work']['items'] ?? []) as $item) {
            $name = trim((string) ($item['client'] ?? ''));
            if ($name !== '' && ! in_array($name, array_column($wall, 'name'), true)) {
                $wall[] = ['name' => $name];
            }
        }

        return array_slice($wall, 0, 15);
    }

    /**
     * The credentials a team holds. A credential whose `held` is not exactly true never reaches a slide:
     * the editor shows it with its switch, and every renderer drops it again, so a mistake upstream
     * cannot put one in a client's hands.
     *
     * @return list<array<string, mixed>>
     */
    public static function heldCredentials(array $team): array
    {
        return array_values(array_filter(
            (array) ($team['credentials'] ?? []),
            fn ($credential) => ($credential['held'] ?? null) === true,
        ));
    }

    /**
     * The facts whose switch is on. The Cameroon entity is off by default and never prints.
     *
     * @return list<array<string, mixed>>
     */
    public static function enabledFacts(array $facts): array
    {
        return array_values(array_filter(
            (array) ($facts['items'] ?? []),
            fn ($fact) => ($fact['enabled'] ?? null) === true,
        ));
    }
}
