<?php

namespace Tests\Feature;

use App\Support\Hosts;
use Livewire\Mechanisms\FrontendAssets\FrontendAssets;
use Tests\Feature\Mail\MailTestKit;
use Tests\TestCase;

/**
 * Gzip for what PHP answers with text (App\Http\Middleware\CompressResponses): pages, Livewire updates and the
 * scripts PHP serves itself, only for browsers that ask, and only after Livewire has written its assets into the page.
 */
class CompressResponsesTest extends TestCase
{
    use MailTestKit;

    private const GZIP = ['Accept-Encoding' => 'gzip, deflate, br'];

    public function test_a_page_is_compressed_for_a_browser_that_asks_and_left_alone_otherwise(): void
    {
        $page = $this->get('https://' . Hosts::hq() . '/login', self::GZIP)->assertOk();
        $this->assertSame('gzip', $page->headers->get('Content-Encoding'));
        $this->assertStringContainsString('Accept-Encoding', (string) $page->headers->get('Vary'));
        $this->assertSame(strlen($page->getContent()), (int) $page->headers->get('Content-Length'));
        $html = gzdecode($page->getContent());
        $this->assertStringContainsString('</html>', $html);
        $this->assertStringContainsString('livewire', $html);

        $plain = $this->get('https://' . Hosts::hq() . '/login')->assertOk();
        $this->assertNull($plain->headers->get('Content-Encoding'));
        $this->assertStringContainsString('</html>', $plain->getContent());

        $refused = $this->get('https://' . Hosts::hq() . '/login', ['Accept-Encoding' => 'gzip;q=0, identity'])->assertOk();
        $this->assertNull($refused->headers->get('Content-Encoding'));
    }

    public function test_livewires_script_is_compressed_and_arrives_whole(): void
    {
        $uri = '/' . ltrim(app(FrontendAssets::class)->javaScriptRoute->uri, '/');
        $plain = $this->get('https://' . Hosts::hq() . $uri)->assertOk();
        $zipped = $this->get('https://' . Hosts::hq() . $uri, self::GZIP)->assertOk();

        $this->assertSame('gzip', $zipped->headers->get('Content-Encoding'));
        $body = $plain->baseResponse instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
            ? file_get_contents($plain->baseResponse->getFile()->getPathname()) : $plain->getContent();
        $this->assertSame($body, gzdecode($zipped->getContent()));
        $this->assertLessThan(strlen($body) / 2, strlen($zipped->getContent()));
        $this->assertStringContainsString('max-age', (string) $zipped->headers->get('Cache-Control'), 'Its long cache is kept');
    }

    public function test_assets_livewire_writes_into_the_page_are_still_there_once_compressed(): void
    {
        $this->setUpMail();
        $this->openMailToEveryone();
        $owner = $this->staff('own', ['owner']);
        $this->mailbox('intelligence@jbspraxis.com');

        $page = $this->actingAs($owner)->get('https://' . Hosts::hq() . '/mail', self::GZIP)->assertOk();
        $this->assertSame('gzip', $page->headers->get('Content-Encoding'));
        $html = gzdecode($page->getContent());
        $head = substr($html, 0, (int) strpos($html, '</head>'));
        $this->assertStringContainsString('HQ → Mail (PR 2)', $head, "The Mail page's styles (@assets) are in the head");
    }
}
