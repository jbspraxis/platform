<?php

namespace Tests\Feature\CompanyProfile;

use App\Models\CompanyProfile\ProfileVersion;
use App\Support\CompanyProfile\CompanyProfileRenderer;
use App\Support\CompanyProfile\DirectoryClientLogos;
use App\Support\CompanyProfile\Importer;
use App\Support\CompanyProfile\Profile;
use App\Support\CompanyProfile\StubProfileRenderer;
use Database\Seeders\CompanyProfileSeeder;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * The seeder that brings the written copy in (docs/COMPANY_PROFILE.md section 7), and the download hook PR 2
 * takes over (section 8).
 */
class ProfileSeederAndDownloadTest extends TestCase
{
    use ProfileFixtures, UsesThrowawayDatabase;

    public function test_the_seeder_publishes_version_one_from_the_content_files(): void
    {
        $version = $this->published();

        $this->assertSame(1, $version->number);
        $this->assertSame(ProfileVersion::PUBLISHED, $version->status);
        $this->assertCount(12, $version->sections, 'every section is stored, even the ones with no list');
        $this->assertNotSame('', Profile::data('en')['cover']['headline']);
        $this->assertNotSame('', Profile::data('fr')['cover']['headline']);
    }

    public function test_the_seeder_leaves_a_database_that_already_has_a_version_alone(): void
    {
        $before = ProfileVersion::pluck('reference', 'id')->all();

        (new CompanyProfileSeeder)->run();

        $this->assertSame($before, ProfileVersion::pluck('reference', 'id')->all());
    }

    public function test_the_importer_lines_the_two_files_up_item_by_item(): void
    {
        ProfileVersion::query()->delete();

        $importer = new Importer;
        $version = $importer->import(
            [
                'cover' => ['headline' => 'One', 'descriptor' => 'Two'],
                'facts' => ['title' => 'The firm', 'items' => [
                    ['label' => 'Founded', 'value' => '2024', 'enabled' => true],
                    ['label' => 'Also', 'value' => 'Hidden', 'enabled' => false],
                ]],
                'teams' => ['title' => 'Teams', 'lead' => '', 'items' => [[
                    'name' => 'Finance', 'summary' => '', 'experience' => '', 'disciplines' => ['Modelling'],
                    'credentials' => [['name' => 'FMVA', 'issuer' => 'CFI', 'held' => true]],
                ]]],
            ],
            [
                'cover' => ['headline' => 'Un', 'descriptor' => 'Deux'],
                'facts' => ['title' => 'Le cabinet', 'items' => [
                    ['label' => 'Création', 'value' => '2024'],
                    ['label' => 'Aussi', 'value' => 'Caché'],
                ]],
                'teams' => ['title' => 'Équipes', 'lead' => '', 'items' => [[
                    'name' => 'Finance', 'summary' => '', 'experience' => '', 'disciplines' => ['Modélisation'],
                ]]],
            ],
            'Test import',
        );

        $this->assertSame([], $importer->warnings);
        $this->assertSame('One', Profile::dataFor($version, 'en')['cover']['headline']);
        $this->assertSame('Un', Profile::dataFor($version, 'fr')['cover']['headline']);

        // The switch came from the English file and holds in both
        foreach (['en', 'fr'] as $locale) {
            $this->assertCount(1, Profile::dataFor($version, $locale)['facts']['items']);
            $this->assertSame(
                [['name' => 'FMVA', 'issuer' => 'CFI', 'held' => true]],
                Profile::dataFor($version, $locale)['teams']['items'][0]['credentials'],
            );
        }

        $this->assertSame('Modélisation', Profile::dataFor($version, 'fr')['teams']['items'][0]['disciplines'][0]);
    }

    public function test_the_importer_says_when_the_two_files_do_not_line_up(): void
    {
        ProfileVersion::query()->delete();

        $importer = new Importer;
        $importer->import(
            ['pov' => ['title' => 'View', 'paragraphs' => ['One', 'Two', 'Three']]],
            ['pov' => ['title' => 'Vue', 'paragraphs' => ['Un']]],
        );

        $this->assertNotEmpty($importer->warnings);
        $this->assertStringContainsString('pov.paragraphs', $importer->warnings[0]);
    }

    public function test_the_download_route_hands_the_contract_data_to_the_renderer(): void
    {
        $renderer = new class implements CompanyProfileRenderer
        {
            public array $seen = [];

            public function pdf(array $data): string
            {
                $this->seen = $data;

                return '%PDF-1.4 test';
            }
        };
        $this->app->instance(CompanyProfileRenderer::class, $renderer);

        $response = $this->actingAs($this->person(['view']))
            ->get($this->hqUrl('/company-profile/download?locale=fr'));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('.pdf"', $response->headers->get('content-disposition'));
        $this->assertSame(Profile::data('fr'), $renderer->seen, 'the renderer is handed exactly what the contract fixes');
    }

    public function test_the_stub_renderer_writes_a_pdf(): void
    {
        $pdf = (new StubProfileRenderer)->pdf(Profile::data('en'));

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringEndsWith('%%EOF', $pdf);
        $this->assertStringContainsString('/MediaBox [0 0 842 595]', $pdf, 'A4 landscape, as the owner asked');
        $this->assertGreaterThan(600, strlen($pdf));

        // The cross-reference offsets must point at the objects, or a reader rejects the file
        preg_match('/startxref\n(\d+)\n%%EOF$/', $pdf, $m);
        $this->assertNotEmpty($m);
        $this->assertSame('xref', substr($pdf, (int) $m[1], 4));
    }

    public function test_the_stub_renderer_carries_french_accents(): void
    {
        $pdf = (new StubProfileRenderer)->pdf(Profile::data('fr'));

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('/WinAnsiEncoding', $pdf);
    }

    public function test_an_unknown_locale_is_not_downloadable(): void
    {
        $this->actingAs($this->person(['view']))
            ->get($this->hqUrl('/company-profile/download?locale=de'))
            ->assertNotFound();
    }

    public function test_the_download_says_so_when_nothing_is_published(): void
    {
        ProfileVersion::query()->delete();

        $this->actingAs($this->person(['view']))
            ->get($this->hqUrl('/company-profile/download?locale=en'))
            ->assertNotFound();
    }

    public function test_the_default_logo_provider_reads_a_folder(): void
    {
        $directory = sys_get_temp_dir().'/cp-logos-'.bin2hex(random_bytes(4));
        mkdir($directory, 0777, true);
        file_put_contents($directory.'/02 LPC.svg', '<svg/>');
        file_put_contents($directory.'/01 Fidson Healthcare.png', 'x');
        file_put_contents($directory.'/notes.txt', 'ignored');

        $logos = (new DirectoryClientLogos($directory))->all();

        $this->assertSame(['Fidson Healthcare', 'LPC'], array_column($logos, 'name'));

        array_map('unlink', glob($directory.'/*'));
        rmdir($directory);

        $this->assertSame([], (new DirectoryClientLogos($directory.'-gone'))->all());
    }
}
