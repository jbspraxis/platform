# Company profile: shared data contract (lead chat, 5 Nov 2026)

One contract for PR 1 (HQ engine), PR 2 (design and PDF) and PR 3 (logo library and intro letter). If you need to change it, message the lead chat first; never change it silently.

## Where the content comes from

The lead chat writes the full copy as two seed files, in exactly this shape:

- `C:\Users\Tom-Blake\jbspraxis_project\company-profile\content.en.json`
- `C:\Users\Tom-Blake\jbspraxis_project\company-profile\content.fr.json`

PR 1 imports them as the first published version (the seeder reads them; copy them into the repo under `praxis_core/database/seeders/company-profile/`). PR 2 uses them as fixtures. Until they exist, build against this contract with short placeholder text.

## The shape

`App\Support\CompanyProfile\Profile::data(string $locale, ?int $versionId = null): array` returns the published version (or the given version, for previews) in this shape. Every string is already in the requested locale.

```php
[
  'meta' => ['version' => 'v3', 'published_at' => '2026-11-06', 'locale' => 'en', 'reference' => 'JBS-CP-2026-11-v3'],
  'cover' => ['headline' => '...', 'descriptor' => '...'],
  'pov' => ['title' => '...', 'paragraphs' => ['...', '...']],                       // point of view
  'system' => ['title' => '...', 'lead' => '...',
               'pillars' => [['title' => '...', 'body' => '...'], ...]],               // what we build
  'problems' => ['title' => '...', 'lead' => '...',
                 'groups' => [['domain' => '...', 'items' => [['problem' => '...', 'answer' => '...'], ...]], ...],
                 'closing' => '...'],                                                   // open set, never "N problems"
  'practices' => ['title' => '...', 'lead' => '...',
                  'items' => [['name' => '...', 'summary' => '...', 'capabilities' => ['...', ...]], ...]],   // 4 practices
  'method' => ['title' => '...', 'lead' => '...',
               'stages' => [['name' => 'See', 'body' => '...'], ...],                   // See, Design, Build, Hold
               'principles' => [['title' => '...', 'body' => '...'], ...]],              // foundation and floors, continuous improvement, ownership
  'teams' => ['title' => '...', 'lead' => '...',
              'items' => [['name' => '...', 'summary' => '...', 'disciplines' => ['...'], 'experience' => '...',
                           'credentials' => [['name' => 'FMVA', 'issuer' => 'CFI', 'held' => true], ...]], ...]],
  'work' => ['title' => '...', 'lead' => '...',
             'items' => [['client' => '...', 'sector' => '...', 'title' => '...', 'problem' => '...', 'built' => '...', 'outcome' => '...', 'image' => 'path or null'], ...]],
  'clients' => ['title' => '...', 'logos' => [['name' => '...', 'path' => 'absolute or storage path to a PNG/SVG']]],
  'security' => ['title' => '...', 'items' => [['title' => '...', 'body' => '...'], ...]],
  'facts' => ['title' => '...', 'items' => [['label' => '...', 'value' => '...', 'enabled' => true], ...]],
  'contact' => ['cta' => '...', 'cta_url' => 'https://jbspraxis.com/schedule', 'email' => 'hello@jbspraxis.com',
                'sales_email' => 'sales@jbspraxis.com', 'phone' => '...', 'website' => 'jbspraxis.com', 'offices' => ['...'],
                'closing' => 'Order, made visible.'],   // the brand's sign-off, last page only
]
```

## Rules every PR keeps

- **Only held credentials and enabled facts are ever rendered** (PDF, deck, letter, share link). The editor shows all of them, with the switch.
- **Team data is private:** HQ only, behind the `company_profile` permission (view, edit, approve, manage). It is never exposed on the website or the portal. People are never named; teams are described by discipline and credentials.
- **No prices** anywhere.
- **Banned words in copy:** "squad", "deployed", "buy"/"sell" for our systems ("we build"), "leverage", "cutting-edge", "solutions provider", "one-stop shop".
- **Client naming:** Fidson is never named ("a leading pharmaceutical manufacturer, with Tales Consulting"). La Petite Cour may be named (the website names it since 4 Nov). The oil and gas client is "An oil and gas company".
- **The Cameroon entity fact** exists but defaults to `enabled = false`.
- `docs/BRAND.md` wins on every visual and language question.
