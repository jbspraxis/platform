<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class LanguageSwitcher extends Component
{
    public $currentLocale;

    public function mount()
    {
        // Detect current language or default to English
        $this->currentLocale = Session::get('locale', config('app.locale', 'en'));
    }

    public function switchLanguage($locale)
    {
        // Strict Security Gateway: Only allow approved languages
        if (!in_array($locale, ['en', 'fr'])) {
            abort(400, 'UNAUTHORIZED_LOCALE');
        }

        // Lock the language into the user's session
        Session::put('locale', $locale);
        App::setLocale($locale);

        // Force a hard refresh to re-render all Blade files and DB queries
        return redirect(request()->header('Referer') ?? '/');
    }

    public function render()
    {
        return view('livewire.language-switcher');
    }
}