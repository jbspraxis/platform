<?php

/*
| Run by Tests\Feature\WithoutFileinfoTest in a PHP started without the fileinfo extension, like the live web PHP.
| Boots the site, then puts each file given as "name=path" through the careers CV's rules as an upload, and prints
| what happened as JSON.
*/

use App\Http\Controllers\CareersController;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$out = ['fileinfo' => extension_loaded('fileinfo'), 'finfo' => class_exists('finfo') ? (get_parent_class('finfo') ?: 'finfo') : null, 'files' => []];
foreach (array_slice($argv, 1) as $arg) {
    [$name, $path] = explode('=', $arg, 2);
    try {
        $check = Validator::make(['cv' => new UploadedFile($path, $name, null, UPLOAD_ERR_OK, true)], ['cv' => CareersController::CV_RULES]);
        $out['files'][$name] = $check->passes() ? 'passes' : 'fails ' . strtolower(implode(',', array_keys($check->failed()['cv'])));
    } catch (Throwable $e) {
        $out['files'][$name] = 'error ' . $e->getMessage();
    }
}

echo json_encode($out);
