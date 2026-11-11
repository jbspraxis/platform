<?php

/*
| Run by Tests\Feature\ProductionCachesTest after config:cache, route:cache, event:cache and view:cache (into the
| paths its environment gives). Boots HQ from those caches, asks for each address given (full URLs: HQ, the portal and
| the site's names share this app) and prints the statuses as JSON. Prints no configuration value.
*/

use Illuminate\Http\Request;

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$out = ['config' => $app->configurationIsCached(), 'routes' => $app->routesAreCached(), 'events' => $app->eventsAreCached(), 'paths' => []];
foreach (array_slice($argv, 1) as $url) {
    $request = Request::create($url, 'GET');
    try {
        $response = $kernel->handle($request);
        $status = $response->getStatusCode();
        if ($status >= 500 && isset($response->exception)) {
            $status .= ' ' . get_class($response->exception) . ': ' . $response->exception->getMessage();
        }
        $kernel->terminate($request, $response);
    } catch (Throwable $e) {
        $status = 'error ' . get_class($e) . ': ' . $e->getMessage();
    }
    $out['paths'][$url] = $status;
}

echo json_encode($out);
