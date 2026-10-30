<?php

namespace Tests\Feature\Controls;

use App\Livewire\Hq\Finance\TaxConfigurator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * HQ → Tax rules: looking at a country writes nothing (its row is created on the first save), saving keeps
 * the existing row, a band is removed only from the country on screen, and every change is in the audit log.
 * Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class TaxControlsTest extends TestCase
{
    use UsesThrowawayDatabase;

    public function test_viewing_the_tax_rules_writes_nothing(): void
    {
        $rows = DB::table('tax_configurations')->count();
        $this->actingAs($this->staff('finance', ['finance']));

        $screen = Livewire::test(TaxConfigurator::class);
        foreach (['CM', 'KE', 'GLOBAL', 'NG', 'XX'] as $region) {
            $screen->call('setRegion', $region);
        }
        $screen->call('loadConfiguration');

        $this->assertSame($rows, DB::table('tax_configurations')->count());
        $this->assertSame(0, DB::table('system_audit_logs')->count());

        try {
            $screen->set('bands', [['id' => 'x']]);
            $this->fail('bands should be locked');
        } catch (CannotUpdateLockedPropertyException) {
            // expected
        }
    }

    public function test_saving_keeps_existing_rows_creates_missing_ones_and_is_logged(): void
    {
        $finance = $this->staff('finance', ['finance']);
        $this->actingAs($finance);
        $nigeria = DB::table('tax_configurations')->where('region_code', 'NG')->value('id');
        $this->assertNotNull($nigeria, 'the reference data has a Nigeria row');

        Livewire::test(TaxConfigurator::class)
            ->set('config.minimum_wage', '70000')->set('config.region_code', 'CM')
            ->call('saveConfiguration')->assertSet('showSuccessModal', true);
        $this->assertSame($nigeria, DB::table('tax_configurations')->where('region_code', 'NG')->value('id'));
        $this->assertEquals(70000, DB::table('tax_configurations')->where('id', $nigeria)->value('minimum_wage'));
        $this->assertFalse(DB::table('tax_configurations')->where('region_code', 'CM')->exists());

        $saved = DB::table('system_audit_logs')->where('action', 'TAX_RULES_SAVED')->first();
        $this->assertNotNull($saved, 'saving is logged');
        $this->assertSame($nigeria, $saved->target_id);
        $this->assertSame($finance->id, $saved->user_id);
        $this->assertEquals(['minimum_wage' => 0], json_decode($saved->old_values, true));
        $this->assertEquals(['minimum_wage' => 70000], json_decode($saved->new_values, true));

        Livewire::test(TaxConfigurator::class)->call('setRegion', 'KE')
            ->set('config.minimum_wage', '15000')->call('saveConfiguration');
        $this->assertSame(1, DB::table('tax_configurations')->where('region_code', 'KE')->count());
    }

    public function test_a_band_is_removed_only_from_the_country_on_screen_and_logged(): void
    {
        $this->actingAs($this->staff('finance', ['finance']));
        $nigeria = DB::table('tax_configurations')->where('region_code', 'NG')->value('id');
        $band = (string) Str::uuid();
        DB::table('tax_bands')->insert(['id' => $band, 'tax_configuration_id' => $nigeria, 'band_name' => 'First band (test)',
            'lower_limit' => 0, 'upper_limit' => 300000, 'tax_rate' => 0.07, 'created_at' => now(), 'updated_at' => now()]);

        Livewire::test(TaxConfigurator::class)->call('setRegion', 'CM')->call('removeTaxBand', $band);
        $this->assertTrue(DB::table('tax_bands')->where('id', $band)->exists());

        Livewire::test(TaxConfigurator::class)->call('removeTaxBand', $band);
        $this->assertFalse(DB::table('tax_bands')->where('id', $band)->exists());
        $log = DB::table('system_audit_logs')->where('action', 'TAX_BAND_REMOVED')->first();
        $this->assertNotNull($log, 'removing a band is logged');
        $this->assertSame($band, json_decode($log->old_values, true)['band_id']);
    }
}
