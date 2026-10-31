<?php

namespace Tests\Unit\SignIn;

use App\Support\SignIn\DeviceName;
use App\Support\SignIn\Pins;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/** The PIN's rules that need no database (docs/SIGN_IN.md section 4), and passkey device names. */
class PinRulesTest extends TestCase
{
    public function test_the_shapes_people_reach_for_first_are_refused(): void
    {
        foreach ([
            '123' => 'digits', '12345' => 'digits', '12a4' => 'digits', '' => 'digits',
            '0000' => 'repeated', '7777' => 'repeated',
            '1234' => 'run', '6789' => 'run', '9876' => 'run', '3210' => 'run',
            '1212' => 'pair', '4545' => 'pair',
            '1155' => 'doubles', '0099' => 'doubles',
            '2580' => 'common', '1998' => 'common',
        ] as $pin => $why) {
            $this->assertSame($why, Pins::weakReason((string) $pin), "PIN {$pin}");
        }
        foreach (['4729', '8051', '3816', '0472'] as $fine) {
            $this->assertNull(Pins::weakReason($fine), "PIN {$fine}");
        }
    }

    public function test_the_wait_doubles_after_five_wrong_and_stops_at_fifteen_minutes(): void
    {
        $now = CarbonImmutable::parse('2026-10-31 12:00:00');
        $this->assertSame(0, Pins::waitSeconds(5, $now, $now));
        $this->assertSame(2, Pins::waitSeconds(6, $now, $now));
        $this->assertSame(4, Pins::waitSeconds(7, $now, $now));
        $this->assertSame(1, Pins::waitSeconds(7, $now->subSeconds(3), $now));
        $this->assertSame(0, Pins::waitSeconds(7, $now->subSeconds(10), $now));
        $this->assertSame(900, Pins::waitSeconds(40, $now, $now));
        $this->assertSame(0, Pins::waitSeconds(9, null, $now));
    }

    public function test_a_passkey_is_named_after_its_device_and_browser(): void
    {
        $this->assertSame('iPhone · Safari', DeviceName::from('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1'));
        $this->assertSame('Android · Chrome', DeviceName::from('Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36'));
        $this->assertSame('Windows · Edge', DeviceName::from('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36 Edg/130.0'));
        $this->assertSame('Mac · Firefox', DeviceName::from('Mozilla/5.0 (Macintosh; Intel Mac OS X 14.6; rv:131.0) Gecko/20100101 Firefox/131.0'));
        $this->assertSame('Passkey', DeviceName::from(null));
    }
}
