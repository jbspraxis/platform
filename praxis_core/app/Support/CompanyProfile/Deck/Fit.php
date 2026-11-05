<?php

namespace App\Support\CompanyProfile\Deck;

/**
 * Whether every slide fits, in the language it is being built in.
 *
 * The book reflows when a page overflows (docs/company-profile/DESIGN.md, "Fitting"). A deck cannot: the
 * owner asked for twenty-four slides and a template designed once, so there is no page to give an item
 * to. The design has to be right instead, and the one thing that would break it is French, which runs 15
 * to 20 per cent longer than English and would quietly push the last block of a slide off the bottom.
 *
 * So the deck is measured rather than trusted, and a slide that does not fit is an error the tests catch
 * before anyone opens the file — never a type size quietly dropped below Style::FLOOR. The fix for a
 * failure here is the blueprint, not the scale: fewer items on the slide, or a shorter block.
 */
final class Fit
{
    /** A box may sit this far past the live area before it counts as overflowing. */
    private const TOLERANCE = 1.0;

    /**
     * @param  list<Slide>  $slides
     * @return list<array{slide: int, name: string, over: float, edge: string}>
     */
    public static function problems(array $slides): array
    {
        $problems = [];
        $bottom = Style::floorY();
        $right = Style::WIDTH - Style::MARGIN_X;

        foreach ($slides as $i => $slide) {
            foreach ($slide->boxes() as $box) {
                // The running reference sits in the foot margin on purpose, the way it does in the book:
                // it has the slide's own edge to keep to, not the live area's
                $limit = ($box['chrome'] ?? false) ? Style::HEIGHT - 8.0 : $bottom;
                $overBottom = ($box['y'] + $box['height']) - $limit;
                if ($overBottom > self::TOLERANCE) {
                    $problems[] = ['slide' => $i + 1, 'name' => $box['name'], 'over' => round($overBottom, 1), 'edge' => 'bottom'];
                }

                $overRight = ($box['x'] + $box['width']) - $right;
                if ($overRight > self::TOLERANCE) {
                    $problems[] = ['slide' => $i + 1, 'name' => $box['name'], 'over' => round($overRight, 1), 'edge' => 'right'];
                }
            }
        }

        return $problems;
    }

    /** @param list<array{slide: int, name: string, over: float, edge: string}> $problems */
    public static function describe(array $problems): string
    {
        return implode(', ', array_map(
            fn ($p) => 'slide '.$p['slide'].' "'.$p['name'].'" '.$p['over'].'pt past the '.$p['edge'],
            $problems,
        ));
    }
}
