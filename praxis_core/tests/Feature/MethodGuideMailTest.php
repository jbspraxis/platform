<?php

namespace Tests\Feature;

use App\Mail\EnquiryReceived;
use App\Mail\PraxisFrameworkDispatch;
use Illuminate\Mail\Mailables\Attachment;
use Tests\TestCase;

/**
 * The Praxis Method guide that the website's guide form emails (PraxisFrameworkDispatch): the PDF edition and the
 * attachment's name follow the mail's language, English for any other, and the files are the tracked editions in
 * resources/pdf (built by brand/build/build_method.js). No database: the mail is only built and rendered.
 */
class MethodGuideMailTest extends TestCase
{
    public function test_the_english_mail_attaches_the_english_guide(): void
    {
        $mail = (new PraxisFrameworkDispatch())->locale('en');

        $mail->assertHasAttachment($this->guide('the-praxis-method-en.pdf', 'JBS-Praxis-Method.pdf'));
        $this->assertFalse($mail->hasAttachment($this->guide('the-praxis-method-fr.pdf', 'JBS-Praxis-Methode.pdf')));
        $this->assertStringContainsString('The Praxis Method is attached to this email as a PDF.', $mail->renderParts()['html']);
    }

    public function test_the_french_mail_attaches_the_french_guide(): void
    {
        $mail = (new PraxisFrameworkDispatch())->locale('fr');

        $mail->assertHasAttachment($this->guide('the-praxis-method-fr.pdf', 'JBS-Praxis-Methode.pdf'));
        $this->assertFalse($mail->hasAttachment($this->guide('the-praxis-method-en.pdf', 'JBS-Praxis-Method.pdf')));

        // The email no longer warns that the guide is in English only
        $parts = (new PraxisFrameworkDispatch())->locale('fr')->renderParts();
        $this->assertStringContainsString('Vous trouverez en pièce jointe la méthode Praxis, au format PDF.', $parts['html']);
        $this->assertStringNotContainsString('anglais', $parts['html'] . $parts['text']);
    }

    public function test_the_booking_button_opens_the_booking_page_in_the_mails_language(): void
    {
        // The guide email and the contact form's copy both end on Book a free audit and demo
        foreach ([new PraxisFrameworkDispatch(), new PraxisFrameworkDispatch(), new EnquiryReceived('Ada'), new EnquiryReceived('Ada')] as $i => $mail) {
            $french = $i % 2 === 1;
            $html = $mail->locale($french ? 'fr' : 'en')->renderParts()['html'];

            $this->assertStringContainsString($french ? '/fr/schedule"' : '/schedule"', $html, get_class($mail));
            if (! $french) {
                $this->assertStringNotContainsString('/fr/schedule', $html, get_class($mail));
            }
        }
    }

    public function test_any_other_language_gets_the_english_guide(): void
    {
        $this->assertSame(PraxisFrameworkDispatch::EDITIONS['en'], (new PraxisFrameworkDispatch())->edition());
        $this->assertSame(PraxisFrameworkDispatch::EDITIONS['en'], (new PraxisFrameworkDispatch())->locale('de')->edition());
    }

    public function test_each_edition_is_a_pdf_in_its_own_language(): void
    {
        foreach (['en' => 'en-GB', 'fr' => 'fr'] as $locale => $language) {
            $pdf = file_get_contents(resource_path('pdf/' . PraxisFrameworkDispatch::EDITIONS[$locale]['file']));

            $this->assertStringStartsWith('%PDF-', $pdf);
            $this->assertStringContainsString("/Lang ({$language})", $pdf, "the {$locale} edition is tagged in its language");
            $this->assertLessThan(2 * 1024 * 1024, strlen($pdf), 'small enough to email');
        }
    }

    private function guide(string $file, string $name): Attachment
    {
        return Attachment::fromPath(resource_path("pdf/{$file}"))->as($name)->withMime('application/pdf');
    }
}
