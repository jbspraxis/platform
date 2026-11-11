<?php

namespace Tests\Feature;

use App\Support\Flash\Toasts;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every confirmation and error in HQ and the Client Portal arrives as a toast in the bottom-right corner, over the
 * page, so nobody has to scroll to find out whether what they clicked worked — and nobody clicks twice because
 * nothing seemed to happen. The host (<x-flash>, resources/views/components/flash.blade.php) sits in the layouts,
 * so no screen has to ask for it. A message flashed by a Livewire action gets there through
 * App\Support\Flash\ToastBridge, because a Livewire update never re-renders the layout.
 */
class FlashMessagesTest extends TestCase
{
    private function render(array $flash): string
    {
        foreach ($flash as $key => $text) {
            session()->flash($key, $text);
        }

        return Blade::render('<x-flash />');
    }

    public function test_a_message_waiting_on_a_page_load_is_drawn_as_a_toast(): void
    {
        $html = $this->render(['success' => 'Invoice INV-0042 cancelled.']);

        $this->assertStringContainsString('<div class="toast-host" data-toast-host', $html);
        $this->assertStringContainsString('class="alert alert-success alert-toast is-in" role="status" data-toast', $html);
        $this->assertStringContainsString('<i data-lucide="circle-check"></i>', $html);
        $this->assertStringContainsString('<span>Invoice INV-0042 cancelled.</span>', $html);
        // is-in from the start, so the message is readable before the script runs
        $this->assertStringContainsString('is-in', $html);
        $this->assertStringContainsString('data-toast-close', $html);
    }

    public function test_good_news_closes_itself_and_a_problem_stays_until_it_is_closed(): void
    {
        foreach (['success' => 'Saved.', 'info' => 'The invoice decides the date.'] as $key => $text) {
            $html = $this->render([$key => $text]);
            $this->assertStringContainsString('role="status"', $html);
            $this->assertStringContainsString('data-toast-auto', $html, "a {$key} closes itself");
        }

        foreach (['warning' => 'Check this.', 'error' => 'That did not work.'] as $key => $text) {
            $html = $this->render([$key => $text]);
            $this->assertStringContainsString('role="alert"', $html);
            $this->assertStringNotContainsString('data-toast-auto', $html, "a {$key} stays until it is closed");
        }
    }

    public function test_the_host_carries_the_four_second_clock_and_the_tones_for_the_script(): void
    {
        $html = $this->render([]);

        $this->assertStringContainsString('&quot;autoCloseMs&quot;:4000', $html);
        $this->assertStringContainsString('&quot;max&quot;:4', $html);
        $this->assertStringContainsString('Close message', $html);
        $this->assertSame(4000, Toasts::AUTO_CLOSE_MS);
    }

    public function test_the_host_is_there_with_nothing_in_it_when_there_is_no_message(): void
    {
        $html = $this->render([]);

        $this->assertStringContainsString('data-toast-host', $html);
        $this->assertStringNotContainsString('alert-toast', $html);
    }

    public function test_a_message_is_escaped(): void
    {
        $html = $this->render(['error' => 'We couldn’t find that invoice. <b>Refresh</b> the page.']);

        $this->assertStringContainsString('&lt;b&gt;Refresh&lt;/b&gt;', $html);
    }

    public function test_a_key_takes_its_tone_from_its_name(): void
    {
        $this->assertSame('error', Toasts::toneFor('team_error'));
        $this->assertSame('success', Toasts::toneFor('qa_success'));
        $this->assertSame('info', Toasts::toneFor('ai_info'));
        $this->assertSame('warning', Toasts::toneFor('lockout_warning'));
        // A name that says nothing reads as good news, as it always has
        $this->assertSame('success', Toasts::toneFor('message'));
        $this->assertSame('success', Toasts::toneFor('message-17'));

        $html = $this->render(['team_error' => 'That role isn’t available.', 'qa_success' => 'Issue sent.']);
        $this->assertStringContainsString('alert alert-danger alert-toast', $html);
        $this->assertStringContainsString('alert alert-success alert-toast', $html);
    }

    public function test_only_our_own_keys_are_shown(): void
    {
        session()->flash('url.intended', 'https://example.test/somewhere');
        session()->flash('status', 'A Laravel key that is not one of ours.');

        $html = $this->render(['success' => 'Saved.']);

        $this->assertStringContainsString('Saved.', $html);
        $this->assertStringNotContainsString('example.test', $html);
        $this->assertStringNotContainsString('not one of ours', $html);
    }

