<?php

namespace Tests\Feature\Letters;

use App\Livewire\Hq\Letters\DocumentPage;
use App\Support\Hosts;
use App\Support\Letters\LetterPdf;
use App\Support\Letters\Letters;
use App\Support\Opportunities\ProposalPdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * A draft's preview and the blank letterhead are kept once drawn, under a hash of exactly what is drawn
 * (LetterPdf::kept): the same page again is the kept file, a changed one is drawn anew. An issued document is
 * always drawn afresh, because the issued file is the record.
 */
class LetterPdfCacheTest extends TestCase
{
    use LetterFixtures, UsesThrowawayDatabase;

    /** @var list<string> */
    private array $before = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->before = $this->kept();
    }

    protected function tearDown(): void
    {
        File::delete(array_diff($this->kept(), $this->before));
        parent::tearDown();
    }

    /** The letter PDFs in the cache folder now */
    private function kept(): array
    {
        return glob(ProposalPdf::cacheFolder() . '/letter-*.pdf') ?: [];
    }

    private function hq(string $path): string
    {
        return 'https://' . Hosts::hq() . $path;
    }

    public function test_a_draft_preview_is_kept_and_a_changed_draft_is_drawn_anew(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $document = Letters::start('formal_letter', null, $this->company(), $owner);
        $document->forceFill(['title' => 'Letter ' . bin2hex(random_bytes(6))])->save();
        $url = $this->hq('/documents/' . $document->id . '/pdf');

        $first = $this->get($url)->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF-', $first);
        $new = array_values(array_diff($this->kept(), $this->before));
        $this->assertCount(1, $new, 'The preview is kept');
        $this->assertSame($first, File::get($new[0]));
        $this->assertSame([], glob(ProposalPdf::cacheFolder() . '/letter-*.tmp') ?: [], 'No temporary file is left behind');

        // Proven served from the cache: the kept file's bytes come back, not a new render
        File::put($new[0], '%PDF-kept');
        $this->assertSame('%PDF-kept', $this->get($url)->assertOk()->getContent());

        // Saved with a new title: drawn anew and kept beside the first
        Livewire::test(DocumentPage::class, ['document' => $document->id])->set('title', 'Another title')->call('save')->assertHasNoErrors();
        $changed = $this->get($url)->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF-1.', $changed);
        $this->assertCount(2, array_diff($this->kept(), $this->before));
    }

    public function test_an_issued_document_is_drawn_afresh_and_never_kept(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $this->signatureFor($owner);
        $document = Letters::start('formal_letter', null, $this->company(), $owner);
        $document->forceFill(['title' => 'Issued ' . bin2hex(random_bytes(6))])->save();
        Letters::sign($document, $owner);

        Letters::issue($document->fresh(), $owner);
        $this->assertSame([], array_values(array_diff($this->kept(), $this->before)), 'The record is not kept in the cache');
        $this->assertStringStartsWith('%PDF-1.', LetterPdf::render($document->fresh(), final: true));
        $this->assertSame([], array_values(array_diff($this->kept(), $this->before)));
    }

    public function test_the_blank_letterhead_is_kept(): void
    {
        $this->actingAs($this->owner());
        // An address of its own, so no blank letterhead another test drew is already kept for it
        $company = $this->company();
        DB::table('mdm_legal_entities')->where('id', $company['id'])->update(['registered_address' => 'Suite ' . random_int(100, 999999) . ', Lekki, Lagos']);
        $url = $this->hq('/documents/blank/' . $company['id'] . '.pdf');

        $this->get($url)->assertOk();
        $new = array_values(array_diff($this->kept(), $this->before));
        $this->assertCount(1, $new);
        File::put($new[0], '%PDF-kept blank');
        $this->assertSame('%PDF-kept blank', $this->get($url)->assertOk()->getContent());
    }
}
