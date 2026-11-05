<?php

namespace Database\Seeders;

use App\Models\CompanyProfile\ClientLogo;
use App\Support\CompanyProfile\LogoLibrary;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * The client logo library starts with the fifteen logos the website already shows, in the order it shows them
 * (praxis_site config/site.php, 4 November 2026), so nothing changes on jbspraxis.com the day HQ takes the
 * strip over (docs/COMPANY_PROFILE.md section 4).
 *
 * The three renditions of each one live beside this file. They were made once from the website's WebP with
 * database/seeders/company-profile/build_logos.py: the artwork trimmed to its ink and scaled to 160 px high,
 * as a transparent PNG, a JPEG on white for the PDF, and a WebP for the site. No PHP here can convert an
 * image, so they are kept rather than built.
 *
 * Safe to run again: a key that is already in the library is left exactly as it is, so re-seeding never
 * overwrites a logo somebody replaced in HQ.
 */
class ClientLogoSeeder extends Seeder
{
    /**
     * key, the name as it may be shown, its sector, and where it appears.
     *
     * A sector is left empty where nothing on record says what it is; the owner fills those in on the screen,
     * rather than this file guessing. "An oil and gas services company" is the firm's own description of a
     * client that is not named (docs/BRAND.md section 1). The last two are our own products, which the strip
     * shows beside the clients but the company profile does not count as clients.
     */
    private const LOGOS = [
        ['smart-logistics', 'Smart Logistics and Services', 'Logistics', true],
        ['mtn', 'MTN', 'Telecoms', true],
        ['pixie-girl-global', 'Pixie Girl Global', 'Manufacturing and global brands', true],
        ['la-petite-cour', 'La Petite Cour', null, true],
        ['mycity-app', 'MyCity App', 'Digital', true],
        ['wellsmart-drilling', 'Wellsmart Drilling Nigeria', 'Oil and gas', true],
        ['orika-living', 'Orika Living', 'Retail', true],
        ['oil-gas-services', 'An oil and gas services company', 'Oil and gas', true],
        ['tales-consulting', 'Tales Consulting', 'Consulting', true],
        ['interworks-drill-tech', 'Interworks Drill Tech', 'Oil and gas', true],
        ['rn-afriluxe', 'RN Afriluxe', null, true],
        ['hod', 'HOD', null, true],
        ['ok-veggies', 'OK Veggies', 'Consumer goods', true],
        ['praxis-ls', 'Praxis LS', 'Our own products', false],
        ['nfelia', 'NFELIA', 'Our own products', false],
    ];

    public function run(): void
    {
        $folder = database_path('seeders/company-profile/logos');
        $disk = Storage::disk(LogoLibrary::DISK);
        $position = (int) ClientLogo::query()->max('position');

        foreach (self::LOGOS as [$key, $name, $sector, $isClient]) {
            if (ClientLogo::query()->where('key', $key)->exists()) {
                continue;
            }

            $files = [];
            foreach (['print' => 'jpg', 'png' => 'png', 'web' => 'webp'] as $slot => $extension) {
                $source = $folder . '/' . $key . '.' . $extension;
                if (! is_file($source)) {
                    continue;
                }
                $path = LogoLibrary::FOLDER . '/' . $key . '.' . $extension;
                $bytes = (string) file_get_contents($source);
                $disk->put($path, $bytes);
                $files[$slot] = ['path' => $path, 'bytes' => $bytes];
            }

            if (! isset($files['print'])) {
                continue;
            }

            ClientLogo::create([
                'key' => $key,
                'name' => $name,
                'sector' => $sector,
                'position' => ++$position,
                'on_profile' => $isClient,
                'on_deck' => $isClient,
                'on_website' => true,
                'print_path' => $files['print']['path'],
                'print_kind' => 'jpeg',
                'png_path' => $files['png']['path'] ?? null,
                'web_path' => $files['web']['path'] ?? null,
                'web_sha256' => isset($files['web']) ? hash('sha256', $files['web']['bytes']) : null,
            ]);
        }
    }
}