    public function test_a_message_is_shown_once_and_then_cleared(): void
    {
        session()->flash('success', 'Saved.');

        $this->assertSame('Saved.', Toasts::pull()[0]['message']);
        $this->assertSame([], Toasts::pull(), 'taking a message clears it, so it cannot show twice');
        $this->assertNull(session('success'));
    }

    public function test_the_close_button_is_translated(): void
    {
        app()->setLocale('fr');
        $html = $this->render(['global_success' => 'Tâche approuvée.']);
        $this->assertStringContainsString('aria-label="Fermer le message"', $html);

        app()->setLocale('en');
        $this->assertSame('Close message', __('flash.close'));
    }

    public function test_every_layout_carries_the_toast_host_so_no_screen_can_forget_it(): void
    {
        $layouts = [
            'components/layouts/hq.blade.php',
            'components/layouts/portal.blade.php',
            'components/layouts/auth.blade.php',
            'layouts/portal.blade.php',
            'layouts/client-light.blade.php',
        ];

        foreach ($layouts as $layout) {
            $this->assertStringContainsString('<x-flash />', File::get(resource_path("views/{$layout}")), "{$layout} must show messages");
        }
    }

    public function test_screens_no_longer_place_the_messages_themselves(): void
    {
        $offenders = [];

        foreach (['livewire', 'components/portal'] as $dir) {
            foreach (File::allFiles(resource_path("views/{$dir}")) as $file) {
                if (str_contains($file->getContents(), '<x-flash')) {
                    $offenders[] = $file->getRelativePathname();
                }
            }
        }

        $this->assertSame([], $offenders, 'the toast host sits in the layout; screens just flash the message');
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

    // ---- The bridge: a Livewire update never re-renders the layout, so the message is dispatched instead ----

    public function test_a_flash_from_a_livewire_action_arrives_as_a_toast_event(): void
    {
        Livewire::test(FlashingComponent::class)
            ->call('save')
            ->assertDispatched('praxis-toast', message: 'Saved.', tone: 'success');

        $this->assertNull(session('success'), 'the message left the session with the event');
    }

    public function test_the_tone_travels_with_the_event(): void
    {
        Livewire::test(FlashingComponent::class)
            ->call('fail')
            ->assertDispatched('praxis-toast', message: 'That did not work.', tone: 'error');
    }

    public function test_a_message_for_the_next_page_is_left_alone_when_the_action_redirects(): void
    {
        Livewire::test(FlashingComponent::class)
            ->call('saveAndLeave')
            ->assertRedirect('/somewhere')
            ->assertNotDispatched('praxis-toast');

        $this->assertSame('Saved and left.', session('success'), 'the page being opened shows it instead');
    }

    public function test_an_action_that_says_nothing_dispatches_nothing(): void
    {
        Livewire::test(FlashingComponent::class)
            ->call('quiet')
            ->assertNotDispatched('praxis-toast');
    }

    public function test_the_bridge_works_in_a_fresh_process_as_on_the_live_site(): void
    {
        // Livewire wires component hooks once, when its own provider boots. In this process an earlier test may already
        // have registered the bridge; a live request starts with nothing, so the toast test runs here in a PHP of its own,
        // as the only test (it failed this way while the bridge was registered in AppServiceProvider::boot()).
        $run = \Illuminate\Support\Facades\Process::path(base_path())->timeout(120)
            ->env(['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'])
            ->run([PHP_BINARY, 'vendor/bin/phpunit', '--filter', 'test_a_flash_from_a_livewire_action_arrives_as_a_toast_event', 'tests/Feature/FlashMessagesTest.php']);

        $this->assertTrue($run->successful(), $run->output() . $run->errorOutput());
        $this->assertStringContainsString('OK (1 test', $run->output());
    }
}

/** A stand-in for any HQ or Client Portal screen: it flashes, and the bridge does the rest. */
class FlashingComponent extends Component
{
    public function save(): void
    {
        session()->flash('success', 'Saved.');
    }

    public function fail(): void
    {
        session()->flash('error', 'That did not work.');
    }

    public function saveAndLeave(): void
    {
        session()->flash('success', 'Saved and left.');
        $this->redirect('/somewhere');
    }

    public function quiet(): void
    {
        // nothing to say
    }

    public function render()
    {
        return '<div>A screen</div>';
    }
}
