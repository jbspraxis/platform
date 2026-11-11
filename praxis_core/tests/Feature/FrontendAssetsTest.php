<?php

namespace Tests\Feature;

use App\Livewire\Hq\Account\MyProfile;
use App\Livewire\Hq\Import\ImportDialog;
use App\Support\Hosts;
use App\Support\SignIn\SignIn;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * What the browser has to fetch before HQ and the Client Portal can show a page, and what each Livewire update
 * carries (11 November 2026). The page tests read the Vite manifest the build writes, as in CI.
 */
class FrontendAssetsTest extends TestCase
{
    use UsesThrowawayDatabase;

    private const UI_FONT = 'node_modules/@fontsource-variable/plus-jakarta-sans/files/plus-jakarta-sans-latin-wght-normal.woff2';

    /** A built file's name, from the Vite manifest the build wrote */
    private function built(string $source): string
    {
        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);

        return $manifest[$source]['file'];
    }

    /** The compiled app.css, as the build wrote it */
    private function compiledCss(): string
    {
        return (string) file_get_contents(public_path('build/' . $this->built('resources/css/app.css')));
    }

    public function test_the_ui_font_starts_downloading_with_the_stylesheet(): void
    {
        $font = '/build/' . $this->built(self::UI_FONT);

        foreach ([Hosts::hq(), Hosts::portal()] as $host) {
            $html = $this->get('https://' . $host . '/login')->assertOk()->getContent();
            $preload = '<link rel="preload" as="font" type="font/woff2" crossorigin href="https://' . $host . $font . '">';
            $this->assertStringContainsString($preload, $html, $host);
            $this->assertLessThan(strpos($html, 'rel="stylesheet"'), strpos($html, $preload), 'ahead of the stylesheet');
        }

        // The very file app.css asks for, so the browser uses the preloaded copy
        $this->assertStringContainsString('url(' . $font . ')', $this->compiledCss());
    }

    public function test_the_app_styles_come_with_app_css_not_as_a_second_stylesheet(): void
    {
        $html = $this->get('https://' . Hosts::hq() . '/login')->assertOk()->getContent();
        $this->assertStringNotContainsString('hq-pwa.css', $html);
        $this->assertSame(1, substr_count($html, 'rel="stylesheet"'), 'one stylesheet before the page can show');

        // Imported ahead of app.css's own rules, the order it had as its own file
        $source = (string) file_get_contents(resource_path('css/app.css'));
        $import = strpos($source, "@import '../pwa/hq-pwa.css';");
        $this->assertNotFalse($import);
        $this->assertLessThan(strpos($source, '@theme'), $import);

        $css = $this->compiledCss();
        foreach (['--hqp-safe-top:', '.hqp-cards', '.hqp-panel', 'html .auth-page'] as $rule) {
            $this->assertStringContainsString($rule, $css);
        }
    }

    public function test_the_documents_script_is_kept_for_a_year_at_each_version(): void
    {
        // Each page asks for it with the file's time, so a changed file has a new address
        foreach (['livewire/hq/letters/document', 'livewire/hq/letters/signature', 'livewire/portal/agreement-signing'] as $view) {
            $this->assertStringContainsString("['v' => filemtime(resource_path('letters/documents.js'))]", file_get_contents(resource_path("views/{$view}.blade.php")), $view);
        }

        $owner = $this->staff('owner', ['owner']);
        SignIn::markFresh();
        $hq = $this->actingAs($owner)->get('https://' . Hosts::hq() . '/documents/editor.js?v=1')->assertOk();
        $portal = $this->get('https://' . Hosts::portal() . '/agreements-signature.js?v=1')->assertOk();

        foreach ([$hq, $portal] as $response) {
            $this->assertStringStartsWith('application/javascript', $response->headers->get('Content-Type'));
            $this->assertTrue($response->headers->hasCacheControlDirective('immutable'));
            $this->assertSame('31536000', $response->headers->getCacheControlDirective('max-age'));
        }
    }

    public function test_the_composer_script_does_not_hold_up_the_first_paint(): void
    {
        $view = (string) file_get_contents(resource_path('views/livewire/hq/mail/composer.blade.php'));
        $this->assertMatchesRegularExpression("~@assets\\s*(\\{\\{--.*?--\\}\\}\\s*)?<script src=\"\\{\\{ route\\('hq\\.mail\\.compose\\.script'[^\"]*\" defer></script>\\s*@endassets~s", $view);
    }

    public function test_static_styles_are_not_sent_again_on_every_update(): void
    {
        $me = $this->staff('sales', ['sales']);

        // My profile: the styles go to <head> once with the page; an update's HTML no longer carries them
        $profile = Livewire::actingAs($me)->test(MyProfile::class)
            ->set('mobile', '+234 801 234 5678')->call('saveNumbers')->assertSee('Phone numbers saved');
        $this->assertStringNotContainsString('<style>', $profile->html());
        $this->assertStringNotContainsString('.mp-cards', $profile->html());

        // The import dialog: the same, from the moment it opens
        $this->actingAs($me);
        $dialog = Livewire::test(ImportDialog::class, ['schema' => 'leads'])->call('show')->assertSee('Download the template');
        $this->assertStringNotContainsString('<style>', $dialog->html());
        $dialog->set('filter', 'ready');
        $this->assertStringNotContainsString('.im-steps', $dialog->html());
    }
}
