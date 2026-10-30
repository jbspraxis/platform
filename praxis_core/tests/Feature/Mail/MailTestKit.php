<?php

namespace Tests\Feature\Mail;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailAccountMember;
use App\Models\User;
use App\Support\Access;
use App\Support\Mail\Autodiscover;
use App\Support\Mail\MailAccess;
use App\Support\Mail\MailCredentials;
use App\Support\Mail\MailSettings;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Mail\FakeMailServer;

/**
 * Shared fixtures for HQ Mail feature tests (all four PRs use it; docs/MAIL.md, "Testing"). Needs MySQL:
 *   bash ../../jbspraxis_project/_localtest/mail-test.sh <pr1|pr2|pr3|pr4> [phpunit args]
 */
trait MailTestKit
{
    use UsesThrowawayDatabase;

    protected FakeMailServer $server;

    protected function setUpMail(): void
    {
        $this->server = FakeMailServer::install();
        MailAccess::flush();
        Access::flush();
    }

    protected function openMailToEveryone(): void
    {
        MailSettings::set('open_to', 'everyone');
        MailAccess::flush();
    }

    /** A connected mailbox (no IMAP check), with the fake server holding the same password */
    protected function mailbox(string $email, string $kind = 'shared', ?User $owner = null, string $password = 'secret-pass'): MailAccount
    {
        if (! isset($this->server->mailboxes[strtolower($email)])) {
            $this->server->addMailbox($email, $password);
        }

        return MailAccount::create([
            'kind' => $kind,
            'email' => strtolower($email),
            'display_name' => $kind === 'shared' ? 'JBS Praxis Intelligence' : ($owner?->name ?? 'Someone'),
            'owner_user_id' => $owner?->id,
            'username' => strtolower($email),
            'secret' => MailCredentials::encrypt($password),
            'status' => 'active',
        ] + Autodiscover::for($email));
    }

    protected function member(MailAccount $account, User $user, string $level): MailAccountMember
    {
        MailAccess::flush();

        return MailAccountMember::create(['account_id' => $account->id, 'user_id' => $user->id, 'level' => $level]);
    }
}
