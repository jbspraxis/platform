<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>READ ME FIRST – how to restore</title>
    <style>
        @page { margin: 48px 56px 56px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; line-height: 1.45; color: #1f2a44; }
        h1 { font-size: 17pt; margin: 0 0 4px; color: #0c142b; }
        h2 { font-size: 11.5pt; margin: 18px 0 6px; color: #0c142b; }
        p { margin: 0 0 7px; }
        ol, ul { margin: 0 0 8px; padding-left: 18px; }
        li { margin-bottom: 3px; }
        .lead { color: #5e6679; margin-bottom: 14px; }
        .box { border: 1px solid #d5dae3; background: #f4f6f9; padding: 8px 10px; margin: 8px 0; }
        code, .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 8.5pt; }
        table { border-collapse: collapse; width: 100%; margin: 6px 0 8px; }
        td, th { border: 1px solid #d5dae3; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f4f6f9; }
    </style>
</head>
<body>
    <h1>READ ME FIRST – how to restore</h1>
    <p class="lead">The JBS Praxis server backups: every database of the cPanel account <code>{{ config('backups.cpanel_user') }}</code> each night, every site’s uploaded files each Sunday. Everything here is encrypted: only the owner’s private key opens it.</p>

    <h2>1. What is in this folder</h2>
    <table>
        <tr><th>Folder</th><th>What</th><th>Kept</th></tr>
        @foreach ($sites as $key => $site)
            <tr><td class="mono">{{ $key }}/</td><td>{{ $site['label'] }}</td><td>daily/ the last {{ $retention['daily'] }} nights · monthly/ the first of each month, the last {{ $retention['monthly'] }} · files/ each Sunday, the last {{ $retention['files'] }}</td></tr>
        @endforeach
        <tr><td class="mono">other/</td><td>Only if a database belongs to none of the sites above: one folder per database.</td><td>daily/ and monthly/</td></tr>
    </table>
    <p>Names start with the site and the date: <code>praxis_db_2026-11-05.sql.gz.enc</code> is HQ’s database on the night of 5 November 2026; <code>praxis_files_2026-11-08.tar.gz.enc</code> its uploaded files that Sunday. A site with two databases adds the database’s name after the date.</p>

    <h2>2. What you need</h2>
    <ul>
        <li><strong>The private key</strong>, <code>backup-private-key.pem</code>: in <code>Documents\JBS Praxis backup key\</code> on the owner’s PC, and in the owner’s password manager. Without it, nobody can open these files: not Google, not JBS Praxis, not the host.</li>
        <li><strong>The restore tool</strong> for Windows, <code>Restore-Backup.ps1</code> and its launcher <code>Restore a backup.cmd</code>: in the same key folder, and in the repository jbspraxis/platform under <code>deploy/backups/pc/</code>. It needs nothing installed.</li>
        @if ($fingerprint)
            <li>The key these backups were made for has the fingerprint <code>{{ $fingerprint }}</code>. The restore tool shows the fingerprint of the key you give it.</li>
        @endif
    </ul>

    <h2>3. Restore a database</h2>
    <ol>
        <li>In this folder, open the site, then <code>daily/</code> (or <code>monthly/</code> for an older month), and download the night you want.</li>
        <li>Double-click <code>Restore a backup.cmd</code> in the key folder and choose the downloaded file. The tool checks that the file is intact and unchanged, opens it, and writes the <code>.sql</code> next to it.</li>
        <li>To look inside on the PC, let the tool import it into the PC’s MySQL (Laragon) when it asks. To put a site back on a server, import the <code>.sql</code> into an empty database there (phpMyAdmin › Import, or <code>mysql -u user -p database &lt; file.sql</code>).</li>
    </ol>

    <h2>4. Restore uploaded files</h2>
    <ol>
        <li>Download the Sunday you want from the site’s <code>files/</code> and open it with the same tool: it writes a <code>.tar.gz</code>.</li>
        <li>Unpack it (<code>tar -xzf file.tar.gz</code>; the tool offers to). Paths start from the account’s home folder, e.g. <code>praxis_core/storage/app/private/…</code>: copy each folder back to the same place.</li>
    </ol>

    <h2>5. If the server is lost</h2>
    <ol>
        <li>Get a new cPanel account (or any PHP + MySQL host) and the code: HQ, the website and the portal from GitHub jbspraxis/platform, bureau.lpc.cm from its own repository, the other sites from their copies.</li>
        <li>For each site, restore its last database (section 3) and its last files (section 4).</li>
        <li>Recreate each site’s <code>.env</code> settings from the password manager, and point the domains at the new server.</li>
        <li>Switch the backups on again on the new server (repository: <code>docs/BACKUPS.md</code>, section 4).</li>
    </ol>

    <h2>6. Without the tool</h2>
    <div class="box">
        <p>Each file is: the text <code>JBSPRAXIS-BACKUP-1</code> and a new line; a 4-byte length and a JSON header; a 2-byte length and the file’s keys locked with RSA-OAEP (SHA-1) to the owner’s key; a 16-byte counter; the data in AES-256-CTR; and a 32-byte HMAC-SHA-256 tag of everything before it. With OpenSSL: <code>openssl pkeyutl -decrypt -inkey backup-private-key.pem -pkeyopt rsa_padding_mode:oaep</code> on the locked keys gives the AES key (first 32 bytes) and the MAC key (last 32); <code>openssl enc -d -aes-256-ctr -K &lt;AES key&gt; -iv &lt;counter&gt;</code> on the data gives the <code>.gz</code>. The repository’s <code>docs/BACKUPS.md</code> has the full recipe.</p>
    </div>
    <p>Folder: {{ $root }}. Made by HQ’s nightly backup (docs/BACKUPS.md in jbspraxis/platform).</p>
</body>
</html>
