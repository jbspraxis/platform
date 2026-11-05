<?php

namespace Tests\Feature\CompanyProfile;

use App\Livewire\Hq\Letters\DocumentPage;
use App\Livewire\Hq\Letters\DocumentRegister;
use App\Models\Letters\LetterDocument;
use App\Support\Letters\LetterBody;
use App\Support\Letters\Letterhead;
use App\Support\Letters\LetterPdf;
use App\Support\Letters\Letters;
use App\Support\Letters\LetterTypes;
use App\Support\Hosts;
use Livewire\Livewire;
use App\Models\CompanyProfile\ProfileVersion;
use App\Support\CompanyProfile\ProfileCopy;
use Tests\Feature\Letters\LetterFixtures;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * HQ › Documents › Introduction letter (docs/COMPANY_PROFILE.md section 3): one page introducing JBS Praxis
 * to a company, in English or French, on the letterhead, with its own reference like every other document.
 *
 * The two things that matter and are easy to break: it holds **one page** in both languages, and it carries
 * the recipient's own details.
 *
 * The published profile these read is the one CompanyProfileSeeder publishes into the throwaway database
 * (docs/COMPANY_PROFILE.md section 7), through the engine's own Profile::data(). Nothing is stubbed.
 */
class IntroductionLetterTest extends TestCase
{
    use LetterFixtures, UsesThrowawayDatabase;

    private function hq(string $path): string
    {
        return 'https://' . Hosts::hq() . $path;
    }

    private function letter(string $locale, array $fields = []): LetterDocument
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $document = Letters::start('company_introduction', null, $this->company(), $owner);
        $document->forceFill([
            'addressed_to' => 'Foxtrot Foods Limited (test)',
            'addressed_to_address' => "12 Marina Road\nLagos Island, Lagos\nNigeria",
            'fields' => array_merge((array) $document->fields, ['locale' => $locale], $fields),
        ])->save();

