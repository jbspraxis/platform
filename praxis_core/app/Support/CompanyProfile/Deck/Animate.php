<?php

namespace App\Support\CompanyProfile\Deck;

/**
 * The deck's motion: what a slide does when it arrives, and what its parts do once it is there.
 *
 * docs/BRAND.md section 11 is the brief. Things settle, they do not perform: no bounce, no spin, no glow.
 * One orchestrated moment a slide, built from three entrances and nothing else.
 *
 *   fade   the quiet default, for a block that simply needs to be there
 *   rise   a fade with a short lift, for the one thing a slide is about
 *   wipe   left to right, for the Praxis rule, which draws to join a question to its answer
 *
 * The XML is the shape PowerPoint itself writes. It was read out of a file PowerPoint saved rather than
 * written from the specification, because the specification allows several encodings of "after previous"
 * and only one of them is the one PowerPoint round-trips. The click group carries both a
 * `delay="indefinite"` and an `onBegin` condition on the main sequence: that pair is what makes a slide
 * build itself when it appears instead of waiting for a click.
 *
 * Transitions are Morph where two slides share a shape, a fade everywhere else. Morph lives in a namespace
 * PowerPoint 2016 introduced, so it is written inside an mc:AlternateContent with a fade underneath it: a
 * reader that does not know Morph — Google Slides, LibreOffice, PowerPoint 2013 — reads the fade and the
 * deck still moves.
 */
final class Animate
{
    public const FADE = 'fade';

    public const RISE = 'rise';

    public const WIPE = 'wipe';

    /** Morph matches shapes between two slides by their id, so a shared shape has to keep its id. */
    public const MORPH = 'morph';

    public const CROSSFADE = 'crossfade';

    public const NONE = 'none';

    /**
     * @param  list<array{id: int, type: string, dur: int, gap: int}>  $effects  in the order they play
     * @param  list<array{id: int, bg: bool}>  $built  the shapes the build list names
     */
    public static function timing(array $effects, array $built): string
    {
        if ($effects === []) {
            return '';
        }

        $id = 4;                 // 1 is the timing root, 2 the main sequence, 3 the one click group
        $start = 0;
        $nodes = '';

        foreach ($effects as $effect) {
            $outer = $id++;
            $node = $id++;
            $nodes .= '<p:par><p:cTn id="'.$outer.'" fill="hold"><p:stCondLst><p:cond delay="'.$start.'"/></p:stCondLst><p:childTnLst>'
                .'<p:par><p:cTn id="'.$node.'" presetID="'.self::presetId($effect['type']).'" presetClass="entr" presetSubtype="'.self::presetSubtype($effect['type']).'" fill="hold" grpId="0" nodeType="afterEffect">'
                .'<p:stCondLst><p:cond delay="'.$effect['gap'].'"/></p:stCondLst>'
                .'<p:childTnLst>'.self::behaviours($effect, $id).'</p:childTnLst>'
                .'</p:cTn></p:par>'
                .'</p:childTnLst></p:cTn></p:par>';

            $start += $effect['gap'] + $effect['dur'];
        }

        $builds = '';
        foreach ($built as $build) {
            $builds .= '<p:bldP spid="'.$build['id'].'" grpId="0"'.($build['bg'] ? ' animBg="1"' : '').'/>';
        }

        return '<p:timing><p:tnLst><p:par><p:cTn id="1" dur="indefinite" restart="never" nodeType="tmRoot"><p:childTnLst>'
            .'<p:seq concurrent="1" nextAc="seek"><p:cTn id="2" dur="indefinite" nodeType="mainSeq"><p:childTnLst>'
            .'<p:par><p:cTn id="3" fill="hold">'
            .'<p:stCondLst><p:cond delay="indefinite"/><p:cond evt="onBegin" delay="0"><p:tn val="2"/></p:cond></p:stCondLst>'
            .'<p:childTnLst>'.$nodes.'</p:childTnLst>'
            .'</p:cTn></p:par>'
            .'</p:childTnLst></p:cTn>'
            .'<p:prevCondLst><p:cond evt="onPrev" delay="0"><p:tgtEl><p:sldTgt/></p:tgtEl></p:cond></p:prevCondLst>'
            .'<p:nextCondLst><p:cond evt="onNext" delay="0"><p:tgtEl><p:sldTgt/></p:tgtEl></p:cond></p:nextCondLst>'
            .'</p:seq>'
            .'</p:childTnLst></p:cTn></p:par></p:tnLst>'
            .($builds === '' ? '' : '<p:bldLst>'.$builds.'</p:bldLst>')
            .'</p:timing>';
    }

