<?php

namespace App\Support\CompanyProfile\Deck;

/**
 * One slide, built from the handful of things the deck is made of: text, a hairline, the Praxis rule, the
 * bar stack, a picture and an Ink ground.
 *
 * It writes PresentationML directly. PhpPresentation cannot author entrance animations or a Morph
 * transition at all, which is the whole point of the deck, so the slide parts are written here and the
 * package's fixed parts — the theme, the master, the one layout, the presentation itself — are template
 * files in resources/company-profile/deck/parts. docs/company-profile/DECK.md says how to change either.
 *
 * Two rules hold the whole thing together:
 *
 * - **Shape ids are the slide's API.** Animation targets a shape by id and Morph matches shapes between
 *   two slides by id, so a shape that is meant to morph keeps the same id on both slides. `reserve()` is
 *   how a blueprint says so.
 * - **A box is given the height its text measures.** PowerPoint is given `<a:noAutofit/>`, so it never
 *   shrinks or grows anything: a slide that does not fit runs off the bottom instead, which is why
 *   Deck\Fit checks every one of them in both languages before the file is written.
 */
final class Slide
{
    /** Shape 1 is the group the tree hangs from, so the first real shape is 2. */
    private int $nextId = 2;

    private string $shapes = '';

    private ?string $background = null;

    private string $transition = '';

    /** @var list<array{id: int, type: string, dur: int, gap: int}> */
    private array $effects = [];

    /** @var list<array{id: int, bg: bool}> */
    private array $builds = [];

    /** @var array<string, string> relationship id => the file on disk it carries */
    private array $media = [];

    /** @var list<array{name: string, x: float, y: float, width: float, height: float, chrome: bool}> */
    private array $boxes = [];

    public function __construct(
        public readonly string $name,
        private readonly string $lang = 'en-GB',
    ) {}

    /* ------------------------------------------------------------------ ids */

    /** The next id a shape will take, without taking it: how a blueprint keeps an id stable for Morph. */
    public function peekId(): int
    {
        return $this->nextId;
    }

    /** Skip ahead, so two slides that morph into each other can put a shared shape on the same id. */
    public function reserve(int $id): void
    {
        $this->nextId = max($this->nextId, $id);
    }

    /* ----------------------------------------------------------- the ground */

    /** The one dark slide in a section: Ink, never black. */
    public function ink(): void
    {
        $this->background = Style::INK;
    }

    public function ground(string $colour): void
    {
        $this->background = $colour;
    }

    /* ------------------------------------------------------------- the parts */

    /**
     * A block of text. Each paragraph is ['text' => …] or ['runs' => [['text' => …, 'cut' => …], …]] with
     * an optional role, colour, alignment and space before it. The box is given the height the text
     * measures, so a block can be stacked under the one above it.
     *
     * @param  list<array<string, mixed>>  $paragraphs
     * @param  array{anchor?: string, height?: float, name?: string, chrome?: bool}  $options
     */
    public function text(float $x, float $y, float $width, array $paragraphs, array $options = []): int
    {
        $id = $this->nextId++;
        $name = $options['name'] ?? 'text';
        $height = $options['height'] ?? self::heightOf($paragraphs, $width);
        $anchor = $options['anchor'] ?? 't';

        $body = '';
        foreach ($paragraphs as $paragraph) {
            $body .= $this->paragraph($paragraph);
        }

        $this->shapes .= '<p:sp><p:nvSpPr><p:cNvPr id="'.$id.'" name="'.Ooxml::esc($name).'"/>'
            .'<p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr>'
            .'<p:spPr>'.$this->xfrm($x, $y, $width, $height).'<a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:noFill/></p:spPr>'
            .'<p:txBody><a:bodyPr wrap="square" lIns="0" tIns="0" rIns="0" bIns="0" rtlCol="0" anchor="'.$anchor.'"><a:noAutofit/></a:bodyPr>'
            .'<a:lstStyle/>'.$body.'</p:txBody></p:sp>';

        $this->builds[] = ['id' => $id, 'bg' => false];
        $this->box($name, $x, $y, $width, $height, (bool) ($options['chrome'] ?? false));

        return $id;
    }

