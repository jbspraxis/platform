<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode (the app lives in ../../praxis_core)...
if (file_exists($maintenance = __DIR__.'/../../praxis_core/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../../praxis_core/vendor/autoload.php';
$app = require_once __DIR__.'/../../praxis_core/bootstrap/app.php';

$app->handleRequest(Request::capture());
