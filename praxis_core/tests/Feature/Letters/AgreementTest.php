<?php

namespace Tests\Feature\Letters;

use App\Models\Letters\LetterCounterparty;
use App\Models\Letters\LetterDocument;
use App\Support\Letters\Agreements;
use App\Support\Letters\LetterPdf;
use App\Support\Letters\Letters;
use App\Support\Letters\LetterTypes;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * The Agreement kind (docs/DOCUMENTS.md section 12): its reference, its other party, and the document it prints
 * — the firm's first real one, the project contractor agreement, is the fixture.
 */
class AgreementTest extends TestCase
{
    use AgreementFixtures, LetterFixtures, UsesThrowawayDatabase;

    public function test_an_agreement_takes_its_own_run_of_reference_numbers(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $first = Letters::start('agreement', null, $this->company(), $owner);
        $second = Letters::start('agreement', null, $this->company(), $owner);
        $letter = Letters::start('formal_letter', null, $this->company(), $owner);

        $year = Letters::today()->format('Y');
        $this->assertSame("JBS/AGR/{$year}/001", $first->reference);
        $this->assertSame("JBS/AGR/{$year}/002", $second->reference);
        $this->assertSame("JBS/LET/{$year}/001", $letter->reference, 'an agreement does not take a letter’s numbers');
        $this->assertSame('AGR', $first->type_code);
        $this->assertTrue($first->isAgreement());
    }

    public function test_it_is_offered_in_the_template_picker_and_starts_without_a_salutation(): void
    {
        $this->assertArrayHasKey('agreement', LetterTypes::TYPES);
        $this->assertSame('AGR', LetterTypes::code('agreement'));
        $this->assertSame('Agreement', LetterTypes::label('agreement'));
        $this->assertTrue(LetterTypes::isFreeBody('agreement'), 'its body is written, not built from fields');

        $defaults = LetterTypes::defaults('agreement', null, ['office_lines' => [], 'representative' => '', 'representative_title' => '']);
        $this->assertSame('', $defaults['salutation'], 'an agreement is not a letter');
        $this->assertSame('', $defaults['closing']);
    }

    public function test_the_other_party_is_kept_on_a_row_of_its_own(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $document = $this->agreement($owner, $this->company());

        $party = Agreements::first($document);
        $this->assertSame('Jonathan Ndamzi Destiny', $party->name);
        $this->assertSame('Full-Stack Engineer', $party->role_title);
        $this->assertSame('jonathan@example.test', $party->email);
        $this->assertSame(LetterCounterparty::PENDING, $party->status);
        $this->assertSame(1, $party->position);
        $this->assertNull($party->token, 'nothing is sent until HQ sends it');
    }

    public function test_more_than_one_signatory_fits_the_same_table(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $document = $this->agreement($owner, $this->company());

        LetterCounterparty::create([
            'document_id' => $document->id, 'position' => 2, 'name' => 'A Second Party',
            'role_title' => 'Guarantor', 'email' => 'second@example.test',
        ]);

        $this->assertCount(2, Agreements::parties($document));
        $this->assertSame(['Jonathan Ndamzi Destiny', 'A Second Party'], Agreements::parties($document)->pluck('name')->all());
    }

    public function test_the_contract_renders_over_several_pages_with_its_schedules(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $this->signatureFor($owner);
        $document = $this->agreement($owner, $this->company());
        Letters::sign($document, $owner);

        $html = LetterPdf::html($document->fresh(), \App\Support\Letters\Letterhead::details($this->company()), final: true);

        // The title block, not a letter's salutation
        $this->assertStringContainsString('Project Contractor Agreement', $html);
        $this->assertStringContainsString($document->reference, $html);
        $this->assertStringNotContainsString('Dear Sir or Madam', $html);

        // Schedule B's pay table survived the cleaner and is drawn as a table
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<th>Instalment</th>', $html);
        $this->assertStringContainsString('End of November 2026, on invoice', $html);

        // Headings are carried with the text under them
        $this->assertStringContainsString('<div class="keep">', $html);

        // Both signature boxes, the counterparty's with a pen line until they sign
        $this->assertStringContainsString('For and on behalf of JBS Praxis Limited', $html);
        $this->assertStringContainsString('Jonathan Ndamzi Destiny', $html);
        $this->assertStringContainsString('sig-blank', $html);

        $pdf = LetterPdf::render($document->fresh(), final: true);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(3, $this->pageCount($pdf), 'the contract runs to several pages');
    }

