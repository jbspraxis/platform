<?php

namespace App\Support\CompanyProfile;

/**
 * What the company profile is made of (docs/COMPANY_PROFILE.md section 4), and the one place that decides,
 * for every field, whether it is words (per locale) or a fact about the firm (shared by both locales).
 *
 * Each section is:
 *   label     what the editor calls it
 *   blurb     one line telling the author what the section is for
 *   fields    section-level words: key => ['label' => ..., 'long' => bool]
 *   shared    section-level values that are the same in both languages (a URL, an address)
 *   lists     ordered lists of items, each:
 *               label, singular   what the editor calls the list and one of its items
 *               string            true when an item is a single line of words and nothing else
 *               text              per-item words: key => ['label' => ..., 'long' => bool]
 *               shared            per-item values and switches: key => ['label' => ..., 'type' => switch|line, 'default' => ...]
 *               lists             lists nested inside each item (credentials inside a team, and so on)
 *
 * The contract the three company-profile PRs share is company-profile/CONTRACT.md and is reproduced by
 * Profile::data(). Change this file and that one together, never one alone.
 */
final class Sections
{
    public const KEYS = ['cover', 'pov', 'system', 'problems', 'practices', 'method', 'teams', 'work', 'clients', 'security', 'facts', 'contact'];

    public const LOCALES = ['en', 'fr'];

