<?php

/*
| Server backups (docs/BACKUPS.md): every night at 02:00 Lagos time, every database the cPanel account owns is dumped,
| encrypted with the owner's public key and uploaded to praxisjbs@gmail.com's Drive; every Sunday at 03:00, each
| site's uploaded files too. Nothing here is a secret: the read-only MySQL user's password lives only in
| ~/.backup-my.cnf on the server (backups:setup writes it), and the private key only on the owner's PC.
*/

$home = env('BACKUP_HOME', dirname(base_path()));

return [

    // The cPanel account's home (/home/smartqaq on live: praxis_core's parent) and where backups wait on the server.
    // Never under public_html; backups:setup makes it 700.
    'home' => $home,
    'dir' => env('BACKUP_DIR', $home . '/backups'),

    // The read-only MySQL user backups:setup creates through uapi, and the client file holding its password (600)
    'cpanel_user' => env('BACKUP_CPANEL_USER', 'smartqaq'),
    'mysql_user' => env('BACKUP_MYSQL_USER', 'smartqaq_bkread'),
    'mysql_cnf' => env('BACKUP_MYSQL_CNF', $home . '/.backup-my.cnf'),
    'mysql_host' => env('BACKUP_MYSQL_HOST', 'localhost'),
    'mysql_port' => env('BACKUP_MYSQL_PORT'),

    // Where the list of databases comes from: 'cpanel' (uapi Mysql list_databases: every database the account owns,
    // so a new one is backed up without anyone remembering) or 'mysql' (SHOW DATABASES starting with the prefix:
    // local runs and tests, where there is no cPanel)
    'databases_from' => env('BACKUP_DATABASES_FROM', 'cpanel'),
    'database_prefix' => env('BACKUP_DATABASE_PREFIX', 'smartqaq_'),

    // The weekly restore test imports each fresh dump here, compares it, then empties it (created by backups:setup).
    // It is never backed up itself.
    'scratch_database' => env('BACKUP_SCRATCH_DATABASE', 'smartqaq_bkrestore'),
    'exclude_databases' => array_values(array_filter(explode(',', (string) env('BACKUP_EXCLUDE_DATABASES', '')))),
    'restore_test_day' => 'sunday',

    // Programs (cPanel's PATH has them; Laragon's on a Windows PC)
    'bin' => [
        'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),
        'mysql' => env('BACKUP_MYSQL', 'mysql'),
        'tar' => env('BACKUP_TAR', 'tar'),
        'uapi' => env('BACKUP_UAPI', 'uapi'),
    ],

    /*
    | The five sites. A database belongs to the first site with a matching name (* is a wildcard); a database no site
    | claims is still backed up, under other/<database>, and rings the owner so it can be added here. 'files' are
    | folders under the home, tarred every Sunday without the 'exclude' names (any folder or file of that name).
    */
    'sites' => [
        'praxis' => [
            'label' => 'HQ, jbspraxis.com and the portal',
            'databases' => ['smartqaq_praxis*'],
            'files' => ['praxis_core/storage/app/private', 'praxis_core/storage/app/public'],
        ],
        'lpc' => [
            'label' => 'bureau.lpc.cm',
            'databases' => ['smartqaq_lpc*', 'smartqaq_bureau*'],
            'files' => ['public_html/bureau.lpc.cm/uploads'],
        ],
        'nfelia' => [
            'label' => 'nfelia.com',
            'databases' => ['smartqaq_nfelia*'],
            'files' => ['public_html/nfelia.com/uploads', 'public_html/nfelia.com/storage'],
            'exclude' => ['release', 'tmp'],
        ],
        'hodlc' => [
            'label' => 'hodlc',
            'databases' => ['smartqaq_hodlc*', 'smartqaq_hod*'],
            'files' => [],
        ],
        'smartls' => [
            'label' => 'smartls.cm',
            'databases' => ['smartqaq_smartls*', 'smartqaq_sls*'],
            'files' => [],
        ],
    ],

    // Left out of every files backup: caches, logs, sessions and installed packages
    'exclude' => ['cache', 'sessions', 'logs', 'vendor', 'node_modules', '*.log', 'error_log'],

    // How many of each Drive keeps (dates, newest first), and how many the server keeps in the backups folder
    'retention' => ['daily' => 30, 'monthly' => 12, 'files' => 8],
    'local_keep' => ['db' => 7, 'files' => 2],

    // A site whose last good database backup is older than this rings the owner (hourly check)
    'stale_hours' => 26,
    'files_stale_days' => 8,

    'drive' => [
        'root' => 'JBS Praxis – Server backups',
        'readme' => 'READ ME FIRST – how to restore.pdf',
        // Resumable upload chunk: a multiple of 256 KiB, as Drive requires
        'chunk_bytes' => (int) env('BACKUP_DRIVE_CHUNK', 8 * 1024 * 1024),
    ],

    // The owner's public key (PEM). The private half never leaves the owner's PC (deploy/backups/pc/New-BackupKey.ps1).
    'public_key' => env('BACKUP_PUBLIC_KEY_FILE', resource_path('backups/owner-public-key.pem')),
];
