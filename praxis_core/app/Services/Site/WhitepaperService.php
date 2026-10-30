<?php

namespace App\Services\Site;

use App\Mail\PraxisFrameworkDispatch;
use App\Support\MailLocale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The method PDF by email: POST /whitepaper on the site API.
 * Limits (docs/SITE_API.md section 6.2): 3 requests a day per visitor, 1 a day per email, 60 an hour
 * for everyone, on the old page's limiter key; the email itself counts towards the address's 3 visitor
 * emails a day.
 */
final class WhitepaperService
{
    public static function rules(): array
    {
        return ['email' => 'required|email|max:100'];
    }

    /**
     * Records the request (validated with rules())
     *
     * @param  string|null  $ip  the visitor's IP (limits)
     *
     * @throws LimitReached
     */
    public function record(string $email, ?string $ip = null): void
    {
        $limitIds = ['email' => strtolower(trim($email))];
        SiteLimits::check('method_pdf', $ip, $limitIds);

        DB::table('whitepaper_downloads')->insert([
            'email_captured' => $email,
            'document_name' => 'The Praxis Method',
        ]);

        SiteLimits::hit('method_pdf', $ip, $limitIds);
    }

    /**
     * Sends the method PDF in the visitor's page language (the email and the guide's edition); false (and logged)
     * if the email could not be sent or the address has had enough email today. On the site API it goes after the
     * response: true then means it was accepted for sending.
     */
    public function send(string $email, bool $afterResponse = false): bool
    {
        if (!SiteLimits::allowVisitorEmail($email)) {
            return false;
        }

        $locale = MailLocale::forVisitor();
        $send = function () use ($email, $locale): bool {
            try {
                Mail::to($email)
                    ->locale($locale)
                    ->send(new PraxisFrameworkDispatch());

                return true;
            } catch (\Exception $e) {
                Log::error('Whitepaper email failed', ['message' => $e->getMessage()]);

                return false;
            }
        };

        if ($afterResponse) {
            Deferred::run(true, fn () => $send());

            return true;
        }

        return $send();
    }
}
