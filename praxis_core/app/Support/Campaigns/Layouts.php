<?php

namespace App\Support\Campaigns;

/**
 * The branded layouts of a broadcast (owner, 3 Nov 2026: four layouts and a rich-text letter). A layout sets the
 * small label above the headline (emails.broadcast.eyebrow.*), the blocks a new broadcast starts with, and what the
 * AI is asked to write. Every block can be added to every layout afterwards.
 */
final class Layouts
{
    public const ALL = [
        'announcement' => [
            'label' => 'Announcement',
            'hint' => 'News in a few lines: a launch, a new office, a new service.',
            'icon' => 'megaphone',
            'eyebrow' => true,
            'blocks' => ['text', 'button'],
            'ai' => 'An announcement: what is new, in two or three short paragraphs, then why it matters to the reader. One button.',
        ],
        'insight' => [
            'label' => 'Insight',
            'hint' => 'A newsletter: what we see in the market, with a few figures.',
            'icon' => 'lightbulb',
            'eyebrow' => true,
            'blocks' => ['text', 'figures', 'heading', 'text', 'quote', 'button'],
            'ai' => 'An insight newsletter: open with the observation, give two or three key figures (only figures the brief gives), a short heading and the practical takeaway, an optional quote (only if the brief gives one, with its source), then one button.',
        ],
        'event' => [
            'label' => 'Event invite',
            'hint' => 'An invitation: what, when and where, and a way to reply.',
            'icon' => 'calendar-days',
            'eyebrow' => true,
            'blocks' => ['text', 'details', 'button'],
            'ai' => 'An event invitation: what the event is and who it is for, then the details (date, time, place, as the brief gives them), then one button to reply or register.',
        ],
        'case_study' => [
            'label' => 'Case study',
            'hint' => 'Work we did: the problem, what we changed, the results.',
            'icon' => 'briefcase-business',
            'eyebrow' => true,
            'blocks' => ['heading', 'text', 'figures', 'quote', 'button'],
            'ai' => 'A case study: the client’s problem, what JBS Praxis changed, the results as key figures (only figures the brief gives), a client quote if the brief gives one, then one button. Name the client only if the brief does; otherwise describe them ("a leading pharmaceutical manufacturer").',
        ],
        'letter' => [
            'label' => 'Letter',
            'hint' => 'Just write: bold, italics, links, lists and headings.',
            'icon' => 'pen-line',
            'eyebrow' => false,
            'blocks' => ['text'],
            'ai' => 'A short letter in plain paragraphs, with a list or a small heading only where it helps.',
        ],
    ];

    public const DEFAULT = 'announcement';

    public static function has(?string $layout): bool
    {
        return isset(self::ALL[(string) $layout]);
    }

    public static function get(?string $layout): array
    {
        return self::ALL[self::has($layout) ? $layout : self::DEFAULT];
    }

    /** A new version in this layout: empty fields, the layout's starting blocks */
    public static function starter(string $layout, string $locale): array
    {
        return [
            'subject' => '',
            'preheader' => '',
            'headline' => '',
            'blocks' => array_map(fn ($type) => Blocks::blank($type, $locale), self::get($layout)['blocks']),
        ];
    }
}
