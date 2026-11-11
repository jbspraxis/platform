<?php

namespace Tests\Feature\CompanyProfile;

use App\Support\CompanyProfile\Measure;
use App\Support\CompanyProfile\PdfRenderer;
use App\Support\Opportunities\ProposalPdf;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The company profile's PDF is kept once drawn (PdfRenderer::kept), under a hash of what it is drawn from: the
 * same profile again, from HQ's Download or a prospect's share link, is the kept file instead of one to three
 * seconds of laying the book out. Changed words, or a logo replaced under the same file name, draw it anew.
 */
class ProfilePdfCacheTest extends TestCase
{
    /** @var list<string> */
    private array $before = [];

    private string $library = '';

    protected function setUp(): void
    {
        parent::setUp();
        Measure::forget();
        $this->before = $this->kept();
        $this->library = sys_get_temp_dir() . '/jbs-profile-cache-' . bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->library);
    }

    protected function tearDown(): void
    {
        File::delete(array_diff($this->kept(), $this->before));
        File::deleteDirectory($this->library);
        parent::tearDown();
    }

    /** The profile PDFs in the cache folder now */
    private function kept(): array
    {
        return glob(ProposalPdf::cacheFolder() . '/profile-*.pdf') ?: [];
    }

    private function logo(string $fill, int $bars = 1): string
    {
        $path = $this->library . '/client-mark.svg';
        $rects = str_repeat('<rect x="12" y="12" width="96" height="36" fill="' . $fill . '"/>', $bars);
        File::put($path, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 60"><rect x="0" y="0" width="240" height="60" fill="#0C142B"/>' . $rects . '</svg>');

        return str_replace(DIRECTORY_SEPARATOR, '/', $path);
    }

    public function test_the_same_profile_is_served_from_the_cache_and_a_change_draws_it_anew(): void
    {
        $data = json_decode((string) file_get_contents(database_path('seeders/company-profile/content.en.json')), true, 512, JSON_THROW_ON_ERROR);
        $data['meta']['locale'] = 'en';
        // A reference of its own, so nothing another test drew is already kept for it
        $data['meta']['reference'] = 'JBS-CP-' . strtoupper(bin2hex(random_bytes(3)));
        $data['clients']['logos'] = [['name' => 'Client Mark', 'path' => $this->logo('#0BB8C4')]];
        $renderer = new PdfRenderer;

        $first = $renderer->pdf($data);
        $this->assertStringStartsWith('%PDF-', $first);
        $new = array_values(array_diff($this->kept(), $this->before));
        $this->assertCount(1, $new, 'The book is kept');
        $this->assertSame($first, File::get($new[0]));
        $this->assertSame([], glob(ProposalPdf::cacheFolder() . '/profile-*.tmp') ?: [], 'No temporary file is left behind');

        // Proven served from the cache: the kept file's bytes come back, not a new layout
        File::put($new[0], '%PDF-kept');
        $this->assertSame('%PDF-kept', $renderer->pdf($data));

        // The logo replaced under the same file name: drawn anew
        $this->logo('#C4500B', 2);
        $this->assertStringStartsWith('%PDF-1.', $renderer->pdf($data));
        $this->assertCount(2, array_diff($this->kept(), $this->before));

        // So is a change to the words
        $data['meta']['version'] = 'v9.9';
        $this->assertStringStartsWith('%PDF-1.', $renderer->pdf($data));
        $this->assertCount(3, array_diff($this->kept(), $this->before));
    }
}
