<?php

namespace Tests\Feature;

use App\Support\Monitor\ErrorMonitor;
use App\Support\Monitor\LogScanner;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * System health reading laravel.log (owner's error export, 30 Oct 2026): an exception logged for a signed-in person
 * ({"userId":"…","exception":…}) is one exception with its file, line and person, not a second plain error whose
 * text (the user id) set off unrelated hints.
 */
class MonitorLogScannerTest extends TestCase
{
    use UsesThrowawayDatabase;

    public function test_an_exception_logged_for_a_signed_in_person(): void
    {
        $line = '[2026-10-30 16:10:11] production.ERROR: The "intl" PHP extension is required to use the [format] method. '
            . '(View: /home/smartqaq/praxis_core/resources/views/livewire/hq/mail/composer.blade.php) '
            . '{"userId":"a29df726-14d8-4198-9a6b-cd8e9e5e5c5e","exception":"[object] (Illuminate\\\\View\\\\ViewException(code: 0): '
            . 'The \\"intl\\" PHP extension is required to use the [format] method. at /home/smartqaq/praxis_core/vendor/laravel/framework/src/Illuminate/Support/Number.php:443)'
            . "\n[stacktrace]\n#0 /home/smartqaq/praxis_core/vendor/x.php(1): y()\n\"} \n";

        $method = new \ReflectionMethod(LogScanner::class, 'importLaravelLog');
        $this->assertSame(1, $method->invoke(null, $line));

        $group = DB::table('monitor_error_groups')->first();
        $this->assertSame('Illuminate\View\ViewException', $group->exception_class);
        $this->assertSame(443, (int) $group->line);
        $this->assertSame('a29df726-14d8-4198-9a6b-cd8e9e5e5c5e', DB::table('monitor_error_events')->value('user_id'));
        $this->assertStringContainsString('missing an extension', (string) ErrorMonitor::explain($group));
    }

    public function test_419_hint_only_for_a_real_419(): void
    {
        $this->assertNull(ErrorMonitor::explain((object) ['exception_class' => null, 'message' => 'Something {"userId":"a29df726-14d8-4198-9a6b"}']));
        $this->assertStringContainsString('page had expired', (string) ErrorMonitor::explain((object) ['exception_class' => null, 'message' => 'HTTP 419 Page Expired']));
    }
}