    /** A filled rectangle: the hairline, a bar, a panel. */
    public function rect(float $x, float $y, float $width, float $height, string $colour, string $name = 'rect', ?string $geometry = null): int
    {
        $id = $this->nextId++;
        $prst = $geometry ?? '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom>';

        $this->shapes .= '<p:sp><p:nvSpPr><p:cNvPr id="'.$id.'" name="'.Ooxml::esc($name).'"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>'
            .'<p:spPr>'.$this->xfrm($x, $y, $width, $height).$prst
            .'<a:solidFill><a:srgbClr val="'.$colour.'"/></a:solidFill><a:ln><a:noFill/></a:ln></p:spPr>'
            .'<p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:endParaRPr lang="'.$this->lang.'"/></a:p></p:txBody></p:sp>';

        $this->builds[] = ['id' => $id, 'bg' => true];
        $this->box($name, $x, $y, $width, $height);

        return $id;
    }

    /** The hairline that separates in place of a box: 0.75pt, and never the edge of anything. */
    public function hairline(float $x, float $y, float $width, bool $onInk = false): int
    {
        return $this->rect($x, $y, $width, 0.75, $onInk ? Style::ON_INK_RULE : Style::HAIRLINE, 'hairline');
    }

    /**
     * The Praxis rule: one 2pt rule, Ink to the left of its split and Teal to the right of it, with the
     * split on the column where the answer starts (docs/BRAND.md section 9). Two shapes, so the rule can
     * draw in two strokes — the dark side, then the teal — which is the brand's own moment.
     *
     * @return array{0: int, 1: int} the ink half and the teal half
     */
    public function rule(float $x, float $y, float $width, float $split, bool $onInk = false): array
    {
        $split = max(2.0, min($width - 2.0, $split));

        return [
            $this->rect($x, $y, $split, 2.0, $onInk ? Style::ON_INK : Style::INK, 'rule-ink'),
            $this->rect($x + $split, $y, $width - $split, 2.0, $onInk ? Style::BRIGHT_TEAL : Style::TEAL, 'rule-teal'),
        ];
    }

    /**
     * The bar stack: three bars in the logo's proportions with their ends cut on the diagonal, dark left
     * of the split and Teal right of it. Six shapes, which is what lets it build bar by bar.
     *
     * @return list<int> the halves in the order they should draw
     */
    public function barStack(float $x, float $y, float $width, bool $onInk = false): array
    {
        $bar = 9.0;
        $gap = 9.5;
        $split = $width * 0.46;
        $dark = $onInk ? Style::ON_INK : Style::INK;
        $light = $onInk ? Style::BRIGHT_TEAL : Style::TEAL;

        // A parallelogram with a shallow adjust is the logo's diagonal cut; the halves meet on the split
        $cut = '<a:prstGeom prst="parallelogram"><a:avLst><a:gd name="adj" fmla="val 18000"/></a:avLst></a:prstGeom>';

        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $top = $y + ($i * ($bar + $gap));
            $ids[] = $this->rect($x, $top, $split, $bar, $dark, 'bar-'.($i + 1).'-ink', $cut);
            $ids[] = $this->rect($x + $split, $top, $width - $split, $bar, $light, 'bar-'.($i + 1).'-teal', $cut);
        }

