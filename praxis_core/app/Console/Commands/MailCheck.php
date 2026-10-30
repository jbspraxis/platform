<?php

namespace App\Console\Commands;

use App\Support\Mail\Autodiscover;
use App\Support\Mail\Imap\ImapException;
use App\Support\Mail\MailSendException;
use App\Support\Mail\MailServer;
use App\Support\Mail\Sync\FolderRoles;
use Illuminate\Console\Command;

/**
 * Local diagnosis (docs/MAIL.md): sign in to a mailbox with the password typed at a hidden prompt, list what
 * the server offers and which folder HQ would treat as Inbox, Sent, … Saves nothing.
 */
class MailCheck extends Command
{
    protected $signature = 'mail:check {email : The mailbox address} {--host= : Server name, if not the usual one for the domain}';

    protected $description = 'HQ Mail: test signing in to a mailbox (IMAP and SMTP) without saving anything';

    public function handle(MailServer $server): int
    {
        $email = strtolower(trim($this->argument('email')));
        $settings = Autodiscover::for($email);
        if ($this->option('host')) {
            $settings['imap_host'] = $settings['smtp_host'] = $this->option('host');
        }
        $password = (string) $this->secret("Password for {$email}");

        $this->line("IMAP {$settings['imap_host']}:{$settings['imap_port']} ({$settings['imap_encryption']})");
        try {
            $session = $server->connect($settings, $email, $password);
            $this->info('Signed in. Capabilities: ' . implode(' ', $session->capabilities()));
            $folders = $session->folders();
            $roles = FolderRoles::assign($folders);
            foreach ($folders as $f) {
                $this->line(sprintf('  %-10s %s  [%s]', $roles[$f['path']], $f['path'], implode(' ', $f['attributes'])));
            }
            $inbox = $session->examine('INBOX');
            $this->line("  INBOX: {$inbox['exists']} messages, UIDVALIDITY {$inbox['uidvalidity']}, UIDNEXT {$inbox['uidnext']}, HIGHESTMODSEQ " . ($inbox['highestmodseq'] ?? '-'));
            $session->logout();
        } catch (ImapException $e) {
            $this->error("IMAP: {$e->reason}: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->line("SMTP {$settings['smtp_host']}:{$settings['smtp_port']} ({$settings['smtp_encryption']})");
        try {
            $server->checkSmtp($settings, $email, $password);
            $this->info('Signed in to SMTP.');
        } catch (MailSendException $e) {
            $this->error("SMTP: {$e->reason}: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
