<?php

namespace App\Livewire\Portal\Concerns;

use App\Support\MailLocale;
use Illuminate\Support\Facades\App;

/**
 * The language of a client's token page (docs/BRAND.md section 4): a language the person chose wins;
 * otherwise the organisation's preferred language, else English. Only a choice goes in the session:
 * the organisation's language is applied per page, so another organisation's link (or a changed
 * preference) is not overridden by an earlier visit. Livewire keeps each component's language
 * between its own update requests.
 */
trait WithGatewayLanguage
{
    /** Call from mount() with the organisation's preferred_language (or null when it is unknown). */
    protected function useGatewayLanguage(?string $preference): void
    {
        $chosen = session('locale');

        App::setLocale(in_array($chosen, ['en', 'fr'], true) ? $chosen : (MailLocale::parse($preference) ?? 'en'));
    }

    /** The "English | Français" switch in the page header: remember the choice and reload the page. */
    public function switchLanguage(string $locale): void
    {
        if (!in_array($locale, ['en', 'fr'], true)) {
            return;
        }

        session(['locale' => $locale]);
        App::setLocale($locale);
        $this->js('window.location.reload()');
    }
}
