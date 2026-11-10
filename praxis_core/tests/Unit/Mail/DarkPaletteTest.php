<?php

namespace Tests\Unit\Mail;

use App\Support\Mail\Read\DarkPalette;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DarkPaletteTest extends TestCase
{
    public static function sheets(): array
    {
        $light = 'p{color:#222}';

        return [
            'a dark block goes' => [$light . '@media (prefers-color-scheme: dark){p{color:#eee}}b{color:#333}', $light . 'b{color:#333}'],
            'whatever the spacing and case' => ['@media(PREFERS-COLOR-SCHEME :DARK){p{color:#eee}}' . $light, $light],
            'with a media type or another condition' => [
                '@media screen and (prefers-color-scheme:dark){td{color:#eee}}@media (prefers-color-scheme:dark) and (min-width:600px){p{color:#fff}}' . $light,
                $light,
            ],
            'nested inside a width query, which keeps its own rules' => [
                '@media (max-width:480px){.a{width:100%}@media (prefers-color-scheme:dark){.a{color:#eee}}.b{color:#000}}',
                '@media (max-width:480px){.a{width:100%}.b{color:#000}}',
            ],
            'only the dark half of a list goes' => ['@media (prefers-color-scheme: dark), print{p{color:#000}}', '@media print{p{color:#000}}'],
            'a query that is true on a light device stays' => [
                '@media not (prefers-color-scheme: dark){p{color:#111}}@media not all and (prefers-color-scheme: dark){b{color:#111}}',
                '@media not (prefers-color-scheme: dark){p{color:#111}}@media not all and (prefers-color-scheme: dark){b{color:#111}}',
            ],
            'light, no-preference, width and print stay' => [
                '@media (prefers-color-scheme: light){p{color:#111}}@media (prefers-color-scheme: no-preference){p{color:#222}}@media print{body{color:#000}}',
                '@media (prefers-color-scheme: light){p{color:#111}}@media (prefers-color-scheme: no-preference){p{color:#222}}@media print{body{color:#000}}',
            ],
            'a brace inside a string is not a block' => ['@media (prefers-color-scheme: dark){.x:after{content:"}"}.y{color:#eee}}' . $light, $light],
            'braces that never balance take the rest of the sheet with them' => [$light . '@media (prefers-color-scheme: dark){p{color:#eee}', $light],
            'two dark blocks in a row' => ['@media (prefers-color-scheme: dark){a{color:#eee}}@media (prefers-color-scheme: dark){b{color:#eee}}' . $light, $light],
            'the opt-in goes' => [':root{color-scheme:light dark;supported-color-schemes:light dark;color:#222}', ':root{color:#222}'],
            'with its vendor prefixes and as the last declaration' => ['p{color:#222;-webkit-color-scheme:dark}', 'p{color:#222;}'],
            'a string that mentions it is left alone' => ['a:after{content:"x;color-scheme:dark"}', 'a:after{content:"x;color-scheme:dark"}'],
            'a custom property that ends in it is left alone' => [':root{--color-scheme:dark;color:#222}', ':root{--color-scheme:dark;color:#222}'],
            'nothing to do' => [$light, $light],
        ];
    }

    #[DataProvider('sheets')]
    public function test_only_the_dark_half_of_a_sheet_goes(string $css, string $expected): void
    {
        $this->assertSame($expected, DarkPalette::strip($css));
    }
}
