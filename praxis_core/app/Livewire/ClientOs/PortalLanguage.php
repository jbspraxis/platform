<?php

namespace App\Livewire\ClientOs;

use App\Support\MailLocale;
use Illuminate\Support\Facades\App;

/**
 * The language of the Client Portal pages (docs/BRAND.md section 4): English and French.
 *
 * A language the person chose (session 'locale') always wins. Otherwise the organisation's preferred
 * language is applied on each page that knows the organisation; it is not stored in the session, so
 * a changed preference is picked up. Livewire keeps the locale of each component between its own requests.
 */
trait PortalLanguage
{
    private static array $portalLocales = ['en', 'fr'];

    /** The person's chosen language, if it is one the portal has */
    protected function chosenPortalLocale(): ?string
    {
        $locale = session('locale');

        return in_array($locale, self::$portalLocales, true) ? $locale : null;
    }

    /** Session choice, else the organisation's preference, else English */
    protected function applyOrganisationLocale(?string $organisationPreference): void
    {
        App::setLocale($this->chosenPortalLocale() ?? MailLocale::parse($organisationPreference) ?? 'en');
    }

    /** The "English | Français" switch: remember the choice and reload the page in that language */
    public function switchLanguage($locale)
    {
        if (!in_array($locale, self::$portalLocales, true)) {
            return;
        }
        session(['locale' => $locale]);
        App::setLocale($locale);

        return $this->redirect($this->languageReturnUrl());
    }

    /** Where the page reloads after a language change */
    abstract protected function languageReturnUrl(): string;
}
