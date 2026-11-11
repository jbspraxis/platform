<?php

namespace Tests\Feature;

use App\Support\Monitor\ErrorMonitor;
use Illuminate\Http\Request;
use Livewire\Exceptions\MethodNotFoundException;
use Tests\TestCase;

/**
 * Which Livewire component an error is filed under (owner's export, 11 Nov 2026: "Public method [setTab] not found"
 * was filed under the notification bell). Livewire sends a tab click and the bell's push refresh in one request when
 * they happen together, bell first; the error belongs to the component the failing method was called on.
 */
class MonitorLivewireComponentTest extends TestCase
{
    private function componentFor(\Throwable $e, array $components): ?string
    {
        $payload = ['components' => array_map(fn ($c) => [
            'snapshot' => json_encode(['data' => [], 'memo' => ['id' => 'x', 'name' => $c[0]], 'checksum' => 'x']),
            'updates' => [],
            'calls' => array_map(fn ($m) => ['method' => $m, 'params' => [], 'metadata' => []], $c[1]),
        ], $components)];
        $request = Request::create('/livewire-3b7ad655/update', 'POST', $payload);

        // captureException() reads the live request, which a console run doesn't have
        return (new \ReflectionMethod(ErrorMonitor::class, 'livewireComponent'))->invoke(null, $request, $e);
    }

    public function test_the_component_the_failing_method_was_called_on(): void
    {
        $component = $this->componentFor(new MethodNotFoundException('setTab'), [
            ['hq.components.notification-bell', ['__dispatch']],
            ['hq.system.system-health', ['setTab']],
        ]);

        $this->assertSame('hq.system.system-health', $component);
    }

    public function test_other_errors_go_to_the_component_that_ran_a_method_rather_than_an_event(): void
    {
        $component = $this->componentFor(new \RuntimeException('Something broke'), [
            ['hq.components.notification-bell', ['__dispatch']],
            ['hq.system.role-management', ['openClient']],
        ]);

        $this->assertSame('hq.system.role-management', $component);
    }

    public function test_one_component_as_before(): void
    {
        $this->assertSame('hq.components.notification-bell', $this->componentFor(new \RuntimeException('Push refresh failed'), [
            ['hq.components.notification-bell', ['__dispatch']],
        ]));
    }
}
