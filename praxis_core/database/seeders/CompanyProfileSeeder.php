<?php

namespace Database\Seeders;

use App\Models\CompanyProfile\ProfileVersion;
use App\Support\CompanyProfile\Importer;
use App\Support\CompanyProfile\Profile;
use Illuminate\Database\Seeder;

/**
 * The first published version of the company profile (docs/COMPANY_PROFILE.md section 7), imported from
 * database/seeders/company-profile/content.en.json and content.fr.json.
 *
 * Those two files are where the copy lives. PR 1 ships a placeholder pair so the screens and the download
 * work; the lead chat replaces them with the written profile and this seeder is run again on a fresh
 * database. It never touches a database that already has a version, because that version may be the one
 * the firm is sending out.
 */
class CompanyProfileSeeder extends Seeder
{
    public function run(): void
    {
        if (ProfileVersion::exists()) {
            $this->command?->info('Company profile: a version already exists; left alone.');

            return;
        }

        [$en, $fr] = Importer::readFiles(database_path('seeders/company-profile'));

        if (! $en) {
            $this->command?->warn('Company profile: no content.en.json to import; nothing seeded.');

            return;
        }

        $importer = new Importer;
        $version = Profile::withoutAudit(fn () => $importer->import($en, $fr, 'Imported from the content files.', publish: true));

        foreach ($importer->warnings as $warning) {
            $this->command?->warn('Company profile: '.$warning);
        }

        $this->command?->info('Company profile: published '.$version->label().' ('.$version->reference.').');
    }
}
