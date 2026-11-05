<?php

namespace Tests\Feature\Letters;

use App\Support\Letters\Agreements;
use App\Support\Letters\Letters;
use App\Support\Signing\SigningCodes;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * Renders the first real agreement — the project contractor agreement — as it will go out, and as it will come
 * back signed, and writes both PDFs where the preview pictures in docs/documents/previews are made from
 * (brand/build/agreement_previews.py). Run it on its own when the layout changes:
 *
 *   AGREEMENT_PREVIEW_DIR=../docs/documents/previews php vendor/bin/phpunit --filter AgreementPreviewTest
 *
 * It is skipped unless that folder is asked for, so an ordinary test run writes nothing.
 */
class AgreementPreviewTest extends TestCase
{
    use AgreementFixtures, LetterFixtures, UsesThrowawayDatabase;

    public function test_it_writes_the_contract_as_it_goes_out_and_as_it_comes_back(): void
    {
        $where = env('AGREEMENT_PREVIEW_DIR');
        if (! $where) {
            $this->markTestSkipped('Set AGREEMENT_PREVIEW_DIR to write the preview PDFs.');
        }
        $where = str_starts_with($where, '.') ? base_path($where) : $where;
        if (! is_dir($where)) {
            mkdir($where, 0775, true);
        }

        Mail::fake();
        $owner = $this->owner();
        $this->actingAs($owner);
        \App\Support\Letters\Signatures::save($owner, $this->specimen(), 'Tom-Blake A. Forghab', 'Director', 'drawn', 1109, 344);
        $document = $this->agreement($owner, $this->company());

        Letters::sign($document, $owner);
        Letters::issue($document->fresh(), $owner);
        $issued = Letters::file($document->fresh());
        file_put_contents($where . '/agreement-issued.pdf', $issued);

        $party = Agreements::send($document->fresh(), $owner);
        $code = SigningCodes::newCode();
        Agreements::holdSignature($party, 'Jonathan Ndamzi Destiny', $this->signatureImage(), $code);
        $this->assertTrue(Agreements::countersign($party->fresh(), $code, '102.89.33.10'));

        $signed = Agreements::signedFile($document->fresh());
        file_put_contents($where . '/agreement-signed.pdf', $signed);

        $this->assertGreaterThan(3, $this->pages($issued));
        $this->assertGreaterThan($this->pages($issued), $this->pages($signed), 'the signature record adds a page');

        fwrite(STDERR, "\nissued: " . $this->pages($issued) . " pages, signed: " . $this->pages($signed) . " pages\n");
    }

    /**
     * A specimen scribble on white, nobody's real signature: it only has to look like ink in the pictures.
     * brand/build/agreement_previews.py says how it was drawn.
     */
    private function specimen(): string
    {
        return 'data:image/jpeg;base64,' . base64_encode((string) file_get_contents(__DIR__ . '/fixtures/specimen-signature.jpg'));
    }

    private function signatureImage(): string
    {
        return substr($this->specimen(), strlen('data:image/jpeg;base64,'));
    }

    private function pages(string $pdf): int
    {
        preg_match_all('~/Type\s*/Page[^s]~', $pdf, $matches);

        return count($matches[0]);
    }
}
