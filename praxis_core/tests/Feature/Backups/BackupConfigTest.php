<?php

namespace Tests\Feature\Backups;

use App\Support\Backups\Sites;
use Tests\TestCase;

/**
 * config/backups.php as found on the server on 4 Nov 2026 (docs/BACKUPS.md 2): the account's five databases each go
 * to their site, anything else to other/, and no files backup can sweep up a whole docroot or the home.
 */
class BackupConfigTest extends TestCase
{
    public function test_the_accounts_five_databases_go_to_their_sites_and_a_new_one_to_other(): void
    {
        $this->assertSame([
            'smartqaq_praxis_core' => 'praxis',
            'smartqaq_lpc_core' => 'lpc',
            'smartqaq_nfelia_core' => 'nfelia',
            'smartqaq_hodlc' => 'hodlc',
            'smartqaq_smartls' => 'smartls',
            'smartqaq_wp42' => 'other/smartqaq_wp42',
        ], collect(['smartqaq_praxis_core', 'smartqaq_lpc_core', 'smartqaq_nfelia_core', 'smartqaq_hodlc', 'smartqaq_smartls', 'smartqaq_wp42'])
            ->mapWithKeys(fn ($db) => [$db => Sites::forDatabase($db)])->all());

        $this->assertSame('hodlc_db_2026-11-05.sql.gz.enc', Sites::dbFileName('hodlc', 'smartqaq_hodlc', false, '2026-11-05'));
        $this->assertSame('wp42_db_2026-11-05.sql.gz.enc', Sites::dbFileName('other/smartqaq_wp42', 'smartqaq_wp42', false, '2026-11-05'));
        $this->assertSame('smartls_files_2026-11-08.tar.gz.enc', Sites::filesFileName('smartls', '2026-11-08'));
    }

    public function test_every_site_has_upload_folders_and_none_is_a_whole_docroot_or_the_home(): void
    {
        foreach (Sites::all() as $site => $config) {
            $this->assertNotEmpty($config['files'], "{$site} has no upload folders");
            foreach ($config['files'] as $folder) {
                // Not the home, not public_html (smartls.cm's docroot, holding every site), not a domain's docroot
                $this->assertDoesNotMatchRegularExpression('#^(|\.|/.*|.*\.\..*|public_html|public_html/[^/]+\.[^/]+|praxis_core|praxis_site)$#', $folder, "{$site}: {$folder} would back up a whole docroot");
            }
        }
        foreach (['*.zip', '*.tar', '*.tar.gz', '*.bak', '*_backup_*', 'cache', 'sessions', 'logs', 'vendor', 'node_modules'] as $pattern) {
            $this->assertContains($pattern, config('backups.exclude'));
        }
    }
}
