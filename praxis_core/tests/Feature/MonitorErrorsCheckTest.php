<?php

namespace Tests\Feature;

use App\Support\Monitor\HealthChecks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** The "Errors (last 24 h)" check on HQ → System health. */
class MonitorErrorsCheckTest extends TestCase
{
    use RefreshDatabase;

    private function check(): array
    {
        return collect(HealthChecks::all())->firstWhere('key', 'errors');
    }

    private function errorGroups(string $status, int $count, string $level = 'error'): void
    {
        foreach (range(1, $count) as $i) {
            DB::table('monitor_error_groups')->insert([
                'fingerprint' => Str::random(40), 'source' => 'exception', 'level' => $level, 'message' => "Error {$status} {$i}",
                'occurrences' => 1, 'first_seen_at' => now()->subHours(3), 'last_seen_at' => now()->subHours(2), 'status' => $status,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_errors_marked_fixed_or_ignored_leave_the_check_green(): void
    {
        // 30 Oct 2026: six errors seen and fixed the same day kept the card red ("Problem") with "No open errors"
        $this->errorGroups('fixed', 6);
        $this->errorGroups('ignored', 1);

        $check = $this->check();

        $this->assertSame('ok', $check['state']);
        $this->assertSame('No open errors in the last 24 hours.', $check['detail']);
    }

    public function test_one_open_error_is_a_warning(): void
    {
        $this->errorGroups('fixed', 6);
        $this->errorGroups('new', 1);

        $check = $this->check();

        $this->assertSame('warn', $check['state']);
        $this->assertSame('1 open error type(s) seen, 1 new.', $check['detail']);
    }

    public function test_more_than_five_new_open_errors_or_a_critical_one_is_a_problem(): void
    {
        $this->errorGroups('new', 6);
        $this->assertSame('bad', $this->check()['state']);

        DB::table('monitor_error_groups')->delete();
        $this->errorGroups('investigating', 1, 'critical');
        $this->assertSame('bad', $this->check()['state']);
    }
}