    /** @param array{id: int, type: string, dur: int, gap: int} $effect */
    private static function behaviours(array $effect, int &$id): string
    {
        $target = '<p:tgtEl><p:spTgt spid="'.$effect['id'].'"/></p:tgtEl>';

        // Every entrance begins by making the shape visible: without this it is drawn from the first frame
        $xml = '<p:set><p:cBhvr><p:cTn id="'.($id++).'" dur="1" fill="hold"><p:stCondLst><p:cond delay="0"/></p:stCondLst></p:cTn>'
            .$target.'<p:attrNameLst><p:attrName>style.visibility</p:attrName></p:attrNameLst></p:cBhvr>'
            .'<p:to><p:strVal val="visible"/></p:to></p:set>';

        $filter = $effect['type'] === self::WIPE ? 'wipe(right)' : 'fade';
        $xml .= '<p:animEffect transition="in" filter="'.$filter.'"><p:cBhvr><p:cTn id="'.($id++).'" dur="'.$effect['dur'].'"/>'.$target.'</p:cBhvr></p:animEffect>';

        if ($effect['type'] === self::RISE) {
            // A short lift, a fortieth of the slide: it settles rather than travels
            foreach ([['ppt_x', '#ppt_x'], ['ppt_y', '#ppt_y+.025']] as [$attribute, $from]) {
                $xml .= '<p:anim calcmode="lin" valueType="num"><p:cBhvr><p:cTn id="'.($id++).'" dur="'.$effect['dur'].'" fill="hold"/>'
                    .$target.'<p:attrNameLst><p:attrName>'.$attribute.'</p:attrName></p:attrNameLst></p:cBhvr>'
                    .'<p:tavLst><p:tav tm="0"><p:val><p:strVal val="'.$from.'"/></p:val></p:tav>'
                    .'<p:tav tm="100000"><p:val><p:strVal val="#'.$attribute.'"/></p:val></p:tav></p:tavLst></p:anim>';
            }
        }

        return $xml;
    }

    private static function presetId(string $type): int
    {
        return match ($type) {
            self::RISE => 47,     // Float In
            self::WIPE => 22,     // Wipe
            default => 10,        // Fade
        };
    }

    private static function presetSubtype(string $type): int
    {
        return $type === self::WIPE ? 2 : 0;     // 2 is "From Left", which draws to the right
    }

    /** The transition onto a slide. Morph carries a fade under it for every reader that has no Morph. */
    public static function transition(string $kind, int $ms = 700): string
    {
        if ($kind === self::NONE) {
            return '';
        }

        $speed = $ms >= 700 ? 'slow' : 'med';
        $fade = '<p:transition spd="'.$speed.'" p14:dur="'.$ms.'"><p:fade/></p:transition>';

        $choice = $kind === self::MORPH
            ? '<mc:Choice xmlns:p14="http://schemas.microsoft.com/office/powerpoint/2010/main" xmlns:p159="http://schemas.microsoft.com/office/powerpoint/2015/09/main" Requires="p159">'
                .'<p:transition spd="'.$speed.'" p14:dur="'.$ms.'"><p159:morph option="byObject"/></p:transition></mc:Choice>'
            : '';

        return '<mc:AlternateContent xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006">'
            .$choice
            .'<mc:Choice xmlns:p14="http://schemas.microsoft.com/office/powerpoint/2010/main" Requires="p14">'.$fade.'</mc:Choice>'
            .'<mc:Fallback><p:transition spd="'.$speed.'"><p:fade/></p:transition></mc:Fallback>'
            .'</mc:AlternateContent>';
    }
}