        return $ids;
    }

    /** A picture, at the size it is given. The caller works the aspect out from Images::size(). */
    public function picture(string $path, float $x, float $y, float $width, float $height, string $name = 'image'): ?int
    {
        if (Images::extension($path) === null) {
            return null;    // not a PNG or a JPEG: left out rather than placed as something it is not
        }

        $id = $this->nextId++;
        $rel = 'rId'.(count($this->media) + 2);     // rId1 is the layout
        $this->media[$rel] = $path;

        $this->shapes .= '<p:pic><p:nvPicPr><p:cNvPr id="'.$id.'" name="'.Ooxml::esc($name).'"/>'
            .'<p:cNvPicPr><a:picLocks noChangeAspect="1"/></p:cNvPicPr><p:nvPr/></p:nvPicPr>'
            .'<p:blipFill><a:blip r:embed="'.$rel.'"/><a:stretch><a:fillRect/></a:stretch></p:blipFill>'
            .'<p:spPr>'.$this->xfrm($x, $y, $width, $height).'<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr></p:pic>';

        $this->builds[] = ['id' => $id, 'bg' => false];
        $this->box($name, $x, $y, $width, $height);

        return $id;
    }

    private function box(string $name, float $x, float $y, float $width, float $height, bool $chrome = false): void
    {
        $this->boxes[] = ['name' => $name, 'x' => $x, 'y' => $y, 'width' => $width, 'height' => $height, 'chrome' => $chrome];
    }

    /**
     * Every box this slide draws. Deck\Fit reads them to say whether the slide fits, which is the only
     * check there is: PowerPoint is given `<a:noAutofit/>` and will not rescue anything.
     *
     * @return list<array{name: string, x: float, y: float, width: float, height: float, chrome: bool}>
     */
    public function boxes(): array
    {
        return $this->boxes;
    }

    /* ------------------------------------------------------------- the motion */

    /** @param int|list<int>|null $id */
    public function animate(int|array|null $id, string $type = Animate::FADE, int $duration = 500, int $gap = 0): void
    {
        foreach (is_array($id) ? $id : [$id] as $one) {
            if ($one === null) {
                continue;
            }
            $this->effects[] = ['id' => $one, 'type' => $type, 'dur' => $duration, 'gap' => $gap];
            $gap = 0;      // a gap given to a list applies once, before the first of them
        }
    }

    public function transition(string $kind, int $ms = 700): void
    {
        $this->transition = Animate::transition($kind, $ms);
    }

    /* ------------------------------------------------------------- the output */

    public function xml(): string
    {
        $animated = array_column($this->effects, 'id');
        $builds = array_values(array_filter($this->builds, fn ($b) => in_array($b['id'], $animated, true)));

        $background = $this->background === null
            ? ''
            : '<p:bg><p:bgPr><a:solidFill><a:srgbClr val="'.$this->background.'"/></a:solidFill><a:effectLst/></p:bgPr></p:bg>';

        return Ooxml::declaration()
            .'<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            .' xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
            .'<p:cSld>'.$background.'<p:spTree>'
            .'<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>'
            .'<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>'
            .$this->shapes
            .'</p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr>'
            .$this->transition
            .Animate::timing($this->effects, $builds)
            .'</p:sld>';
    }

    public function rels(): string
    {
        $xml = Ooxml::declaration()
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>';

        foreach ($this->media as $rel => $path) {
            $xml .= '<Relationship Id="'.$rel.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"'
                .' Target="../media/'.$this->mediaName($rel, $path).'"/>';
        }

        return $xml.'</Relationships>';
    }

    /**
     * The image parts this slide needs: the name inside the package => the file on disk.
     *
     * @return array<string, string>
     */
    public function mediaParts(int $slide): array
    {
        $out = [];
        foreach ($this->media as $rel => $path) {
            $out['ppt/media/'.$this->mediaName($rel, $path, $slide)] = $path;
        }

        return $out;
    }

    /** @return list<array{id: int, type: string, dur: int, gap: int}> */
    public function effects(): array
    {
        return $this->effects;
    }

    private ?int $slideNumber = null;

    public function number(int $slide): void
    {
        $this->slideNumber = $slide;
    }

    private function mediaName(string $rel, string $path, ?int $slide = null): string
    {
        $slide ??= $this->slideNumber ?? 0;

        return 'slide'.$slide.'-'.substr($rel, 3).'.'.Images::extension($path);
    }

    /* ------------------------------------------------------------ measurement */

    /**
     * How tall a block of paragraphs is in a box this wide. The blueprint stacks on this, and Deck\Fit
     * checks the total against the slide in both languages.
     *
     * @param  list<array<string, mixed>>  $paragraphs
     */
    public static function heightOf(array $paragraphs, float $width): float
    {
        $metrics = Metrics::instance();
        $height = 0.0;

        foreach ($paragraphs as $paragraph) {
            $type = Style::type((string) ($paragraph['role'] ?? 'body'));
            $text = self::plain($paragraph);
            $cut = (string) ($paragraph['cut'] ?? $type['cut']);
            $lines = $text === '' ? 1 : $metrics->lines($text, $width, $type['face'], $type['size'], $cut);
            $height += (float) ($paragraph['before'] ?? 0) + ($lines * $type['leading']);
        }

        return $height;
    }

    /** @param array<string, mixed> $paragraph */
    private static function plain(array $paragraph): string
    {
        if (isset($paragraph['runs'])) {
            return implode('', array_map(fn ($run) => (string) ($run['text'] ?? ''), (array) $paragraph['runs']));
        }

        return trim((string) ($paragraph['text'] ?? ''));
    }

    /** @param array<string, mixed> $paragraph */
    private function paragraph(array $paragraph): string
    {
        $type = Style::type((string) ($paragraph['role'] ?? 'body'));
        $colour = (string) ($paragraph['colour'] ?? Style::SLATE);
        $align = (string) ($paragraph['align'] ?? 'l');
        $before = (float) ($paragraph['before'] ?? 0);

        $runs = $paragraph['runs'] ?? [['text' => (string) ($paragraph['text'] ?? '')]];
        $xml = '';
        foreach ((array) $runs as $run) {
            $text = Ooxml::clean((string) ($run['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $xml .= '<a:r>'.$this->runProperties($type, (string) ($run['cut'] ?? $type['cut']), (string) ($run['colour'] ?? $colour))
                .'<a:t>'.Ooxml::esc($text).'</a:t></a:r>';
        }

        if ($xml === '') {
            $xml = '<a:endParaRPr lang="'.$this->lang.'" sz="'.Ooxml::hundredths($type['size']).'"/>';
        }

        return '<a:p><a:pPr algn="'.$align.'">'
            .'<a:lnSpc><a:spcPts val="'.Ooxml::hundredths($type['leading']).'"/></a:lnSpc>'
            .'<a:spcBef><a:spcPts val="'.Ooxml::hundredths($before).'"/></a:spcBef>'
            .'</a:pPr>'.$xml.'</a:p>';
    }

    /** @param array{font: string, size: float, track: int} $type */
    private function runProperties(array $type, string $cut, string $colour): string
    {
        $font = $type['font'];

        return '<a:rPr lang="'.$this->lang.'" sz="'.Ooxml::hundredths($type['size']).'"'
            .' b="'.($cut === 'bold' ? 1 : 0).'" i="'.($cut === 'italic' ? 1 : 0).'"'
            .($type['track'] !== 0 ? ' spc="'.$type['track'].'"' : '')
            .' dirty="0">'
            .'<a:solidFill><a:srgbClr val="'.$colour.'"/></a:solidFill>'
            .'<a:latin typeface="'.$font.'"/><a:ea typeface="'.$font.'"/><a:cs typeface="'.$font.'"/>'
            .'</a:rPr>';
    }

    private function xfrm(float $x, float $y, float $width, float $height): string
    {
        return '<a:xfrm><a:off x="'.Ooxml::emu($x).'" y="'.Ooxml::emu($y).'"/>'
            .'<a:ext cx="'.Ooxml::emu(max($width, 1)).'" cy="'.Ooxml::emu(max($height, 1)).'"/></a:xfrm>';
    }
}
