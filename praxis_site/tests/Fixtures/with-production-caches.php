<?php

/*
| Run by Tests\Feature\ProductionCachesTest after config:cache, route:cache and view:cache (into the paths its
| environment gives). Boots the site from those caches with HQ unreachable (no request leaves), asks for each path
| given and prints the statuses as JSON. Prints no configuration value.
*/

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Http::preventStrayRequests();
Http::fake(['*' => fn () => throw new ConnectionException('HQ is not reachable from this test')]);

$out = ['config' => $app->configurationIsCached(), 'routes' => $app->routesAreCached(), 'paths' => []];
foreach (array_slice($argv, 1) as $path) {
    // "{indexnow}" stands for the IndexNow key, read from the cached configuration
    $request = Request::create('https://jbspraxis.com' . str_replace('{indexnow}', (string) config('site.indexnow.key'), $path), 'GET');
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
    $out['paths'][$path] = $status;
}

echo json_encode($out);
