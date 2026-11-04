# Server backups

Every night, every database on the shared cPanel server (account `smartqaq` on Newtoncorp's srv-web-ns9) is dumped, encrypted so that only the owner's PC can open it, and uploaded to praxisjbs@gmail.com's Google Drive. Every Sunday, each site's uploaded files are too. HQ runs it all from its own scheduler (`praxis_core`), shows it in **System health**, and rings the owner when something is wrong. Built on 4 Nov 2026, the day the whole server went down with nothing backed up off it.

## 1. The owner's decisions (4 Nov 2026)

1. **All five sites, automatically.** At 02:00 Lagos time, every database the cPanel account owns is dumped (`mysqldump --single-transaction --quick --routines --triggers --events`, gzip, lowest CPU and disk priority), so a sixth database is included without anyone remembering. Each database belongs to a site (praxis, lpc, nfelia, hodlc, smartls); one no site claims goes under `other/<database>` and rings the owner.
2. **Uploaded files weekly**, Sundays at 03:00: one tar per site of its upload folders (HQ's `storage/app` private and public; the other sites' upload folders, listed in `config/backups.php`), without caches, logs, sessions or installed packages.
3. **A read-only MySQL user**, created by the server itself through cPanel's `uapi` (`backups:setup`), with SELECT, SHOW VIEW, LOCK TABLES, TRIGGER and EVENT on each database and a random password nobody sees: it lives only in `~/.backup-my.cnf` (600).
4. **Encryption only the owner's PC can open.** The server holds only the public key. The private key is made on the owner's PC and lives there and in the owner's password manager. **No passphrase on it** (the owner's choice): the key file alone opens every backup, so it is kept as carefully as a password. Since 4 Nov 2026 it has three copies off the PC: Bitwarden (the main one), HQ's key vault, and a passphrase-locked file in the jbspraxisllc@gmail.com Drive (docs/KEY_VAULT.md).
5. **One tidy Drive folder**, nothing loose (section 5). Names start with the site and the date. Drive keeps 30 nights, 12 months and 8 Sundays of files; the server keeps the last 7 in `~/backups` (700, never under `public_html`).
6. **The owner approves Drive once.** HQ's Google connection asks for one more permission, `drive.file`: HQ sees and touches only the files it creates. Until it is granted, Settings and access › Integrations says "Drive backups: reconnect Google to allow".
7. **Proof it works.** System health shows each site's last backup (time, size, status), its last files backup and its restore test. The owner's bell rings when a backup fails, when a site's last good backup is older than 26 hours, or when Drive's checksum of an upload doesn't match. Each week, before encryption, each fresh dump is imported into a scratch database and compared table by table and row by row. (The cPanel plan allows 5 databases and the sites use all 5, so there is no scratch database for now: the weekly test is a dump check, and says so; section 5.)

## 2. What is backed up

As found on the server on 4 Nov 2026 (read in cPanel; the account owned exactly these five databases):

