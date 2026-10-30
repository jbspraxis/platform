<?php

namespace App\Support\Mail;

use App\Models\Mail\MailAccount;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Mailbox passwords, encrypted with APP_KEY in mail_accounts.secret. Only MailServer reads them back.
 * Never put a password in a log, an exception, a Livewire property or a notification.
 */
final class MailCredentials
{
    public static function encrypt(string $password): string
    {
        return Crypt::encryptString($password);
    }

    public static function password(MailAccount $account): string
    {
        try {
            return Crypt::decryptString((string) $account->secret);
        } catch (DecryptException) {
            // APP_KEY changed: the person has to enter the password again
            throw new \App\Support\Mail\Imap\ImapException('The saved password can no longer be read. Enter it again.', 'auth');
        }
    }
}
