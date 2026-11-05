<?php

namespace Tests\Feature\CompanyProfile;

use App\Models\CompanyProfile\ClientLogo;
use App\Support\CompanyProfile\Deck\Blueprint;
use App\Support\CompanyProfile\Deck\Zip;
use App\Support\CompanyProfile\LogoLibrary;
use App\Support\CompanyProfile\Profile;
use App\Support\CompanyProfile\Typography;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * The "Download deck" button, end to end: HQ's route, the permission in front of it, the published
 * version behind it, and the bytes a client receives (docs/company-profile/DECK.md).
 *
 * DeckRendererTest proves the file is what the design and the contract say it is. This proves the other
 * half — that HQ hands it out, in both languages, only to someone who may see it, and never hands out a
 * draft to someone who may only view.
 */
class ProfileDownloadDeckTest extends TestCase
{
    use LogoFixtures, ProfileFixtures, UsesThrowawayDatabase;

    public function test_the_wall_carries_the_logos_the_library_ticks_for_the_deck(): void
    {
        ClientLogo::query()->delete();

        // Two for the deck, one kept off it: the deck asks the library for its own set, not the book's
        LogoLibrary::save(['name' => 'Deck One (test)'], ['print' => $this->jpeg(), 'png' => $this->png()]);
        LogoLibrary::save(['name' => 'Deck Two (test)'], ['print' => $this->jpeg(), 'png' => $this->png()]);
        LogoLibrary::save(['name' => 'Book Only (test)', 'on_deck' => false], ['print' => $this->jpeg(), 'png' => $this->png()]);

        $this->assertCount(2, LogoLibrary::shownOn('deck'));

        $response = $this->actingAs($this->person(['view']))->get($this->hqUrl('/company-profile/deck?locale=en'));
        $response->assertOk();

        $entries = Zip::entries((string) $response->getContent());
        $wall = $entries['ppt/slides/slide22.xml'];

        $this->assertSame(2, substr_count($wall, '<p:pic>'), 'the wall is not drawing the library\'s logos');
        $this->assertStringNotContainsString('Book Only (test)', $wall, 'a logo kept off the deck reached it');
    }

    public function test_the_download_deck_button_sends_a_twenty_four_slide_pptx_in_both_languages(): void
    {
        foreach (['en' => 'EN', 'fr' => 'FR'] as $locale => $suffix) {
            $response = $this->actingAs($this->person(['view']))
                ->get($this->hqUrl("/company-profile/deck?locale={$locale}"));

            $response->assertOk();
            $response->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            );

            // The filename follows the PDF's: one profile in two forms, not two unrelated things
            $disposition = (string) $response->headers->get('content-disposition');
            $this->assertStringContainsString('attachment;', $disposition, $locale);
            $this->assertStringContainsString('JBS Praxis company profile ', $disposition, $locale);
            $this->assertStringContainsString((string) ($this->published()->reference ?? 'JBS-CP'), $disposition, $locale);
            $this->assertStringContainsString($suffix.'.pptx"', $disposition, $locale);

            $pptx = (string) $response->getContent();
            $entries = Zip::entries($pptx);

            $this->assertArrayHasKey('[Content_Types].xml', $entries, "{$locale} is not an Office package");
            for ($i = 1; $i <= Blueprint::SLIDES; $i++) {
                $this->assertArrayHasKey("ppt/slides/slide{$i}.xml", $entries, "{$locale} is missing slide {$i}");
            }
            $this->assertArrayNotHasKey('ppt/slides/slide'.(Blueprint::SLIDES + 1).'.xml', $entries, "{$locale} has an extra slide");
        }
    }

    public function test_the_deck_carries_the_published_copy(): void
    {
        $response = $this->actingAs($this->person(['view']))->get($this->hqUrl('/company-profile/deck?locale=en'));
        $response->assertOk();

        $entries = Zip::entries((string) $response->getContent());
        $text = '';
        for ($i = 1; $i <= Blueprint::SLIDES; $i++) {
            $text .= $entries["ppt/slides/slide{$i}.xml"];
        }

        // Compared after Typography, which binds the last two words of a line with a no-break space before
        // anything is drawn: the published string and the drawn string differ by exactly that character.
        $published = Profile::data('en');
        $descriptor = Typography::text((string) $published['cover']['descriptor'], 'en');
        $this->assertStringContainsString(
            htmlspecialchars($descriptor, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            $text,
            'the deck is not carrying the published descriptor',
        );
        $this->assertStringContainsString((string) $published['meta']['reference'], $text, 'the deck carries no reference');
    }

    public function test_the_deck_is_behind_the_same_permission_as_the_pdf(): void
    {
        $nobody = $this->person([]);

        $this->actingAs($nobody)->get($this->hqUrl('/company-profile/download?locale=en'))->assertForbidden();
        $this->actingAs($nobody)->get($this->hqUrl('/company-profile/deck?locale=en'))->assertForbidden();
    }

    public function test_an_unknown_language_is_not_a_deck(): void
    {
        $this->actingAs($this->person(['view']))
            ->get($this->hqUrl('/company-profile/deck?locale=de'))
            ->assertNotFound();
    }

    public function test_only_someone_who_may_edit_or_approve_can_download_a_draft_as_slides(): void
    {
        $draft = Profile::withoutAudit(fn () => Profile::startDraft('A draft for the deck test'));

        $this->actingAs($this->person(['view']))
            ->get($this->hqUrl("/company-profile/deck?locale=en&version={$draft->number}"))
            ->assertForbidden();

        $this->actingAs($this->person(['view', 'edit']))
            ->get($this->hqUrl("/company-profile/deck?locale=en&version={$draft->number}"))
            ->assertOk();
    }
}
