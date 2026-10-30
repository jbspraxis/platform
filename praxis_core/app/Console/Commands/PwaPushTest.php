<?php

namespace App\Console\Commands;

use App\Http\Middleware\HqStaff;
use App\Models\User;
use App\Support\Pwa\PushDispatcher;
use Illuminate\Console\Command;

/**
 * For the owner's checks on the server (docs/PWA.md, Deploy): a test notification to every device of one
 * staff member, with each push service's answer. Owner: the "push" part.
 */
class PwaPushTest extends Command
{
    protected $signature = 'pwa:push-test {email : the staff member’s email address}';

    protected $description = 'Send a test notification to every device of one HQ staff member';

    public function handle(): int
    {
        if (! PushDispatcher::ready()) {
            $this->error('The HQ app tables are not there yet. Run the migration first.');

            return self::FAILURE;
        }

        $user = User::where('email', trim((string) $this->argument('email')))->first();
        if (! $user || ! HqStaff::allows($user)) {
            $this->error('No active staff member has that email address.');

            return self::FAILURE;
        }

        $result = PushDispatcher::sendTest($user);
        if (! $result['devices']) {
            $this->warn("{$user->email} has no device with notifications turned on. Turn them on in HQ first (bell menu).");

            return self::FAILURE;
        }

        foreach ($result['devices'] as $device) {
            $name = mb_strimwidth($device['device'], 0, 48, '…');
            $this->line('  ' . $name . str_repeat(' ', max(1, 50 - mb_strwidth($name))) . self::describe($device['status']));
        }
        $this->line("Sent to {$result['sent']} of " . count($result['devices']) . ' device(s).');

        return $result['sent'] > 0 ? self::SUCCESS : self::FAILURE;
    }

    private static function describe(int $status): string
    {
        return match (true) {
            $status >= 200 && $status < 300 => "{$status}: accepted by the push service",
            in_array($status, [404, 410], true) => "{$status}: the device unsubscribed, so it was removed",
            in_array($status, [400, 403], true) => "{$status}: the device’s keys no longer match, so it was removed (it signs up again when HQ next opens there)",
            $status === 413 => '413: the message was too large',
            $status === 429 => '429: the push service asked HQ to slow down; try again in a minute',
            $status === 0 => 'no answer from the push service (network or time-out)',
            default => "{$status}: refused by the push service",
        };
    }
}
