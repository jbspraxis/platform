<?php

namespace Tests\Feature;

use App\Services\Site\PraxisAi;
use Illuminate\Support\Facades\Process;
use Tests\Support\PhpWithout;
use Tests\TestCase;

/**
 * The website assistant's prompt names the offices' and registrations' countries. Without intl (the live web PHP)
 * it used to say "Lagos, NG" and "Registration CM": it now takes the names HQ offers (WebsiteSettings::COUNTRIES).
 * Checked in a second PHP started without intl.
 */
class PraxisAiWithoutIntlTest extends TestCase
{
    use PhpWithout;

    /** Codes HQ offers (WebsiteSettings::COUNTRIES; nothing else can be saved) and the names intl gives them */
    private const CODES = ['NG', 'cm', 'CI', 'GB'];

    private const NAMES = ['Nigeria', 'Cameroon', 'Côte d’Ivoire', 'United Kingdom'];

    public function test_country_names_without_intl(): void
    {
        $php = $this->phpWithout('intl');
        if ($php === null) {
            $this->markTestSkipped('intl is built into this PHP: it cannot be left out');
        }

        $code = 'require ' . var_export(base_path('vendor/autoload.php'), true) . ';'
            . '$m = new ReflectionMethod(App\Services\Site\PraxisAi::class, "country");'
            . 'echo json_encode(["intl" => extension_loaded("intl"), "names" => array_map(fn ($c) => $m->invoke(null, $c), '
            . var_export([...self::CODES, 'ZZ'], true) . ')]);';
        $run = Process::path(base_path())->run([...$php, '-r', $code]);

        $this->assertTrue($run->successful(), $run->errorOutput() . $run->output());
        $out = json_decode($run->output(), true);
        $this->assertIsArray($out, $run->output());
        $this->assertFalse($out['intl'], 'the second PHP still has intl');
        $this->assertSame([...self::NAMES, 'ZZ'], $out['names']); // a code outside the list stays as it is
    }

    public function test_country_names_in_this_php(): void
    {
        $country = new \ReflectionMethod(PraxisAi::class, 'country');

        $this->assertSame(self::NAMES, array_map(fn ($c) => $country->invoke(null, $c), self::CODES));
        $this->assertNull($country->invoke(null, null));
        $this->assertNull($country->invoke(null, ''));
    }
}