        return $document->fresh();
    }

    /** How many pages a rendered PDF has, counted from its page objects */
    private function pages(string $pdf): int
    {
        return max(
            preg_match_all('#/Type\s*/Page[^s]#', $pdf),
            (int) (preg_match('#/Type\s*/Pages.*?/Count\s+(\d+)#s', $pdf, $m) ? $m[1] : 0),
        );
    }

    // ------------------------------------------------------------------ the template

    public function test_the_template_is_offered_and_runs_with_the_introduction_reference(): void
    {
        $this->actingAs($this->owner());
        $this->company();

        Livewire::test(DocumentRegister::class)->call('choose')->assertSee('Introduction letter');

        $this->assertSame('INT', LetterTypes::code('company_introduction'));
        $this->assertStringStartsWith('JBS/INT/', $this->letter('en')->reference);
    }

    public function test_the_author_picks_the_language_the_contact_and_their_email(): void
    {
        $this->company();
        $owner = $this->owner();
        $this->actingAs($owner);
        $document = Letters::start('company_introduction', null, $this->company(), $owner);

        Livewire::test(DocumentPage::class, ['document' => $document->id])
            ->set('addressedTo', 'Foxtrot Foods Limited (test)')
            ->set('addressedToAddress', "12 Marina Road\nLagos")
            ->set('fields.locale', 'fr')
            ->set('fields.contact_person', 'A. Mbeki')
            ->set('fields.contact_email', 'not-an-email')
            ->call('save')
            ->assertHasErrors('fields.contact_email');

        Livewire::test(DocumentPage::class, ['document' => $document->id])
            ->set('addressedTo', 'Foxtrot Foods Limited (test)')
            ->set('fields.locale', 'de')          // not on offer: it falls back to the first option
            ->set('fields.contact_person', 'A. Mbeki')
            ->set('fields.contact_email', 'a.mbeki@example.test')
            ->call('save')
            ->assertHasNoErrors();

        $saved = $document->fresh();
        $this->assertSame('en', $saved->fields['locale']);
        $this->assertSame('a.mbeki@example.test', $saved->fields['contact_email']);
    }

    // ------------------------------------------------------------------ what it says

    public function test_the_english_letter_carries_the_recipient_and_the_profile_copy(): void
    {
        $document = $this->letter('en', ['contact_person' => 'A. Mbeki', 'contact_email' => 'a.mbeki@example.test']);
        $html = LetterPdf::html($document, Letterhead::details($this->company()));

        $this->assertStringContainsString('Foxtrot Foods Limited (test)', $html);
        $this->assertStringContainsString('12 Marina Road', $html);
        $this->assertStringContainsString('A. Mbeki', $html);
        $this->assertStringContainsString('a.mbeki@example.test', $html);
        $this->assertStringContainsString('Dear A. Mbeki,', $html);
        $this->assertStringContainsString('JBS Praxis', $html);
        $this->assertStringContainsString('Our reference:', $html);
        $this->assertStringContainsString('<html lang="en">', $html);

        // The words come from the company profile, through ProfileCopy
        $copy = ProfileCopy::forLetter('en');
        $this->assertStringContainsString(e($copy['headline']), $html);
        // The descriptor runs on inside the sentence, so its first letter is lowered
        $this->assertStringContainsString(e(\Illuminate\Support\Str::lcfirst(rtrim($copy['descriptor'], '.'))), $html);
        $this->assertStringContainsString(e($copy['build']), $html);
        $this->assertStringContainsString(e($copy['cta']), $html);
        $this->assertStringContainsString(e($copy['cta_url']), $html);
        $this->assertCount(3, $copy['proof']);
        foreach ($copy['proof'] as $line) {
            $this->assertStringContainsString(e($line), $html);
        }
    }

    public function test_the_french_letter_is_french_from_the_reference_to_the_closing(): void
    {
        $document = $this->letter('fr', ['contact_person' => 'A. Mbeki']);
        $html = LetterPdf::html($document, Letterhead::details($this->company()));

        $this->assertSame('fr', LetterBody::locale($document));
        $this->assertStringContainsString('<html lang="fr">', $html);
        $this->assertStringContainsString('Notre référence', $html);
        $this->assertStringContainsString('Madame, Monsieur,', $html);
        $this->assertStringContainsString('Trois systèmes qui tournent', $html);
        $this->assertStringContainsString('Foxtrot Foods Limited (test)', $html);

        $copy = ProfileCopy::forLetter('fr');
        $this->assertStringContainsString(e($copy['headline']), $html);
        $this->assertStringContainsString(e($copy['build']), $html);
        $this->assertStringNotContainsString('Yours faithfully', $html);
        $this->assertStringNotContainsString('I am writing on behalf', $html);
    }

    public function test_the_banned_words_stay_out_of_the_letter_in_both_languages(): void
    {
        foreach (['en', 'fr'] as $locale) {
            $html = strtolower(LetterPdf::html($this->letter($locale), Letterhead::details($this->company())));
            foreach (['squad', 'leverage', 'cutting-edge', 'solutions provider', 'one-stop shop'] as $banned) {
                $this->assertStringNotContainsString($banned, $html, $banned . ' in the ' . $locale . ' letter');
            }
        }
    }

    // ------------------------------------------------------------------ one page, both languages

    public function test_it_holds_one_page_in_english_and_in_french(): void
    {
        foreach (['en', 'fr'] as $locale) {
            $document = $this->letter($locale, ['contact_person' => 'A. Mbeki', 'contact_email' => 'a.mbeki@example.test']);
            $pdf = LetterPdf::render($document, final: true);

            $this->assertStringStartsWith('%PDF-', $pdf);
            $this->assertSame(1, $this->pages($pdf), 'the ' . $locale . ' introduction letter is one page');
        }
    }

    public function test_the_signature_line_of_an_unsigned_letter_follows_its_language(): void
    {
        // A company with no legal representative on record: the line under the pen is the fallback one
        $french = $this->letter('fr');
        $english = $this->letter('en');
        \Illuminate\Support\Facades\DB::table('mdm_legal_entities')->where('region_code', 'NG')
            ->update(['legal_representative_name' => '', 'legal_representative_title' => '']);
        $details = Letterhead::details(Letterhead::company($french->legal_entity_id));

        $fr = LetterPdf::html($french->fresh(), $details);
        $this->assertStringContainsString('Pour le compte de', $fr);
        $this->assertStringContainsString('Directeur', $fr);
        $this->assertStringNotContainsString('For and on behalf of', $fr);

        $this->assertStringContainsString('For and on behalf of', LetterPdf::html($english->fresh(), $details));
    }

    public function test_the_copy_is_the_company_profiles_own_and_our_products_are_not_proof(): void
    {
        $en = ProfileCopy::forLetter('en');
        $fr = ProfileCopy::forLetter('fr');

        $this->assertSame('Run everything. Ask anything.', $en['headline']);
        $this->assertSame('Pilotez tout. Interrogez tout.', $fr['headline']);

        // The point of view's closing paragraph, without the sentence that needs the two before it
        $this->assertStringStartsWith('We design how the organisation should run', $en['build']);
        $this->assertStringNotContainsString('We close it differently', $en['build']);
        $this->assertStringNotContainsString('Nous le comblons autrement', $fr['build']);

        foreach ([$en, $fr] as $copy) {
            $lines = implode(' | ', $copy['proof']);
            // Our own products are not proof of what we do for a client
            $this->assertStringNotContainsString('NFELIA', $lines);
            // Clients are named exactly as the profile names them (the naming rules live there)
            $this->assertStringNotContainsString('Fidson', $lines);
        }

        $this->assertStringContainsString('Smart Logistics and Services', $en['proof'][0]);
        $this->assertStringContainsString('A leading pharmaceutical manufacturer', $en['proof'][2]);
        $this->assertSame('JBS Praxis', $en['closing']);
    }

    /**
     * Until a profile is published — which is where this branch stands until #60 is merged — the letter still
     * writes, on the house copy, and simply leaves the proof block out. It must never name a client of its own
     * accord: a client's work is the profile's to publish.
     */
    public function test_with_no_published_profile_the_letter_writes_without_claiming_any_work(): void
    {
        // Nothing published: the seeded version goes, and Profile::data() raises MissingProfile
        ProfileVersion::query()->delete();

        foreach (['en', 'fr'] as $locale) {
            $copy = ProfileCopy::forLetter($locale);
            $this->assertFalse($copy['published'], $locale . ': nothing is published');
            $this->assertSame([], $copy['proof'], $locale . ': no proof lines');
            $this->assertNotSame('', $copy['headline']);

            $document = $this->letter($locale);
            $html = LetterPdf::html($document, Letterhead::details($this->company()));
            foreach (['Smart Logistics', 'Pixie Girl', 'La Petite Cour', 'pharmaceutical', 'pharmaceutique'] as $client) {
                $this->assertStringNotContainsString($client, $html, $locale . ': ' . $client . ' is not ours to claim here');
            }

            $this->assertSame(1, $this->pages(LetterPdf::render($document, final: true)), $locale . ': still one page');
        }
    }

    /**
     * The one page is held by the length of what goes on it, so the budget is checked here rather than
     * discovered when somebody rewrites the profile copy. If a new paragraph breaks this, shorten it or
     * accept a second page on purpose (docs/COMPANY_PROFILE.md section 3).
     */
    public function test_the_letter_copy_stays_inside_the_one_page_budget(): void
    {
        foreach (['en', 'fr'] as $locale) {
            $copy = ProfileCopy::forLetter($locale);

            $this->assertLessThanOrEqual(90, mb_strlen($copy['headline']), $locale . ': the headline');
            $this->assertLessThanOrEqual(90, mb_strlen($copy['descriptor']), $locale . ': the descriptor');
            $this->assertLessThanOrEqual(320, mb_strlen($copy['build']), $locale . ': what we build');
            $this->assertCount(3, $copy['proof'], $locale . ': three proof lines');
            foreach ($copy['proof'] as $line) {
                $this->assertLessThanOrEqual(220, mb_strlen($line), $locale . ': a proof line');
            }
            $this->assertLessThanOrEqual(120, mb_strlen($copy['cta']), $locale . ': the call to action');
        }
    }

    public function test_it_is_issued_downloaded_and_checked_like_any_other_document(): void
    {
        $owner = $this->owner();
        $document = $this->letter('fr');
        $document->forceFill(['show_qr' => true])->save();

        Letters::issue($document->fresh(), $owner);
        $issued = $document->fresh();

        $this->assertSame(LetterDocument::ISSUED, $issued->status);
        $this->assertSame(hash('sha256', (string) Letters::file($issued)), $issued->file_sha256);
        $this->assertNotNull($issued->verify_token);

        $this->actingAs($owner)->get($this->hq('/documents/' . $issued->id . '/pdf'))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
