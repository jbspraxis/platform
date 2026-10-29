<?php
/**
 * Screenshots of HQ and Client Portal pages without running a server: each page is rendered in-process
 * (local MySQL copy, signed in as a staff owner or a client), its asset links are pointed at local files,
 * and headless Edge takes the picture. Rendering happens inside a transaction that is rolled back.
 *
 *   cd praxis_core
 *   APP_ENV=localmysql MAIL_MAILER=log php tests/ui/snapshot.php --only=finance --out=../_ui/finance [--width=1440] [--height=1200] [--list]
 *
 * --only matches route names or paths (comma-separated). Pages that need a record id are skipped.
 * Static pictures: Livewire actions don't run, but Alpine and the icons do.
 */
putenv('APP_ENV=localmysql');
$_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'localmysql';
putenv('MAIL_MAILER=log');
$_ENV['MAIL_MAILER'] = $_SERVER['MAIL_MAILER'] = 'log';

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Route};

$opt = getopt('', ['only::', 'out::', 'width::', 'height::', 'list']);
$only = array_filter(array_map('trim', explode(',', strtolower($opt['only'] ?? ''))));
$out = rtrim($opt['out'] ?? base_path('../_ui'), '/\\');
$width = (int) ($opt['width'] ?? 1440);
$height = (int) ($opt['height'] ?? 1200);
$edge = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
@mkdir($out, 0777, true);
$out = realpath($out); // Edge needs absolute paths for the file URL and the picture

// Pages: HQ screens (staff owner), the Client Portal dashboard (client), and the two sign-in pages (no one)
$pages = [];
foreach (Route::getRoutes() as $r) {
    $name = $r->getName();
    if (!$name || !in_array('GET', $r->methods()) || $r->parameterNames()) continue;
    if (str_starts_with($name, 'hq.') && !in_array($name, ['hq.logout', 'hq.file'], true)) $pages[$name] = 'staff';
}
$pages['client.dashboard'] = 'client';
$pages['login'] = 'guest';
$pages['client.login'] = 'guest';
ksort($pages);
if ($only) {
    $pages = array_filter($pages, fn ($who, $name) => (bool) array_filter($only, fn ($o) => str_contains($name, $o) || str_contains(parse_url(route($name), PHP_URL_PATH) ?? '', $o)), ARRAY_FILTER_USE_BOTH);
}
if (isset($opt['list'])) {
    foreach ($pages as $name => $who) echo str_pad($name, 34) . " $who  " . route($name) . "\n";
    exit(0);
}

$roots = [
    'build' => 'file:///' . str_replace('\\', '/', public_path('build')),
    'web' => 'file:///' . str_replace('\\', '/', realpath(base_path('../public_html_PRAXIS'))),
    'livewire' => 'file:///' . str_replace('\\', '/', base_path('vendor/livewire/livewire/dist/livewire.js')),
];

DB::beginTransaction();
try {
    // A throwaway staff owner and the first client account with a password, so every screen has a person
    $ownerRole = DB::table('roles')->where('slug', 'owner')->value('id');
    $staffId = (string) Illuminate\Support\Str::uuid();
    $internalOrg = DB::table('users')->where('id', App\Models\User::EDITORIAL_ID)->value('organization_id')
        ?? DB::table('organizations')->where('status', 'Internal')->value('id');
    DB::table('users')->insert(['id' => $staffId, 'organization_id' => $internalOrg, 'first_name' => 'Ada', 'last_name' => 'Okafor',
        'email' => 'ui-snapshot@example.com', 'password' => bcrypt('x'), 'lifecycle_stage' => 'Active', 'status' => 'Active',
        'created_at' => now(), 'updated_at' => now()]);
    if ($ownerRole) DB::table('staff_roles')->insert(['user_id' => $staffId, 'role_id' => $ownerRole, 'created_at' => now()]);
    App\Support\Access::flush();
    $staff = App\Models\User::find($staffId);
    $client = App\Models\ClientUser::whereNotNull('password')->first();

    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    foreach ($pages as $name => $who) {
        Auth::guard('web')->logout();
        Auth::guard('client')->logout();
        if ($who === 'staff') Auth::guard('web')->setUser($staff);
        if ($who === 'client') {
            if (!$client) { echo "skip  $name (no client account with a password in the local database)\n"; continue; }
            Auth::guard('client')->setUser($client);
        }
        $url = route($name);
        try {
            $res = $kernel->handle(Request::create($url, 'GET'));
            $html = $res->getContent();
            $status = $res->getStatusCode();
        } catch (Throwable $e) {
            echo "FAIL  $name: " . $e->getMessage() . "\n";
            continue;
        }
        if ($status >= 300) { echo "skip  $name (HTTP $status)\n"; continue; }

        // Point assets at local files
        $html = preg_replace('~https?://[^"\']*?/build/~', $roots['build'] . '/', $html);
        $html = preg_replace('~(src|href)="/(assets|icons|splash)/~', '$1="' . $roots['web'] . '/$2/', $html);
        $html = preg_replace('~(src|href)="/(favicon\.ico|manifest-[a-z]+\.webmanifest)~', '$1="' . $roots['web'] . '/$2', $html);
        $html = preg_replace('~<script src="[^"]*livewire[^"]*\.js[^"]*"~', '<script src="' . $roots['livewire'] . '"', $html);

        $file = $out . DIRECTORY_SEPARATOR . str_replace('.', '_', $name);
        file_put_contents("$file.html", $html);
        // An argument list (no shell), so the paths with spaces need no quoting
        $proc = proc_open([$edge, '--headless=new', '--disable-gpu', '--hide-scrollbars', '--force-device-scale-factor=1',
            '--allow-file-access-from-files', '--virtual-time-budget=4000', "--window-size={$width},{$height}",
            "--screenshot={$file}.png", 'file:///' . str_replace('\\', '/', $file) . '.html'], [1 => ['file', 'NUL', 'w'], 2 => ['file', 'NUL', 'w']], $pipes);
        if (is_resource($proc)) proc_close($proc);
        echo (is_file("$file.png") ? 'ok    ' : 'NOPNG ') . "$name -> " . basename($file) . ".png\n";
    }
} finally {
    DB::rollBack();
}
