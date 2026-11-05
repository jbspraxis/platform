<?php

namespace Tests\Feature\CompanyProfile;

use App\Support\CompanyProfile\CompanyProfileRenderer;
use App\Support\CompanyProfile\PdfRenderer;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * The Download button, end to end: HQ's route, the bound renderer, and the bytes a client receives
 * (docs/COMPANY_PROFILE.md section 8, docs/company-profile/DESIGN.md).
 *
 * ProfileSeederAndDownloadTest already proves the route hands the renderer exactly what the contract
 * fixes, with a fake renderer in the way. This proves the other half: that the renderer the application
 * actually resolves is the designed one, and that what comes back down the wire is the twelve-page book
 * rather than the placeholder.
 *
 * It renders the real thing twice, once per language, which costs about three seconds a locale. That is
 * what a download costs, and it is worth paying here, because a test with a fake renderer in it cannot
 * tell you the button works.
 */
class ProfileDownloadPdfTest extends TestCase
{
    use ProfileFixtures, UsesThrowawayDatabase;

    public function test_the_download_button_sends_the_designed_profile_not_the_stub(): void
    {
        $this->assertInstanceOf(
            PdfRenderer::class,
            app(CompanyProfileRenderer::class),
            'the application still resolves the placeholder renderer'
        );

        foreach (['en' => 'EN', 'fr' => 'FR'] as $locale => $suffix) {
            $response = $this->actingAs($this->person(['view']))
                ->get($this->hqUrl("/company-profile/download?locale={$locale}"));

            $response->assertOk();
            $response->assertHeader('content-type', 'application/pdf');

            $filename = (string) $response->headers->get('content-disposition');
            $this->assertStringContainsString('attachment;', $filename, $locale);
            $this->assertStringContainsString('JBS Praxis company profile ', $filename, $locale);
            $this->assertStringContainsString($this->published()->reference ?? 'JBS-CP', $filename, $locale);
            $this->assertStringContainsString($suffix.'.pdf"', $filename, $locale);

            $pdf = (string) $response->getContent();

            $this->assertStringStartsWith('%PDF-', $pdf, "{$locale} is not a PDF");
            $this->assertStringContainsString('%%EOF', substr($pdf, -1024), "{$locale} has no end-of-file marker");

            // The placeholder is one page of Helvetica with no images; the designed book is twelve pages
            // carrying the brand's own fonts, so neither the length nor the size can be mistaken.
            $this->assertSame(12, $this->pages($pdf), "{$locale} is not twelve pages");
            $this->assertGreaterThan(300_000, strlen($pdf), "{$locale} is too small to be carrying its imagery");
            $this->assertStringNotContainsString('/BaseFont /Helvetica', $pdf, "{$locale} is the placeholder");
        }
    }

    public function test_the_download_is_private_and_never_cached(): void
    {
        $response = $this->actingAs($this->person(['view']))
            ->get($this->hqUrl('/company-profile/download?locale=en'));

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
    }

    /** The page tree's own count, not the outline's, which is also written as /Count. */
    private function pages(string $pdf): int
    {
        preg_match('/\/Type \/Pages\b.*?\/Count (\d+)/s', $pdf, $match);

        return (int) ($match[1] ?? 0);
    }
}