| Site | Database (`config/backups.php`) | Size then | Upload folders (under `/home/smartqaq`) |
|---|---|---|---|
| praxis (HQ, the portal, jbspraxis.com) | `smartqaq_praxis_core` | ~19 MB | `praxis_core/storage/app/private`, `praxis_core/storage/app/public`, `praxis_site/storage/app` (jbspraxis.com's own files) |
| lpc (bureau.lpc.cm, lpc.cm) | `smartqaq_lpc_core` | ~23 MB | `public_html/bureau.lpc.cm/uploads`, `public_html/lpc.cm/uploads` |
| nfelia (nfelia.com, nfelia.lpc.cm) | `smartqaq_nfelia_core` | ~6 MB | `public_html/nfelia.com/uploads`, `public_html/nfelia.com/storage`, `public_html/nfelia.lpc.cm/uploads`, `public_html/nfelia.lpc.cm/storage` (both storage folders without `framework`, `release`, `tmp`) |
| hodlc (hodlc.lpc.cm) | `smartqaq_hodlc` | ~20 MB | `public_html/hodlc.lpc.cm/uploads` |
| smartls (smartls.cm, the account's main domain) | `smartqaq_smartls` | ~5 MB | `public_html/uploads`, `public_html/administration/uploads`, `public_html/administration/upload`, `public_html/administration/storage`, `public_html/assets/uploads`, `uploads` (the private one in the home) |

smartls.cm's docroot is `public_html` itself, which also holds the other sites and old exports: only its upload folders are backed up. `www/uploads` is the same folder as `public_html/uploads` (`www` is a link to `public_html`), and `public_html/PRAXIS/storage` (HQ's and the portal's public files) is `storage:link`'s symlink to `praxis_core/storage/app/public`, already listed: neither is listed twice. Folders checked live in cPanel on 4 Nov 2026.

Left out of every files backup: any folder or file named `cache`, `sessions`, `logs`, `vendor`, `node_modules`, `*.log`, `error_log`, and archives and old copies (`*.zip`, `*.tar`, `*.tar.gz`, `*.tgz`, `*.bak`, `*.failed_*`, `*.pre_*`, `*_backup_*`), even inside an upload folder. The `public_html/public_html` copy and the `*_backup_*` folders in the home are never listed. Never backed up: the scratch database `smartqaq_bkrestore` and anything in `BACKUP_EXCLUDE_DATABASES`.

To add a database or a folder to a site, edit its line in `config/backups.php` and merge: the next night picks it up. `backups:setup --dry-run` prints every database with its site and every site's folders (found or not), plus folders that look like uploads.

## 3. The owner's key (once, on the owner's PC)

1. In the repository, open `deploy/backups/pc/` and double-click **Make backup key.cmd** (or run `powershell -NoProfile -ExecutionPolicy Bypass -File New-BackupKey.ps1`). It needs nothing installed.
2. It makes a 4096-bit RSA key in `Documents\JBS Praxis backup key\`:
   - `backup-private-key.pem`: opens every backup. Only this Windows account may read it. **Save it in Bitwarden straight away** (attach the file, or paste its whole text): that is the main copy. Then lock it in HQ › Settings and access › **Key vault** with a passphrase, and keep the vault's **locked copy** in the jbspraxisllc@gmail.com Google Drive (docs/KEY_VAULT.md: the three copies, opening the locked copy offline, and what to do if the laptop is lost). Never send the key itself to anyone, Claude included; never put it unlocked on the server, in email, chat or Drive.
   - `backup-public-key.pem`: safe to share.
   - `Restore-Backup.ps1` and **Restore a backup.cmd**: the restore tool, copied next to the key.
   - `READ ME - backup key.txt` with the key's fingerprint (e.g. `56cd 13d1 …`).
3. It refuses to overwrite an existing key (backups made for it would become unreadable). To change keys on purpose, move the old folder away and keep it: old backups still need the old key.
4. The lead chat copies **only** `backup-public-key.pem` into the repository as `praxis_core/resources/backups/owner-public-key.pem` and merges. The fingerprint shows in System health's README and in the restore tool, so anyone can check they match.

## 4. Switching it on (once, after the merge)

1. **Merge** the PR. The deployer puts it live and runs the migration (`server_backups`). Nothing is backed up yet: System health says "Not switched on yet".
2. **Run the setup once**, with a one-shot cron line in cPanel › Cron Jobs (every minute; the `mkdir` makes it run only once):

   ```bash
   mkdir /home/smartqaq/backups_setup_run 2>/dev/null && /usr/local/bin/ea-php83 /home/smartqaq/praxis_core/artisan backups:setup > /home/smartqaq/backups_setup_run/setup.log 2>&1
   ```

   A minute later, read `~/backups_setup_run/setup.log` (cPanel › File Manager), then delete the cron line and the folder. The log lists what it did, each database with its site, and each site's upload folders; it never shows the password. To look first without changing anything, use `backups:setup --dry-run` the same way. `backups:setup` is safe to run again: it creates only what is missing and grants every database again. (`backups:setup --once` does nothing once the setup has succeeded.)

   **When the plan allows no more databases** (as on 4 Nov 2026: 5 of 5 used), the scratch database can't be created. The setup goes on and says so in three lines: "Could not create the scratch database smartqaq_bkrestore: the cPanel plan allows no more databases. cPanel said: You have reached your maximum allotment of databases (5).", then that the backups still run and the weekly test is a dump check, then that a later `backups:setup` brings the import test back. It still grants the five databases, signs in as the backup user and finishes ("Set up."). Once the host allows one more database, run the same one-shot line again (with a new folder name, e.g. `backups_setup_run2`): it creates the scratch database, grants it, and from the next Sunday the weekly test imports again by itself.
3. **The owner reconnects Google** (the one approval): HQ › Settings and access › Integrations › **Reconnect Google**, sign in as praxisjbs@gmail.com on Google's page, and tick every box, including "See, edit, create and delete only the specific Google Drive files you use with this app". The page then says "Drive backups: allowed". Nobody else signs in.
4. **The owner makes the key** (section 3) if not done, and the public key goes into `resources/backups/owner-public-key.pem` (merge).
5. **Check the first night.** After 02:00 Lagos time, System health › Server backups shows each site with OK, a size and "on Drive", and the weekly test (the first night always runs one) with its table and row counts: **Dump check passed** while there is no scratch database (it says "Not imported: no scratch database"), **Restore test passed** once there is one. In Drive, the folder has the README and each site's `daily/` and `monthly/` file. Download one and open it with the restore tool (section 6) to prove the key works. The run's output is in `praxis_core/storage/logs/backups.log`.

For a first run without waiting for the night, the same kind of one-shot cron line can run `backups:run` (and `backups:files`):

```bash
mkdir /home/smartqaq/backups_first_run 2>/dev/null && /usr/local/bin/ea-php83 /home/smartqaq/praxis_core/artisan backups:run > /home/smartqaq/backups_first_run/run.log 2>&1 && /usr/local/bin/ea-php83 /home/smartqaq/praxis_core/artisan backups:files > /home/smartqaq/backups_first_run/files.log 2>&1
```

## 5. What happens each night and each Sunday

`routes/console.php`: `backups:run` daily at 02:00 Lagos, `backups:files` Sundays at 03:00 Lagos (each in its own background process, never two at once: a lock file in `~/backups`), `backups:check` hourly.

**Each database, every night** (`App\Support\Backups\Backups::databases`):
1. The list comes from `uapi Mysql list_databases` (every database the account owns). A database the backup user can't read yet is granted there and then, so it is in tonight's backup.
2. `mysqldump` (as the read-only user, through `~/.backup-my.cnf`, at `nice -n 19` and `ionice -c3`) streams through gzip into `~/backups/tmp/` (700). A dump without mysqldump's closing "Dump completed" line is refused.
3. **On Sundays** (and whenever the last test is over a week old, so also the first night), the weekly test, in one of two forms:
   - **Restore test**, when the backup user has the scratch database `smartqaq_bkrestore`: the dump is imported into it, each table's rows are counted against the rows in the dump, every table of the live database must be in it and every view created, then the scratch database is emptied. DEFINER clauses are dropped on the way in (the backup user can't create objects in another user's name).
   - **Dump check**, when there is no scratch database (the plan's database limit): nothing is imported. The whole `.gz` is decompressed with zlib's checks (a damaged or cut file fails), every table's rows are counted, every live table and view must be in the dump, and mysqldump's closing "Dump completed" line must be there. The live row counts are recorded beside the dump's (and the first tables that differ), but never fail the check: rows written after the dump make them differ.

   Both read the dump with the same strict decoder. A failed test marks the backup failed and rings, but the backup is still encrypted and kept. System health and the run's output always say which test ran ("Restore test passed", or "Dump check passed" with "Not imported: no scratch database"). A missing scratch database rings nobody.
4. It is encrypted with the public key into `~/backups/<site>/` (section 7) and the plain dump is removed at once. Nothing plain stays on disk; a run that died leaves its plain files in `tmp/`, which the next run removes first.
5. It is uploaded with Drive's resumable protocol in 8 MB chunks (a dropped connection or a Google error resumes from Drive's last byte, five times in a row at most), then checked: Drive's `md5Checksum` must equal the file's MD5. A mismatch is sent once more; a second mismatch fails the backup and rings.
6. The month's first backup of each database is also copied (by Drive, nothing re-sent) into `monthly/`.
7. Pruning: Drive keeps the newest 30 dates in `daily/`, 12 in `monthly/`, 8 in `files/` (older ones go to Drive's bin, which Google empties after 30 days); the server keeps the newest 7 dates of each site's database files and 2 of its files backups. Only files with a dated backup name are ever touched.

**Each site's files, every Sunday** (`Backups::files`): `tar -czf` of its folders (paths from the home folder, so a restore puts each file back where it was), then steps 4, 5 and 7. A folder that doesn't exist fails that site's files backup (what exists is still backed up) and rings.

The size of a night: today's databases are a few MB compressed; a dump of the largest takes seconds. Uploads go out at the server's speed, a chunk at a time.

## 6. Restoring a site from Drive (owner's PC)

1. In Drive › **JBS Praxis – Server backups** › the site › `daily/` (or `monthly/`, or `files/`), download the file, e.g. `praxis_db_2026-11-05.sql.gz.enc`.
2. Double-click **Restore a backup.cmd** in `Documents\JBS Praxis backup key\` and choose the file. The tool:
   - shows the backup (site, kind, date, size) and the key's fingerprint, and stops if the backup was made for another key;
   - checks the file's tag before trusting a byte, decrypts it, and checks the result against the checksum recorded when it was made;
   - for a database: writes `….sql.gz` and `….sql` (DEFINER clauses removed, so it imports under any user) and offers to import it into the PC's MySQL (Laragon's is found by itself) as `restored_<site>_<date>`;
   - for files: writes `….tar.gz` and offers to unpack it.
3. To put the database back on a server: create an empty database there and import the `.sql` (phpMyAdmin › Import for small ones; `mysql -u user -p database < file.sql` for any size). For files, copy each unpacked folder back to the same path under the account's home.

From a terminal: `Restore-Backup.ps1 -File <file> [-Import -Database <name> -MySqlPort 3306 -AskMySqlPassword] [-Extract] [-KeepDefiners] [-Key <private key>]`.
On a machine with PHP and this repository: `php artisan backups:decrypt <file> --key=<private key>` (never on the server: the private key is never there).

## 7. The file format, and opening a backup with plain OpenSSL

`App\Support\Backups\Envelope`. Each file gets its own random AES-256 key and MAC key; both travel inside the file, locked with RSA-OAEP (SHA-1, MGF1-SHA-1) to the owner's key. The data is streamed 1 MB at a time, so a file of any size never sits in memory.

| Bytes | What |
|---|---|
| 19 | `JBSPRAXIS-BACKUP-1` and a new line |
| 4 | length *h* of the header (big-endian) |
| *h* | header JSON: site, kind (`db` or `files`), name, date, database or folders, size and SHA-256 of the plain file, the key's fingerprint, the algorithms |
| 2 | length *w* of the locked keys (512 for a 4096-bit key) |
| *w* | RSA-OAEP of AES key (32 bytes) + MAC key (32 bytes) |
| 16 | the counter AES-256-CTR starts from (as `openssl enc -aes-256-ctr -iv`) |
| … | the data, as long as the plain file |
| 32 | HMAC-SHA-256 with the MAC key of every byte before it |

Without the tool (Linux, macOS or Git Bash, with OpenSSL):

```bash
f=praxis_db_2026-11-05.sql.gz.enc; key=backup-private-key.pem
h=$(od -An -tu4 --endian=big -j19 -N4 "$f" | tr -d ' ')
w=$(od -An -tu2 --endian=big -j$((23+h)) -N2 "$f" | tr -d ' ')
data=$((41+h+w)); len=$(( $(wc -c < "$f") - data - 32 ))
tail -c +$((26+h)) "$f" | head -c "$w" > locked.bin
openssl pkeyutl -decrypt -inkey "$key" -pkeyopt rsa_padding_mode:oaep -in locked.bin -out keys.bin
aes=$(head -c 32 keys.bin | od -An -tx1 | tr -d ' \n'); mac=$(tail -c 32 keys.bin | od -An -tx1 | tr -d ' \n')
iv=$(tail -c +$((26+h+w)) "$f" | head -c 16 | od -An -tx1 | tr -d ' \n')
# The tag first: the two lines must be the same, or the file was changed or damaged
head -c $((data+len)) "$f" | openssl dgst -sha256 -mac HMAC -macopt hexkey:"$mac" -binary | od -An -tx1 | tr -d ' \n'; echo
tail -c 32 "$f" | od -An -tx1 | tr -d ' \n'; echo
tail -c +$((data+1)) "$f" | head -c "$len" | openssl enc -d -aes-256-ctr -K "$aes" -iv "$iv" > "${f%.enc}"
rm -f keys.bin locked.bin
```

## 8. System health, bells, and what to do

- **System health › Server backups** (`App\Support\Backups\Status`): per site, the last database backup (OK / Failed / Running, time, size, on Drive), the weekly test ("Restore test passed / failed" with the rows imported, or "Dump check passed / failed" with the rows in the dump and "Not imported: no scratch database", in blue rather than green), the last files backup, and the site's status with the first reason. The **Server backups (Drive)** check is red when there is no key, Drive isn't allowed, a site's last backup failed or is older than 26 hours, or a weekly test failed; amber before the setup, while a site has no upload folders, or without any weekly test for over a week. A dump check in place of the import is not a fault: the check stays green and its detail says "The weekly test is a dump check, not an import". It is in the 8am summary like every check.
- **Bells to the owner** (protected role only; they open System health and are pushed to phones): a failed night or Sunday (one bell per run, naming the sites, "did not match on Drive" for a checksum mismatch); a database no site claims (once a month per database); a site with no good backup for 26 hours (`backups:check`, once a day per site); no files backup for 8 days (once a week).

| What you see | What to do |
|---|---|
| Not switched on yet | Section 4. |
| No backup key on the server | Section 3, then step 4.4. |
| Drive backups are not allowed | Integrations › Reconnect Google, tick every box. |
| Failed at `dump` | Read the error: a database the backup user can't read (run `backups:setup` again), a disk quota full, MySQL down. |
| Failed at `restore_test` | "Restore test failed": the dump didn't import or counts differ. "Dump check failed": the dump is damaged or cut short, has no closing line, or lacks a live table or view. Read the mismatches; the backup itself is still on Drive. Check the dump by restoring it on the PC (section 6). |
| Dump check passed, Not imported: no scratch database | Not a fault: the plan allows no more databases. When the host allows one more, run `backups:setup` again (section 4.2). |
| Setup log: Could not create the scratch database | The same; the setup still finished. |
| Failed at `upload`, `verify` or `monthly` | Drive or Google trouble; the encrypted file waits on the server in `~/backups/<site>/`. The next night uploads again. "Access withdrawn" means reconnect. |
| Failed at `archive` | A folder in `config/backups.php` doesn't exist on the server: fix the config. |
| No backup for 26 hours | The cron job or the scheduler stopped (see the Scheduler check), or the night's run died: `storage/logs/backups.log`. |
| A new database was backed up | Add it to its site in `config/backups.php` (section 2). Until then it is safe under `other/`. |

## 9. If the server is lost

1. Nothing is lost that was backed up: Drive has each database of the last 30 nights (and 12 months) and each site's files of the last 8 Sundays, and the owner's PC and password manager have the key.
2. Get a new host (a cPanel account, or any PHP 8.3 + MySQL/MariaDB host). Put each site's code back: HQ, jbspraxis.com and the portal from GitHub jbspraxis/platform (`deploy/server/` for the deployer), bureau.lpc.cm from its own repository, nfelia, hodlc and smartls from their repositories or copies.
3. For each site: restore its newest database into an empty database on the new host, and unpack its newest files backup into the same paths (section 6).
4. Recreate each site's `.env` from the password manager (database names and users will be new), point the domains' DNS at the new host, and check each site.
5. Switch the backups on there (section 4): the new account's user and database prefix go into `BACKUP_CPANEL_USER`, `BACKUP_MYSQL_USER`, `BACKUP_SCRATCH_DATABASE` and the patterns in `config/backups.php`.

If the owner's PC is lost, the private key in the password manager opens everything: copy it to `Documents\JBS Praxis backup key\backup-private-key.pem` on any Windows PC and use the restore tool from the repository. Without Bitwarden, the locked copy in the jbspraxisllc@gmail.com Drive opens offline with the passphrase (docs/KEY_VAULT.md sections 5 and 6). HQ's key vault is on the lost server, so it can't help here.

## 10. Testing and local runs

- `tests/Feature/Backups/ServerBackupsTest.php` (13 tests) runs the whole path on the test MySQL server with real `mysqldump`, `mysql` and `tar`: fictional databases standing in for the five sites (`praxis_test_bk_*`, with a view, a trigger, binary data, quotes and parentheses in strings), a throwaway home with fictional uploads, and `Tests\Support\Google\FakeDrive`, HTTP fakes that answer like Drive (folders, resumable chunks with 308 and Range, md5Checksum, copies, the bin). `BackupConfigTest` pins the live mapping (the five databases to their sites, no folder that is a whole docroot or the home, the archive exclusions). The end-to-end tests cover the night, a second night and a same-day rerun, retention on Drive and the server, a resumed upload, checksum mismatches, files with exclusions (caches, sessions, logs, archives, old backup copies; smartls.cm's two folders without the docroot around them) and a missing folder, no Drive permission, no key, before the setup, the 26-hour bell, System health and Integrations, `backups:setup` through a fake `uapi` that really creates the MySQL user, and a database granted on the night. `tests/Unit/Backups/EnvelopeTest.php` covers the encryption. `PlanLimitTest` covers the plan's database limit: the setup refused its scratch database (it finishes and says so), a rerun with the user and client file already there, the setup bringing the import test back once a database is allowed, and the dump check (passing and labelled as such in System health without a bell; a missing table, a cut or damaged `.gz`, a missing closing line; rows written after the dump shown, not failed).
- Locally (Laragon): `DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_USERNAME=root DB_PASSWORD= DB_DATABASE=praxis_test_… BACKUP_MYSQLDUMP=C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqldump.exe BACKUP_MYSQL=…/mysql.exe BACKUP_TAR=C:/Windows/System32/tar.exe OPENSSL_CONF=C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/extras/ssl/openssl.cnf php vendor/bin/phpunit tests/Feature/Backups tests/Unit/Backups`. Without `OPENSSL_CONF`, PHP on Windows can't make keys (`openssl_pkey_new` fails), which is also why the passkey tests fail locally. Git Bash's own `tar` misreads `C:/` paths: use Windows' `tar.exe`.
- A local run off the server: `BACKUP_DATABASES_FROM=mysql` with `BACKUP_DATABASE_PREFIX`, `BACKUP_HOME`, `BACKUP_MYSQL_CNF` (a client file you write by hand), `BACKUP_PUBLIC_KEY_FILE` and the program paths above.

## 11. Files

`app/Support/Backups/` (Backups, Setup, Status, Bells, Sites, MysqlClient, Cpanel, Dumper, RestoreTest, Archiver, Envelope, Keys, DriveStore, Readme, Programs, BackupFailed), `app/Console/Commands/Backups{Setup,Run,Files,Check,Decrypt}.php`, `config/backups.php`, migration `2026_11_04_960000_create_server_backups.php`, `resources/views/backups/readme.blade.php` (the PDF at the top of the Drive folder), `resources/backups/owner-public-key.pem` (once the owner has made the key), `deploy/backups/pc/` (New-BackupKey.ps1, Restore-Backup.ps1 and their .cmd launchers). Shared files touched: `GoogleWorkspace` (the `drive.file` scope, Drive calls; `missingScopes()` leaves Drive out so meetings never warn about it), `GoogleOAuthController` (a warning when Drive wasn't ticked), Integrations (screen and `lang/*/integrations.php`), `HealthChecks` and System health (the check and the table), `routes/console.php`, `.gitattributes` (CRLF for `.ps1` and `.cmd`).
