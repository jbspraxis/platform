<?php

namespace App\Support\CompanyProfile;

/**
 * Every file a profile page places, and the one rule that decides their formats.
 *
 * The live web PHP has no gd (docs/DOCUMENTS.md section 3). Without it dompdf cannot read a PNG at all
 * (Cpdf::addPngFromFile() returns before drawing when imagecreatefrompng() is missing) and a CSS
 * background-image throws (AbstractRenderer::_background_image), while a JPEG needs nothing but
 * getimagesize(). So the profile places JPEGs as <img>, carries no CSS background image, and the brand
 * lockups are JPEG renders rather than the kit's SVGs, which are an automatic trace with nested <svg>
 * that php-svg-lib draws only partly.
 *
 * tools/company-profile/build_assets.py builds everything in resources/company-profile/.
 */
final class Assets
{
    /**
     * A path dompdf will accept: absolute, with forward slashes. Windows backslashes survive the font and
     * image resolvers, but the chroot comparison is cleaner on one separator, and a path relative to the
     * document's base is not resolved for @font-face at all.
     */
    public static function path(string ...$parts): string
    {
        $path = self::root().'/'.implode('/', $parts);
        $real = realpath($path);

        return str_replace(DIRECTORY_SEPARATOR, '/', $real ?: $path);
    }

    public static function root(): string
    {
        return str_replace(DIRECTORY_SEPARATOR, '/', realpath(resource_path('company-profile')) ?: resource_path('company-profile'));
    }

    /** The seven cuts of the two brand families, keyed by the family name the stylesheet asks for. */
    public static function fonts(): array
    {
        return [
            ['family' => 'Newsreader Display', 'weight' => 400, 'style' => 'normal', 'file' => 'newsreader-display.ttf'],
            ['family' => 'Newsreader Headline', 'weight' => 500, 'style' => 'normal', 'file' => 'newsreader-headline.ttf'],
            ['family' => 'Newsreader Text', 'weight' => 400, 'style' => 'normal', 'file' => 'newsreader-text.ttf'],
            ['family' => 'Newsreader Text', 'weight' => 400, 'style' => 'italic', 'file' => 'newsreader-text-italic.ttf'],
            ['family' => 'Jakarta', 'weight' => 400, 'style' => 'normal', 'file' => 'jakarta-regular.ttf'],
            ['family' => 'Jakarta', 'weight' => 500, 'style' => 'normal', 'file' => 'jakarta-medium.ttf'],
            ['family' => 'Jakarta', 'weight' => 600, 'style' => 'normal', 'file' => 'jakarta-semibold.ttf'],
        ];
    }

    public static function font(string $file): string
    {
        return self::path('fonts', $file);
    }

    public static function brand(string $file): string
    {
        return self::path('brand', $file);
    }

    /**
     * A place photograph, cut to the shape it bleeds across: a whole A4 landscape page. build_assets.py
     * cut it to that exact ratio, so a page never has to squash an image or rely on dompdf cropping one.
     */
    public static function place(string $name, string $shape = 'page'): ?string
    {
        $path = self::root()."/img/{$name}-{$shape}.jpg";

        return is_file($path) ? self::path('img', "{$name}-{$shape}.jpg") : null;
    }

    /**
     * A client's own platform, as its team signs in to it. These are the website's screens, so they are
     * already public, and they are sign-in screens, so they carry no client records.
     *
     * The match is on the client's name, which is the same proper noun in both languages, because the
     * content has no field that names a screen: `work.items[].image` is null throughout. When the engine
     * lets the owner choose one, that is what should decide this, and this list can go.
     */
    public static function screenForClient(string $client): ?string
    {
        $haystack = mb_strtolower($client);
        $screens = [
            'smart-logistics' => ['smart logistics'],
            'pixie-girl-global' => ['pixie girl'],
            'fmcg-distributor-cameroon' => ['petite cour'],
            'orika-living' => ['orika'],
            'mycity-app' => ['mycity', 'my city'],
            'nfelia' => ['nfelia'],
        ];

        foreach ($screens as $name => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return self::screen($name);
                }
            }
        }

        return null;
    }

    public static function screen(string $name): ?string
    {
        $path = self::root()."/screens/{$name}.jpg";

        return is_file($path) ? self::path('screens', "{$name}.jpg") : null;
    }

    /**
     * The place photograph for a case story: its own `image` when the content names one, otherwise the
     * sector it belongs to, matched in either language (BRAND.md section 10 pairs one image to a sector).
     */
    public static function placeForSector(string $sector, ?string $image = null): ?string
    {
        if (is_string($image) && $image !== '' && is_file($image)) {
            return str_replace(DIRECTORY_SEPARATOR, '/', $image);
        }

        $haystack = mb_strtolower($sector);
        $sectors = [
            'pharmaceuticals' => ['pharmac'],
            'logistics' => ['logistic', 'logistique', 'freight', 'fret', 'transport'],
            'oil-and-gas' => ['oil', 'gas', 'pétrol', 'petrol', 'gaz'],
            'consumer-goods' => ['consumer', 'consommation', 'distribution', 'retail', 'détail', 'detail', 'brand', 'marque'],
        ];

        foreach ($sectors as $name => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return self::place($name);
                }
            }
        }

        return self::place('lagos');
    }

    /**
     * How large one logo is drawn in the wall. Logos arrive at every proportion, and matching them on
     * height alone makes a wide wordmark shout and a square mark whisper; matching them on area is what
     * a designer does by eye. The box is clamped so nothing breaks the row or the column.
     *
     * @return array{width: float, height: float}|null
     */
    public static function logoBox(string $path, float $area = 1250.0, float $maxWidth = 108.0, float $maxHeight = 27.0): ?array
    {
        $size = @getimagesize($path);
        if ($size === false || ($size[1] ?? 0) <= 0) {
            return null;
        }

        $ratio = $size[0] / $size[1];
        $width = sqrt($area * $ratio);
        $height = $width / $ratio;

        if ($width > $maxWidth) {
            $width = $maxWidth;
            $height = $width / $ratio;
        }
        if ($height > $maxHeight) {
            $height = $maxHeight;
            $width = $height * $ratio;
        }

        return ['width' => round($width, 2), 'height' => round($height, 2)];
    }
}
