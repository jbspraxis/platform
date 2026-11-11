<?php

namespace App\Support\Portal;

use App\Mail\UserInvitation;
use App\Support\MailLocale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * After a client signs (moved out of Portal\ProposalGateway for the SOW, PR 5): the signer's contact (else the
 * organisation's first) becomes the primary portal account, Onboarding, with a fresh setup link; a contact who has
 * never set a password gets the setup email, in the language they chose on the site or the organisation's.
 */
final class SignerOnboarding
{
    public static function welcome(object $organization, string $signerEmail, string $title, $timestamp): void
    {
        $clientUser = DB::table('client_users')->where('organization_id', $organization->id)->where('email', $signerEmail)->whereNull('deleted_at')->first()
            ?? DB::table('client_users')->where('organization_id', $organization->id)->whereNull('deleted_at')->orderBy('created_at')->first();
        if (! $clientUser) {
            return;
        }

        $invitationToken = Str::random(60);
        DB::table('client_users')->where('id', $clientUser->id)->update([
            'invitation_token' => $invitationToken,
            'lifecycle_stage' => 'Onboarding',
            'is_primary_client' => 1,
            'updated_at' => $timestamp,
        ]);

        // Only contacts who have never set a password get the setup email; a returning client already has access
        if ($clientUser->password === null) {
            // Sent once the signing's transaction is saved (at once when there is none): the rows it locks are not
            // held while the mail server answers, and a signing that is rolled back sends no setup link
            DB::afterCommit(function () use ($organization, $clientUser, $invitationToken, $title) {
                try {
                    $locale = session()->has('locale') ? MailLocale::forVisitor() : MailLocale::forRecipient($organization->preferred_language ?? null);
                    Mail::to($clientUser->email)->locale($locale)->send(new UserInvitation($clientUser->first_name, $invitationToken, true, $title));
                } catch (\Exception $e) {
                    // Never the setup link itself: anyone who can read the log could take the account. HQ can send a new one.
                    Log::warning('Portal setup email failed after signing', ['client_user_id' => $clientUser->id, 'message' => $e->getMessage()]);
                }
            });
        }
    }
}
