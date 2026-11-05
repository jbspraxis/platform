<?php

namespace App\Console\Commands;

use App\Support\CompanyProfile\Deck\Blueprint;
use App\Support\CompanyProfile\Deck\DeckRenderer;
use App\Support\CompanyProfile\Deck\Fit;
use App\Support\CompanyProfile\Deck\Zip;
use App\Support\CompanyProfile\Typography;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Render the company profile deck from the seeded content on disk, without HQ, a database or a sign-in —
 * the sibling of company-profile:preview, and how the deck is proofed (docs/company-profile/DECK.md).
 *
 *   php artisan company-profile:deck              both languages, into storage/app/company-profile
 *   php artisan company-profile:deck fr --fit     measure the French deck and say what does not fit
 */
class CompanyProfileDeck extends Command
{
    protected $signature = 'company-profile:deck
        {locale? : en or fr; both when left out}
        {--fit : measure every slide and report what overflows, without writing a file}
        {--path= : write into this folder instead of storage/app/company-profile}';

    protected $description = 'Render the company profile deck (.pptx) from the seeded content';

    public function handle(): int
    {
        $locales = ($locale = $this->argument('locale')) ? [$locale] : ['en', 'fr'];
        $directory = $this->option('path') ?: storage_path('app/company-profile');
        File::ensureDirectoryExists($directory);

        $renderer = new DeckRenderer;
        $failed = false;

        foreach ($locales as $locale) {
            $source = database_path("seeders/company-profile/content.{$locale}.json");
            if (! is_file($source)) {
                $this->error("No content for {$locale}: {$source}");

                return self::FAILURE;
            }

            $data = json_decode((string) file_get_contents($source), true, 512, JSON_THROW_ON_ERROR);
            $data['meta']['locale'] = $locale;

            if ($this->option('fit')) {
                $slides = (new Blueprint(Typography::prepare($data, $locale), $locale))->slides();
                $problems = Fit::problems($slides);
                if ($problems === []) {
                    $this->line($locale.'  '.count($slides).' slides, all of them fit');
                } else {
                    $failed = true;
                    $this->error($locale.'  '.Fit::describe($problems));
                }

                continue;
            }

            $started = microtime(true);
            $pptx = $renderer->pptx($data);
            $target = $directory."/company-profile-{$locale}.pptx";
            file_put_contents($target, $pptx);

            $this->line(sprintf(
                '%s  %s  %d KB  %d parts  %.2fs',
                $locale,
                $target,
                strlen($pptx) / 1024,
                count(Zip::entries($pptx)),
                microtime(true) - $started
            ));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
