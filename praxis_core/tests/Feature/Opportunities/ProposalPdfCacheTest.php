<?php

namespace Tests\Feature\Opportunities;

use App\Support\Opportunities\ProposalPdf;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Proposal, SOW and invoice PDFs all come through ProposalPdf::fromHtml(), which keeps each one under a hash of
 * exactly what it draws: the same document again is the kept file (no dompdf render), a changed one is drawn anew,
 * and files not served for 30 days are removed when a new one is kept.
 */
class ProposalPdfCacheTest extends TestCase
{
    /** @var list<string> */
    private array $made = [];

    protected function tearDown(): void
    {
        foreach ($this->made as $file) {
            File::delete($file);
        }
        parent::tearDown();
    }

    private function render(string $html, string $footer = 'PRP-1 v1'): string
    {
        $fonts = storage_path('framework/cache/dompdf');
        File::ensureDirectoryExists($fonts);

        return ProposalPdf::fromHtml($html, $footer, 'Page :n of :of', $fonts);
    }

    /** The files in the cache folder now */
    private function kept(): array
    {
        return glob(ProposalPdf::cacheFolder() . '/*.pdf') ?: [];
    }

    public function test_the_same_document_is_served_from_the_cache_and_a_changed_one_is_drawn_anew(): void
    {
        $unique = bin2hex(random_bytes(8));
        $html = "<html><body><h1>Proposal {$unique}</h1><p>Total USD 12,500.00</p></body></html>";
        $before = $this->kept();

        $first = $this->render($html);
        $this->assertStringStartsWith('%PDF-', $first);
        $new = array_values(array_diff($this->kept(), $before));
        $this->made = $new;
        $this->assertCount(1, $new, 'The rendered PDF is kept');
        $this->assertSame($first, File::get($new[0]));
        $this->assertSame([], glob(ProposalPdf::cacheFolder() . '/*.tmp') ?: [], 'No temporary file is left behind');

        // Proven served from the cache: the kept file's bytes come back, not a new render
        File::put($new[0], '%PDF-kept ' . $unique);
        $this->assertSame('%PDF-kept ' . $unique, $this->render($html));

        // A changed page (here only the footer) is drawn anew and kept beside the first
        $changed = $this->render($html, 'PRP-1 v2');
        $this->assertStringStartsWith('%PDF-', $changed);
        $this->assertNotSame('%PDF-kept ' . $unique, $changed);
        $this->made = array_values(array_diff($this->kept(), $before));
        $this->assertCount(2, $this->made);

        // So is a changed body
        $this->assertStringStartsWith('%PDF-1.', $this->render(str_replace('12,500.00', '12,600.00', $html)));
        $this->made = array_values(array_diff($this->kept(), $before));
        $this->assertCount(3, $this->made);
    }

    public function test_a_kept_file_that_is_not_a_pdf_is_drawn_again(): void
    {
        $html = '<html><body><p>Invoice ' . bin2hex(random_bytes(8)) . '</p></body></html>';
        $before = $this->kept();
        $this->render($html);
        $this->made = array_values(array_diff($this->kept(), $before));
        $this->assertCount(1, $this->made);

        // A truncated or emptied file (a full disk, say) is never sent: the page is rendered and kept again
        File::put($this->made[0], '');
        $pdf = $this->render($html);
        $this->assertStringStartsWith('%PDF-1.', $pdf);
        $this->assertSame($pdf, File::get($this->made[0]));
    }

    public function test_files_not_served_for_30_days_are_pruned_when_a_new_pdf_is_kept(): void
    {
        $folder = ProposalPdf::cacheFolder();
        File::ensureDirectoryExists($folder);
        $old = $folder . '/' . str_repeat('0', 56) . bin2hex(random_bytes(4)) . '.pdf';
        $recent = $folder . '/' . str_repeat('1', 56) . bin2hex(random_bytes(4)) . '.pdf';
        File::put($old, '%PDF-old');
        File::put($recent, '%PDF-recent');
        touch($old, time() - 31 * 86400);
        touch($recent, time() - 29 * 86400);
        $this->made = [$old, $recent];
        $before = $this->kept();

        $this->render('<html><body><p>Statement of work ' . bin2hex(random_bytes(8)) . '</p></body></html>');
        $this->made = [...$this->made, ...array_diff($this->kept(), $before)];

        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($recent);
    }
}