    /** @return array<string, array> */
    public static function all(): array
    {
        return [
            'cover' => [
                'label' => 'Cover',
                'blurb' => 'The first page: the line the reader sees first, and the descriptor beneath it.',
                'fields' => [
                    'headline' => ['label' => 'Headline'],
                    'descriptor' => ['label' => 'Descriptor'],
                ],
            ],

            'pov' => [
                'label' => 'Point of view',
                'blurb' => 'What the firm believes, in its own words.',
                'fields' => ['title' => ['label' => 'Title']],
                'lists' => [
                    'paragraphs' => ['label' => 'Paragraphs', 'singular' => 'paragraph', 'string' => true, 'long' => true],
                ],
            ],

            'system' => [
                'label' => 'What we build',
                'blurb' => 'The system the firm builds, and the pillars it stands on.',
                'fields' => ['title' => ['label' => 'Title'], 'lead' => ['label' => 'Lead', 'long' => true]],
                'lists' => [
                    'pillars' => ['label' => 'Pillars', 'singular' => 'pillar', 'text' => [
                        'title' => ['label' => 'Title'],
                        'body' => ['label' => 'Body', 'long' => true],
                    ]],
                ],
            ],

            'problems' => [
                'label' => 'Problems we answer',
                'blurb' => 'An open set, grouped by domain. Never say how many there are.',
                'shape' => ['title', 'lead', 'groups', 'closing'],
                'fields' => [
                    'title' => ['label' => 'Title'],
                    'lead' => ['label' => 'Lead', 'long' => true],
                    'closing' => ['label' => 'Closing line', 'long' => true],
                ],
                'lists' => [
                    'groups' => ['label' => 'Groups', 'singular' => 'group', 'text' => [
                        'domain' => ['label' => 'Domain'],
                    ], 'lists' => [
                        'items' => ['label' => 'Problems', 'singular' => 'problem', 'text' => [
                            'problem' => ['label' => 'The problem', 'long' => true],
                            'answer' => ['label' => 'What we do about it', 'long' => true],
                        ]],
                    ]],
                ],
            ],

            'practices' => [
                'label' => 'Practices',
                'blurb' => 'The four practices and what each one can do.',
                'fields' => ['title' => ['label' => 'Title'], 'lead' => ['label' => 'Lead', 'long' => true]],
                'lists' => [
                    'items' => ['label' => 'Practices', 'singular' => 'practice', 'text' => [
                        'name' => ['label' => 'Name'],
                        'summary' => ['label' => 'Summary', 'long' => true],
                    ], 'lists' => [
                        'capabilities' => ['label' => 'Capabilities', 'singular' => 'capability', 'string' => true],
                    ]],
                ],
            ],

            'method' => [
                'label' => 'How we work',
                'blurb' => 'The stages of an engagement and the principles underneath them.',
                'fields' => ['title' => ['label' => 'Title'], 'lead' => ['label' => 'Lead', 'long' => true]],
                'lists' => [
                    'stages' => ['label' => 'Stages', 'singular' => 'stage', 'text' => [
                        'name' => ['label' => 'Name'],
                        'body' => ['label' => 'Body', 'long' => true],
                    ]],
                    'principles' => ['label' => 'Principles', 'singular' => 'principle', 'text' => [
                        'title' => ['label' => 'Title'],
                        'body' => ['label' => 'Body', 'long' => true],
                    ]],
                ],
            ],

            'teams' => [
                'label' => 'Teams',
                'blurb' => 'By discipline and credentials. People are never named. A credential appears in the profile only while its Held switch is on.',
                'fields' => ['title' => ['label' => 'Title'], 'lead' => ['label' => 'Lead', 'long' => true]],
                'lists' => [
                    'items' => ['label' => 'Teams', 'singular' => 'team', 'shape' => ['name', 'summary', 'disciplines', 'experience', 'credentials'], 'text' => [
                        'name' => ['label' => 'Team'],
                        'summary' => ['label' => 'Summary', 'long' => true],
                        'experience' => ['label' => 'Experience line'],
                    ], 'lists' => [
                        'disciplines' => ['label' => 'Disciplines', 'singular' => 'discipline', 'string' => true],
                        'credentials' => ['label' => 'Credentials', 'singular' => 'credential', 'gate' => 'held', 'shared' => [
                            'name' => ['label' => 'Credential', 'type' => 'line', 'default' => ''],
                            'issuer' => ['label' => 'Issuer', 'type' => 'line', 'default' => ''],
                            'held' => ['label' => 'Held', 'type' => 'switch', 'default' => true],
                        ]],
                    ]],
                ],
            ],

            'work' => [
                'label' => 'Work',
                'blurb' => 'Case stories. Fidson is never named; LPC is an FMCG distributor, Cameroon.',
                'fields' => ['title' => ['label' => 'Title'], 'lead' => ['label' => 'Lead', 'long' => true]],
                'lists' => [
                    'items' => ['label' => 'Case stories', 'singular' => 'case story', 'text' => [
                        'client' => ['label' => 'Client, as we may describe them'],
                        'sector' => ['label' => 'Sector'],
                        'title' => ['label' => 'Title'],
                        'problem' => ['label' => 'The problem', 'long' => true],
                        'built' => ['label' => 'What we built', 'long' => true],
                        'outcome' => ['label' => 'The outcome', 'long' => true],
                    ], 'shared' => [
                        'image' => ['label' => 'Image path', 'type' => 'line', 'default' => null],
                    ]],
                ],
            ],

            'clients' => [
                'label' => 'Clients',
                'blurb' => 'The logo wall. The logos themselves come from the logo library, not from here.',
                'fields' => ['title' => ['label' => 'Title']],
            ],

            'security' => [
                'label' => 'Security and confidentiality',
                'blurb' => 'How client data is held.',
                'fields' => ['title' => ['label' => 'Title']],
                'lists' => [
                    'items' => ['label' => 'Points', 'singular' => 'point', 'text' => [
                        'title' => ['label' => 'Title'],
                        'body' => ['label' => 'Body', 'long' => true],
                    ]],
                ],
            ],

            'facts' => [
                'label' => 'Company facts',
                'blurb' => 'A fact appears in the profile only while its Show switch is on.',
                'fields' => ['title' => ['label' => 'Title']],
                'lists' => [
                    'items' => ['label' => 'Facts', 'singular' => 'fact', 'gate' => 'enabled', 'text' => [
                        'label' => ['label' => 'Label'],
                        'value' => ['label' => 'Value'],
                    ], 'shared' => [
                        'enabled' => ['label' => 'Show', 'type' => 'switch', 'default' => true],
                    ]],
                ],
            ],

            'contact' => [
                'label' => 'Contact',
                'blurb' => 'The last page. Addresses and links are the same in both languages.',
                'shape' => ['cta', 'cta_url', 'email', 'sales_email', 'phone', 'website', 'offices', 'closing'],
                'fields' => [
                    'cta' => ['label' => 'Call to action', 'long' => true],
                    // docs/BRAND.md: the brand's sign-off, on the last page and nowhere else
                    'closing' => ['label' => 'Closing line'],
                ],
                'shared' => [
                    'cta_url' => ['label' => 'Call-to-action link'],
                    'email' => ['label' => 'Email'],
                    'sales_email' => ['label' => 'Sales email'],
                    'phone' => ['label' => 'Phone'],
                    'website' => ['label' => 'Website'],
                ],
                'lists' => [
                    'offices' => ['label' => 'Offices', 'singular' => 'office', 'string' => true],
                ],
            ],
        ];
    }

    public static function get(string $key): array
    {
        $all = self::all();

        if (! isset($all[$key])) {
            throw new \InvalidArgumentException("Unknown company profile section [{$key}].");
        }

        return $all[$key];
    }

    public static function label(string $key): string
    {
        return self::get($key)['label'];
    }

    public static function isLocale(string $locale): bool
    {
        return in_array($locale, self::LOCALES, true);
    }
}
