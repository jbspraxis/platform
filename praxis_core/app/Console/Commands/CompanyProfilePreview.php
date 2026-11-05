<?php

namespace App\Console\Commands;

use App\Support\CompanyProfile\PdfRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Render the company profile from the approved content on disk, without HQ, a database or a signed link.
 * It is how the design is proofed: render both languages, turn the pages into images and look at every
 * one of them (docs/company-profile/DESIGN.md).
 *
 *   php artisan company-profile:preview            both languages, into storage/app/company-profile
 *   php artisan company-profile:preview fr --html  the French page as HTML too, to read the markup
 */
class CompanyProfilePreview extends Command
{
    protected $signature = 'company-profile:preview
        {locale? : en or fr; both when left out}
        {--html : write the HTML beside the PDF}
        {--path= : write into this folder instead of storage/app/company-profile}';

    protected $description = 'Render the company profile PDF from resources/company-profile/content';

    public function handle(): int
    {
        $locales = ($locale = $this->argument('locale')) ? [$locale] : ['en', 'fr'];
        $directory = $this->option('path') ?: storage_path('app/company-profile');
        File::ensureDirectoryExists($directory);

        $renderer = new PdfRenderer;

        foreach ($locales as $locale) {
            $source = resource_path("company-profile/content/content.{$locale}.json");
            if (! is_file($source)) {
                $this->error("No content for {$locale}: {$source}");

                return self::FAILURE;
            }

            $data = json_decode((string) file_get_contents($source), true, 512, JSON_THROW_ON_ERROR);
            $data['meta']['locale'] = $locale;

            $started = microtime(true);
            $pdf = $renderer->pdf($data);
            $target = $directory."/company-profile-{$locale}.pdf";
            file_put_contents($target, $pdf);

            if ($this->option('html')) {
                file_put_contents($directory."/company-profile-{$locale}.html", $renderer->html($data));
            }

            $this->line(sprintf(
                '%s  %s  %d KB  %.1fs',
                $locale,
                $target,
                strlen($pdf) / 1024,
                microtime(true) - $started
            ));
        }

        return self::SUCCESS;
    }
}
