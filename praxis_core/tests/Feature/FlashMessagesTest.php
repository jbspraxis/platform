<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Flash messages in HQ and the Client Portal all go through <x-flash> (resources/views/components/flash.blade.php):
 * the brand alert, one icon and role per tone, and a toast that can be closed in English or French.
 */
class FlashMessagesTest extends TestCase
{
    private function render(string $blade, array $flash): string
    {
        foreach ($flash as $key => $text) {
            session()->flash($key, $text);
        }

        return Blade::render($blade);
    }

    public function test_the_standard_messages_use_the_brand_alert(): void
    {
        $html = $this->render('<x-flash class="mb-6" />', ['success' => 'Invoice INV-0042 cancelled.', 'error' => 'We couldn’t find that invoice. <b>Refresh</b> the page.']);

        $this->assertStringContainsString('<div class="alert alert-success mb-6" role="status"', $html);
        $this->assertStringContainsString('<i data-lucide="circle-check"></i>', $html);
        $this->assertStringContainsString('<span>Invoice INV-0042 cancelled.</span>', $html);
        $this->assertStringContainsString('<div class="alert alert-danger mb-6" role="alert"', $html);
        $this->assertStringContainsString('<i data-lucide="circle-alert"></i>', $html);
        $this->assertStringContainsString('&lt;b&gt;Refresh&lt;/b&gt;', $html, 'messages are escaped');
        $this->assertStringNotContainsString('alert-close', $html, 'inline messages have no close button');
    }

    public function test_nothing_is_shown_without_a_message(): void
    {
        $this->assertSame('', trim($this->render('<x-flash />', [])));
    }

    public function test_named_keys_take_their_tone_from_the_name_or_from_tones(): void
    {
        $html = $this->render(
            '<x-flash :keys="[\'team_error\', \'qa_success\', \'message\', \'error\']" :tones="[\'error\' => \'warning\']" />',
            ['team_error' => 'That role isn’t available.', 'qa_success' => 'Issue sent.', 'message' => 'Receipt uploaded.', 'error' => '3 invoices were marked overdue.', 'success' => 'Not asked for.']
        );

        $this->assertStringContainsString('<div class="alert alert-danger" role="alert" wire:key="flash-team_error"', $html);
        $this->assertStringContainsString('<div class="alert alert-success" role="status" wire:key="flash-qa_success"', $html);
        $this->assertStringContainsString('<div class="alert alert-success" role="status" wire:key="flash-message"', $html);
        $this->assertStringContainsString('<div class="alert alert-warning" role="alert" wire:key="flash-error"', $html);
        $this->assertStringContainsString('<i data-lucide="triangle-alert"></i>', $html);
        $this->assertStringNotContainsString('Not asked for.', $html);
    }

    public function test_a_toast_floats_closes_itself_when_it_is_good_news_and_can_be_closed_in_french(): void
    {
        app()->setLocale('fr');
        $html = $this->render('<x-flash toast :keys="[\'global_success\', \'team_error\']" />', ['global_success' => 'Tâche approuvée.', 'team_error' => 'Ce rôle n’est pas disponible.']);

        $this->assertStringContainsString('class="alert alert-success alert-toast"', $html);
        $this->assertStringContainsString('x-init="setTimeout(() => show = false, 6000)"', $html);
        $this->assertStringContainsString('aria-label="Fermer le message"', $html);
        $this->assertStringContainsString('.alert-toast { position: fixed;', $html);
        // An error stays until it is closed
        $error = substr($html, strpos($html, 'flash-team_error'));
        $this->assertStringNotContainsString('setTimeout', strstr($error, '</div>', true));

        app()->setLocale('en');
        $this->assertSame('Close message', __('flash.close'));
    }

    public function test_hq_and_portal_screens_no_longer_hand_write_flash_messages(): void
    {
        $dirs = ['livewire/hq', 'livewire/portal', 'livewire/client-os', 'livewire/tracker', 'components/portal', 'components/layouts'];
        $offenders = [];
        foreach ($dirs as $dir) {
            foreach (File::allFiles(resource_path("views/{$dir}")) as $file) {
                // HQ Mail keeps its own message bar (with Undo), built on the same alert
                if (str_contains($file->getPathname(), 'hq' . DIRECTORY_SEPARATOR . 'mail')) {
                    continue;
                }
                if (preg_match('/session\(\s*[\'"]|session\(\)->(has|get)\(/', $file->getContents())) {
                    $offenders[] = $file->getRelativePathname();
                }
            }
        }

        $this->assertSame([], $offenders, 'show flash messages with <x-flash>');
    }
}
