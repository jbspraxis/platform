<?php

namespace Tests\Unit;

use App\Support\Countries;
use Tests\TestCase;

/**
 * App\Support\Countries: every country, its ISO code and its dialling code (lang/{en,fr}/countries.php).
 *
 * The forms send the dialling code, and thirteen codes are shared by more than one country, so the two
 * lists are different lengths on purpose. A list keyed by the code would otherwise keep only the last
 * country of each shared code and drop the other twenty-four of +1 without a word.
 */
class CountriesTest extends TestCase
{
    public function test_the_markets_the_site_serves_have_the_right_dialling_codes(): void
    {
        $expected = [
            'NG' => '+234', 'CM' => '+237', 'GH' => '+233', 'CI' => '+225', 'SN' => '+221',
            'BJ' => '+229', 'TG' => '+228', 'GA' => '+241', 'CG' => '+242', 'CD' => '+243',
            'RW' => '+250', 'KE' => '+254', 'ZA' => '+27', 'EG' => '+20', 'MA' => '+212',
            'GB' => '+44', 'FR' => '+33', 'BE' => '+32', 'CH' => '+41', 'DE' => '+49',
            'US' => '+1', 'CA' => '+1', 'AE' => '+971',
        ];

        foreach ($expected as $iso => $dial) {
            $this->assertSame($dial, Countries::dial($iso), $iso);
        }
    }

    public function test_every_country_is_listed_in_both_languages_with_a_code(): void
    {
        $en = Countries::options('en');
        $fr = Countries::options('fr');

        $this->assertGreaterThan(200, count($en), 'the whole world, not a shortlist');
        $this->assertCount(count($en), $fr, 'both languages name the same countries');

        $this->assertSame(array_column($en, 'iso'), array_column($en, 'iso'));
        foreach ([...$en, ...$fr] as $country) {
            $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $country['iso']);
            $this->assertMatchesRegularExpression('/^\+\d{1,4}$/', $country['dial'], $country['iso']);
            $this->assertNotSame('', trim($country['name']), $country['iso']);
        }
    }

    public function test_a_shared_dialling_code_keeps_every_country_and_names_the_right_one(): void
    {
        $codes = Countries::codes('en');

        // Fewer codes than countries, because several countries share one
        $this->assertLessThan(count(Countries::options('en')), count($codes));

        // +1 is one entry, named after the country it belongs to, with the other 24 counted and not lost
        $this->assertSame('United States and 24 others (+1)', $codes['+1']);
        $this->assertSame('United Kingdom and 3 others (+44)', $codes['+44']);
        $this->assertSame('Russia and 1 other (+7)', $codes['+7']);

        // A code only one country uses is just that country
        $this->assertSame('Nigeria (+234)', $codes['+234']);

        // Every country's code still resolves, including the 24 that share +1
        foreach (['US', 'CA', 'BS', 'JM', 'TT'] as $iso) {
            $this->assertArrayHasKey(Countries::dial($iso), $codes, $iso);
        }
    }

    public function test_the_labels_are_translated(): void
    {
        $this->assertSame('États-Unis et 24 autres (+1)', Countries::codes('fr')['+1']);
        $this->assertSame('Côte d’Ivoire (+225)', Countries::codes('fr')['+225']);
    }

    public function test_an_unknown_code_is_not_invented(): void
    {
        $this->assertNull(Countries::dial('ZZ'));
        $this->assertFalse(Countries::has('ZZ'));
        $this->assertSame('+225', Countries::dial('ci'), 'the case of the code does not matter');
    }
}
