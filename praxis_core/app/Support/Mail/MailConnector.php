<?php

namespace App\Support\Mail;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailAccountMember;
use App\Models\User;
use App\Support\Mail\Imap\ImapException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Connecting mailboxes (docs/MAIL.md, "Connect"): every connect, reconnect and password change signs in to
 * both IMAP and SMTP first, so nothing is saved that doesn't work. Errors come back as ValidationException
 * on the field to fix (password, imap_host, smtp_host), in plain words. Everything is written to the audit log.
 */
final class MailConnector
{
    public const IMAP_PORTS = [993, 143];

    public const SMTP_PORTS = [465, 587, 25, 2525];

    public function __construct(private readonly MailServer $server)
    {
    }

    public static function make(): self
    {
        return app(self::class);
    }

    /** Connect the person's own mailbox (one per person). */
    public function connectPersonal(User $user, string $email, string $password, array $settings, ?string $displayName = null): MailAccount
    {
        abort_unless(MailAccess::canUse($user), 403);
        $email = self::cleanEmail($email);

        $existing = MailAccount::where('kind', 'personal')->where('owner_user_id', $user->id)->first();
        if ($existing && strcasecmp($existing->email, $email) !== 0) {
            throw ValidationException::withMessages(['email' => 'You already have a mailbox connected. Disconnect it first to connect another one.']);
        }
        $this->ensureFree($email, $existing?->id);

        $settings = Autodiscover::normalise($settings);
        $this->verify($settings, $email, $password);

        $account = $existing ?? new MailAccount(['kind' => 'personal', 'owner_user_id' => $user->id]);
        $account->fill([
            'email' => $email,
            'display_name' => self::cleanName($displayName ?: $user->name),
            'username' => $email,
            'secret' => MailCredentials::encrypt($password),
            'status' => 'active',
            'last_error' => null,
        ] + $settings)->save();

        self::audit($user, $existing ? 'MAIL_MAILBOX_RECONNECTED' : 'MAIL_MAILBOX_CONNECTED', "Connected their mailbox {$email} to HQ Mail.", $account);
        MailAccess::flush();

        return $account;
    }

    /** Add a shared mailbox; the person adding it becomes its first manager. */
    public function addShared(User $user, string $email, string $password, array $settings, string $displayName): MailAccount
    {
        abort_unless(MailAccess::canAddShared($user), 403);
        $email = self::cleanEmail($email);
        $this->ensureFree($email, null);

        $settings = Autodiscover::normalise($settings);
        $this->verify($settings, $email, $password);

        $account = DB::transaction(function () use ($user, $email, $password, $settings, $displayName) {
            $account = MailAccount::create([
                'kind' => 'shared',
                'email' => $email,
                'display_name' => self::cleanName($displayName),
                'owner_user_id' => $user->id,
                'username' => $email,
                'secret' => MailCredentials::encrypt($password),
                'status' => 'active',
            ] + $settings);
            MailAccountMember::create(['account_id' => $account->id, 'user_id' => $user->id, 'level' => 'manage', 'added_by' => $user->id]);

            return $account;
        });

        self::audit($user, 'MAIL_SHARED_ADDED', "Added the shared mailbox {$email} to HQ Mail.", $account);
        MailAccess::flush();

        return $account;
    }

    /** New password (and optionally server settings) for a mailbox: needs manage (or the person's own). */
    public function updatePassword(User $user, MailAccount $account, string $password, ?array $settings = null): void
    {
        MailAccess::authorize($user, $account, 'manage');
        $settings = $settings ? Autodiscover::normalise($settings) : MailServer::settingsOf($account);
        $this->verify($settings, $account->username, $password);

        $account->fill(['secret' => MailCredentials::encrypt($password), 'status' => 'active', 'last_error' => null] + $settings)->save();
        self::audit($user, 'MAIL_PASSWORD_UPDATED', "Updated the password of {$account->email} in HQ Mail.", $account);
    }

    /** Rename a shared mailbox (the name people see in the From line) */
    public function rename(User $user, MailAccount $account, string $displayName): void
    {
        MailAccess::authorize($user, $account, 'manage');
        $name = self::cleanName($displayName);
        if ($name === '') {
            throw ValidationException::withMessages(['display_name' => 'Enter the name people see when this mailbox writes to them.']);
        }
        $old = $account->display_name;
        $account->update(['display_name' => $name]);
        self::audit($user, 'MAIL_MAILBOX_RENAMED', "Renamed {$account->email} from \"{$old}\" to \"{$name}\".", $account, ['display_name' => $old], ['display_name' => $name]);
    }

