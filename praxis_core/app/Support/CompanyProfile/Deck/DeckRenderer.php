<?php

namespace App\Support\CompanyProfile\Deck;

use App\Support\CompanyProfile\ClientLogos;
use App\Support\CompanyProfile\LogoLibrary;
use App\Support\CompanyProfile\Typography;
use RuntimeException;

/**
 * The company profile as an animated PowerPoint deck: twenty-four slides, English and French, written
 * from the published profile and nothing else.
 *
 * It is the sibling of App\Support\CompanyProfile\PdfRenderer — same contract, same rules, same brand —
 * and it is built the way the owner asked for: one template designed once, which HQ fills by writing the
 * text, the pictures and the relationships into its OOXML and repackaging it.
 *
 * - **The template is resources/company-profile/deck/parts.** Every fixed part of the package is a file
 *   there: the content types, the theme, the master, the one blank layout, the presentation and its
 *   relationships. They are copied in untouched, except for docProps, which takes this profile's title,
 *   its version and its date.
 * - **The slides are written by Deck\Blueprint.** Twenty-four of them, each one a list of shapes, a
 *   transition and a build. A slide is OOXML all the way down; PhpPresentation is not used anywhere,
 *   because it cannot author an entrance animation or a Morph transition at all.
 * - **The archive is written by Deck\Zip**, so the deck needs no `ext-zip` — the live web PHP has none,
 *   the same ground DOCUMENTS.md found for gd and imagick.
 *
 * The file is deterministic: the same profile in the same language gives the same bytes twice.
 */
class DeckRenderer
{
    public function __construct(private readonly ?string $template = null) {}

    /** The .pptx, as bytes. */
    public function pptx(array $data): string
    {
        $locale = (string) ($data['meta']['locale'] ?? 'en');
        $data = Typography::prepare($data, $locale);
        $data['clients']['logos'] = $this->logos($data);

        $slides = (new Blueprint($data, $locale))->slides();

        $problems = Fit::problems($slides);
        if ($problems !== []) {
            // The design is wrong for this copy, not the copy for the design: say so rather than shrink
            throw new RuntimeException('The deck does not fit in '.strtoupper($locale).': '.Fit::describe($problems));
        }

        return Zip::write($this->parts($data, $slides));
    }

    /**
     * The wall's logos, as the deck wants them.
     *
     * The contract's `clients.logos` carries the print file, which may be an SVG, and PowerPoint will not
     * place an SVG without a raster copy beside it that nothing on the server can make. The logo library
     * keeps a transparent PNG of every logo for exactly this (docs/COMPANY_PROFILE.md section 9), and it
     * knows which logos are ticked for the deck rather than for the book, so the deck asks it directly and
     * falls back to the contract when the library is not the provider.
     *
     * @return list<array<string, mixed>>
     */
    private function logos(array $data): array
    {
        $contract = (array) ($data['clients']['logos'] ?? []);

        // A wall is never worth a failed download: with no library — a preview off the seed, a test with no
        // database — the deck falls back to what the contract carries, and then to the clients it names.
        return rescue(function () use ($contract) {
            $provider = app()->bound(ClientLogos::class) ? app(ClientLogos::class) : null;

            return $provider instanceof LogoLibrary ? $provider->forProfile('deck') : $contract;
        }, $contract, false);
    }

    /**
     * Every file in the package, in the order it is written.
     *
     * @param  list<Slide>  $slides
     * @return array<string, string>
     */
    public function parts(array $data, array $slides): array
    {
        $files = [];
        foreach ($this->templateParts() as $path => $body) {
            $files[$path] = str_contains($path, 'docProps/') ? $this->fill($body, $data, $slides) : $body;
        }

        $media = [];
        foreach ($slides as $i => $slide) {
            $number = $i + 1;
            $slide->number($number);
            $files['ppt/slides/slide'.$number.'.xml'] = $slide->xml();
            $files['ppt/slides/_rels/slide'.$number.'.xml.rels'] = $slide->rels();
            $media += $slide->mediaParts($number);
        }

        foreach ($media as $path => $file) {
            $bytes = @file_get_contents($file);
            if ($bytes !== false) {
                $files[$path] = $bytes;
            }
        }

        return $files;
    }

    /**
     * The template's fixed parts, read off disk: package path => bytes.
     *
     * @return array<string, string>
     */
    public function templateParts(): array
    {
        $root = $this->template ?? resource_path('company-profile/deck/parts');
        $root = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', (string) (realpath($root) ?: $root)), '/');

        if (! is_dir($root)) {
            throw new RuntimeException("The deck template is missing: {$root}");
        }

        $files = [];
        $walk = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($walk as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $path = ltrim(str_replace([DIRECTORY_SEPARATOR, $root], ['/', ''], $file->getPathname()), '/');
            $files[$path] = (string) file_get_contents($file->getPathname());
        }

        // [Content_Types].xml must be the first entry of the package, and the rest read best in part order
        ksort($files);
        $ordered = [];
        foreach (['[Content_Types].xml', '_rels/.rels'] as $first) {
            if (isset($files[$first])) {
                $ordered[$first] = $files[$first];
                unset($files[$first]);
            }
        }

        return $ordered + $files;
    }

    /** @param list<Slide> $slides */
    private function fill(string $body, array $data, array $slides): string
    {
        $meta = $data['meta'] ?? [];
        $version = (string) ($meta['version'] ?? '');
        $locale = strtoupper((string) ($meta['locale'] ?? 'en'));
        $published = (string) ($meta['published_at'] ?? '');

        $replacements = [
            '{{TITLE}}' => 'JBS Praxis — '.(string) ($data['cover']['headline'] ?? 'Company profile'),
            '{{SUBJECT}}' => trim('Company profile '.$version.' '.$locale),
            '{{REVISION}}' => ltrim($version, 'vV') ?: '1',
            '{{CREATED}}' => ($published !== '' ? $published : '2026-01-01').'T00:00:00Z',
        ];

        foreach ($slides as $i => $slide) {
            $replacements['{{SLIDE'.($i + 1).'}}'] = $slide->name;
        }

        return str_replace(array_keys($replacements), array_map(
            fn ($value) => Ooxml::esc(Ooxml::clean((string) $value)),
            array_values($replacements),
        ), $body);
    }
}