    public function test_a_multi_page_agreement_carries_a_running_head(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $document = $this->agreement($owner, $this->company());

        $this->assertSame($document->reference, LetterPdf::headerLine($document));

        $letter = Letters::start('formal_letter', null, $this->company(), $owner);
        $this->assertNull(LetterPdf::headerLine($letter), 'every other kind keeps the letterhead exactly as it was');
    }

    public function test_the_draft_screen_writes_the_other_party(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $document = Letters::start('agreement', null, $this->company(), $owner);

        Livewire::test(\App\Livewire\Hq\Letters\DocumentPage::class, ['document' => $document->id])
            ->set('title', 'Project Contractor Agreement')
            ->set('counterparty.name', 'Jonathan Ndamzi Destiny')
            ->set('counterparty.role_title', 'Full-Stack Engineer')
            ->set('counterparty.email', 'jonathan@example.test')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('jonathan@example.test', Agreements::first($document)->email);
    }

    public function test_a_bad_email_address_is_refused_before_anything_is_saved(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $document = Letters::start('agreement', null, $this->company(), $owner);

        Livewire::test(\App\Livewire\Hq\Letters\DocumentPage::class, ['document' => $document->id])
            ->set('counterparty.name', 'Someone')
            ->set('counterparty.email', 'not-an-address')
            ->call('save')
            ->assertHasErrors('counterparty.email');

        $this->assertNull(Agreements::first($document));
    }

    public function test_an_issued_agreement_cannot_have_its_other_party_changed(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $this->signatureFor($owner);
        $document = $this->agreement($owner, $this->company());
        Letters::sign($document, $owner);
        Letters::issue($document->fresh(), $owner);

        $this->expectExceptionMessage('An issued agreement cannot be changed.');
        Agreements::saveCounterparty($document->fresh(), ['name' => 'Somebody Else', 'role_title' => 'x', 'email' => 'x@example.test']);
    }

    public function test_an_agreement_cannot_be_sent_before_it_is_signed_and_issued(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $document = $this->agreement($owner, $this->company());

        $this->assertStringContainsString('Issue the agreement first', (string) Agreements::cannotSend($document));

        $this->signatureFor($owner);
        Letters::sign($document, $owner);
        Letters::issue($document->fresh(), $owner);
        $this->assertNull(Agreements::cannotSend($document->fresh()));
    }

    public function test_an_unsigned_agreement_is_not_sent_for_countersignature(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $document = $this->agreement($owner, $this->company());
        Letters::issue($document->fresh(), $owner);     // issued, but nobody signed it for JBS Praxis

        $this->assertStringContainsString('Sign it for JBS Praxis', (string) Agreements::cannotSend($document->fresh()));
    }

    public function test_a_replacement_carries_the_other_party_over_unsent(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $this->signatureFor($owner);
        $document = $this->agreement($owner, $this->company());
        Letters::sign($document, $owner);
        Letters::issue($document->fresh(), $owner);

        $copy = Letters::replace($document->fresh(), $owner);
        $party = Agreements::first($copy);

        $this->assertSame('Jonathan Ndamzi Destiny', $party->name);
        $this->assertSame(LetterCounterparty::PENDING, $party->status);
        $this->assertNull($party->token, 'the copy gets its own link, never the old one');
    }

    public function test_starting_an_agreement_is_written_to_the_audit_log(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $document = Letters::start('agreement', null, $this->company(), $owner);

        $this->assertDatabaseHas('system_audit_logs', [
            'action' => 'LETTER_CREATED',
            'target_table' => 'letter_documents',
            'target_id' => $document->id,
        ]);
        $this->assertStringContainsString('Agreement', (string) DB::table('system_audit_logs')
            ->where('target_id', $document->id)->value('description'));
    }

    public function test_an_issued_agreement_is_valid_on_the_verify_page(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $this->signatureFor($owner);
        $document = $this->agreement($owner, $this->company());
        Letters::sign($document, $owner);
        Letters::issue($document->fresh(), $owner);

        $this->assertTrue($document->fresh()->isValid());
        $this->assertTrue($document->fresh()->isIssued());
        $this->assertSame(LetterDocument::ISSUED, $document->fresh()->status);
    }

    /** How many pages a PDF has, read from its own page tree without any extension */
    protected function pageCount(string $pdf): int
    {
        preg_match_all('~/Type\s*/Page[^s]~', $pdf, $matches);

        return count($matches[0]);
    }
}
