<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * /verify/{token} and /fr/verify/{token} (docs/DOCUMENTS.md section 7): the page a QR code on a formal
 * document opens. Everything on it comes from HQ through the signed Site API; the tamper check happens in the
 * reader's own browser, so the fingerprint is all this page needs to carry.
 */
class VerifyDocumentTest extends TestCase
{
    private const TOKEN = 'nvlv2Sc1LG4bjVs4XJpdB9O9Q6H3zk8tKXL6Ikfytm9TGdRh';

    private const SHA = '1f674faa0ea951ef61c58dd093f0cd4cd5f5aebf27665eae37d1c213c455d79c';

    private function document(array $overrides = []): array
    {
        return ['data' => array_merge([
            'reference' => 'JBS/RES/2026/001',
            'title' => 'Resolution of the director: opening of a corporate bank account',
            'kind' => 'Board resolution: Open a corporate bank account',
            'type' => 'board_resolution',
            'subtype' => 'open_bank_account',
            'issued_by' => 'JBS Praxis Limited',
            'document_date' => '2026-11-04',
            'issued_at' => '2026-11-04T10:30:00+01:00',
            'addressed_to' => 'Example Microfinance Bank',
            'signatories' => [['name' => 'Ada Test', 'title' => 'Director']],
            'status' => 'issued',
            'revoked_at' => null,
            'revoked_reason' => null,
            'replaced_by' => null,
            'replaced_at' => null,
            'replaces' => null,
            'file_sha256' => self::SHA,
            'file_bytes' => 142838,
            'copy_available' => true,
        ], $overrides)];
    }

    public function test_a_valid_document_says_who_issued_it_and_who_signed_it(): void
    {
        $this->fakeHq(['documents/' . self::TOKEN => Http::response($this->document())]);

        $this->get('/verify/' . self::TOKEN)->assertOk()
            ->assertSee('Genuine document issued by JBS Praxis Limited')
            ->assertSee('It is valid: it has not been revoked or replaced.')
            ->assertSee('JBS/RES/2026/001')
            ->assertSee('4 November 2026')
            ->assertSee('Example Microfinance Bank')
            ->assertSee('Ada Test')
            ->assertSee('Director')
            ->assertSee(self::SHA)
            ->assertSee('Open the document')
            // A private link: never in a search engine
            ->assertSee('noindex, nofollow', false);
    }

    public function test_a_revoked_document_says_when_and_why(): void
    {
        $this->fakeHq(['documents/' . self::TOKEN => Http::response($this->document([
            'status' => 'revoked', 'revoked_at' => '2026-11-04', 'revoked_reason' => 'The visit was moved.', 'copy_available' => false,
        ]))]);

        $this->get('/verify/' . self::TOKEN)->assertOk()
            ->assertSee('It was revoked on 4 November 2026 and no longer stands.')
            ->assertSee('Why it was revoked: The visit was moved.')
            ->assertSee('This document is confidential')
            ->assertDontSee('Open the document');
    }

    public function test_a_replaced_document_names_what_took_its_place(): void
    {
        $this->fakeHq(['documents/' . self::TOKEN => Http::response($this->document([
            'status' => 'replaced', 'replaced_by' => 'JBS/RES/2026/002', 'replaced_at' => '2026-11-05',
        ]))]);

        $this->get('/verify/' . self::TOKEN)->assertOk()
            ->assertSee('It was replaced on 5 November 2026 by JBS/RES/2026/002.')
            ->assertSee('Ask whoever gave you this document for JBS/RES/2026/002');
    }

    public function test_an_unknown_token_answers_in_the_same_words_as_any_other_scan(): void
    {
        $this->fakeHq(['documents/*' => Http::response(['error' => ['code' => 'not_found']], 404)]);

        $this->get('/verify/' . self::TOKEN)->assertNotFound()
            ->assertSee('Is this document genuine?')
            ->assertSee('We have no record of this document')
            ->assertDontSee('JBS/RES/2026/001');
    }

    public function test_the_page_carries_the_fingerprint_and_checks_the_file_in_the_reader_s_browser(): void
    {
        $this->fakeHq(['documents/' . self::TOKEN => Http::response($this->document())]);

        $page = $this->get('/verify/' . self::TOKEN)->assertOk()->getContent();

        // The panel the script works on, and the fingerprint it compares against
        $this->assertStringContainsString('data-verify-check', $page);
        $this->assertStringContainsString('data-expected="' . self::SHA . '"', $page);
        $this->assertStringContainsString('data-verify-drop', $page);
        // Nothing about the check is posted anywhere: the file input sits outside any form
        $panel = substr($page, (int) strpos($page, 'data-verify-check'));
        $panel = substr($panel, 0, (int) strpos($panel, '</section>'));
        $this->assertStringContainsString('<input type="file"', $panel);
        $this->assertStringNotContainsString('<form', $panel);
    }

    public function test_the_copy_is_served_through_the_site_so_no_reader_needs_an_address_on_hq(): void
    {
        $this->fakeHq(['documents/' . self::TOKEN . '/file' => Http::response('%PDF-1.4 test', 200, ['Content-Type' => 'application/pdf'])]);

        $this->get('/verify/' . self::TOKEN . '/document.pdf')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_a_document_whose_copy_is_private_is_not_served(): void
    {
        $this->fakeHq(['documents/*' => Http::response(['error' => ['code' => 'not_found']], 404)]);

        $this->get('/verify/' . self::TOKEN . '/document.pdf')->assertNotFound();
    }

    public function test_the_french_page_names_the_kind_and_the_date_in_french(): void
    {
        $this->fakeHq(['documents/' . self::TOKEN => Http::response($this->document())]);

        $page = $this->get('/fr/verify/' . self::TOKEN)->assertOk()
            ->assertSee('Ce document est-il authentique')
            ->assertSee('Document authentique émis par JBS Praxis Limited')
            ->assertSee('Il est valable')
            ->assertSee('4 novembre 2026')
            ->assertSee('Résolution du conseil : ouverture d’un compte bancaire professionnel')
            ->getContent();

        // French typography (docs/BRAND.md section 4): the narrow no-break space before the question mark
        $this->assertStringContainsString("authentique\u{202F}?", $page);
    }

    public function test_a_token_that_is_not_a_token_never_reaches_hq(): void
    {
        $this->fakeHq([]);

        $this->get('/verify/short')->assertNotFound();
        $this->get('/verify/' . str_repeat('a', 80))->assertNotFound();
        $this->get('/verify/' . str_repeat('a', 40) . '/../etc')->assertNotFound();
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/documents/'));
    }
}
