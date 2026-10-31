<?php

/*
| Run by Tests\Feature\WithoutIntlTest twice: in this PHP (with intl) and in a PHP started without the intl
| extension, like the live web PHP. Boots the site and prints, as JSON, how App\Support\Format writes numbers
| in English and in French.
*/

use App\Support\Format;

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$out = ['intl' => extension_loaded('intl'), 'formats' => []];
foreach (['en', 'fr'] as $locale) {
    app()->setLocale($locale);
    $out['formats'][$locale] = [
        'number' => Format::number(1250000),
        'decimals' => Format::number(1234.56, 1),
        'negative' => Format::number(-2500000),
        'percent' => Format::percent(3.25),
        'naira' => Format::money(1250000, 'NGN'),
        'fcfa' => Format::money(2500000, 'XAF'),
    ];
}

echo json_encode($out);