    /**
     * Take a mailbox out of HQ: its copy here, its people, its conversations' notes and assignments go; the mail
     * on the server is not touched. A personal mailbox: its person; a shared one: a manager.
     */
    public function remove(User $user, MailAccount $account): void
    {
        MailAccess::authorize($user, $account, 'manage');
        $email = $account->email;
        $kind = $account->kind;
        self::audit($user, $kind === 'shared' ? 'MAIL_SHARED_REMOVED' : 'MAIL_MAILBOX_DISCONNECTED',
            $kind === 'shared' ? "Removed the shared mailbox {$email} from HQ Mail (the mail stays on the server)." : "Disconnected their mailbox {$email} from HQ Mail.",
            $account);
        $account->delete(); // everything below it cascades
        MailAccess::flush();
    }

    /** Sign in to IMAP and SMTP with these settings, or throw a ValidationException that says what to fix. */
    public function verify(array $settings, string $username, string $password): void
    {
        if ($settings['imap_host'] === '' || $settings['smtp_host'] === '') {
            throw ValidationException::withMessages(['imap_host' => 'Enter the incoming and outgoing server names (for example mail.jbspraxis.com).']);
        }
        // Mail ports only: HQ must not become a way to probe other services
        if (! in_array($settings['imap_port'], self::IMAP_PORTS, true)) {
            throw ValidationException::withMessages(['imap_host' => 'The incoming port must be ' . implode(' or ', self::IMAP_PORTS) . '.']);
        }
        if (! in_array($settings['smtp_port'], self::SMTP_PORTS, true)) {
            throw ValidationException::withMessages(['smtp_host' => 'The outgoing port must be ' . implode(', ', self::SMTP_PORTS) . '.']);
        }
        foreach (['imap_host', 'smtp_host'] as $key) {
            if (! preg_match('/^(?=.{1,190}$)([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', $settings[$key])) {
                throw ValidationException::withMessages([$key => 'Enter a server name like mail.jbspraxis.com.']);
            }
        }

        try {
            $session = $this->server->connect($settings, $username, $password);
            $session->logout();
        } catch (ImapException $e) {
            throw ValidationException::withMessages(match ($e->reason) {
                'auth' => ['password' => 'The mail server refused this address or password. Check the password (the one for this mailbox in cPanel, not your HQ password).'],
                'tls' => ['imap_host' => "A secure connection to {$settings['imap_host']} could not be made. Check the incoming server and its security setting."],
                'unreachable', 'timeout' => ['imap_host' => "{$settings['imap_host']} could not be reached on port {$settings['imap_port']}. Check the incoming server and port."],
                default => ['imap_host' => 'The incoming mail server answered something unexpected. Check the incoming server settings.'],
            });
        }

        try {
            $this->server->checkSmtp($settings, $username, $password);
        } catch (MailSendException $e) {
            throw ValidationException::withMessages(match ($e->reason) {
                'auth' => ['password' => 'Signing in to receive mail worked, but the outgoing server refused the password. Check the outgoing server settings.'],
                'unreachable' => ['smtp_host' => "{$settings['smtp_host']} could not be reached on port {$settings['smtp_port']}. Check the outgoing server and port."],
                default => ['smtp_host' => 'The outgoing mail server could not be used: ' . $e->getMessage()],
            });
        }
    }

    private function ensureFree(string $email, ?string $exceptId): void
    {
        $taken = MailAccount::where('email', $email)->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))->first();
        if ($taken) {
            throw ValidationException::withMessages(['email' => $taken->isShared()
                ? "{$email} is already in HQ as a shared mailbox. Ask one of its managers to give you access."
                : "{$email} is already connected by someone in HQ."]);
        }
    }

    public static function cleanEmail(string $email): string
    {
        $email = strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'Enter a full email address, for example intelligence@jbspraxis.com.']);
        }

        return $email;
    }

    private static function cleanName(string $name): string
    {
        return mb_substr(trim(preg_replace('/[\r\n"\\\\<>]+/', ' ', $name)), 0, 120);
    }

    public static function audit(User $user, string $action, string $description, MailAccount $account, ?array $old = null, ?array $new = null): void
    {
        DB::table('system_audit_logs')->insert([
            'user_id' => $user->id,
            'action' => $action,
            'description' => $description,
            'target_table' => 'mail_accounts',
            'target_id' => $account->id,
            'old_values' => $old !== null ? json_encode($old) : null,
            'new_values' => $new !== null ? json_encode($new) : null,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
