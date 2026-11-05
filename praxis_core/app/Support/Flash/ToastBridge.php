<?php

namespace App\Support\Flash;

use Livewire\ComponentHook;

/**
 * Carries flash messages out of a Livewire update and into the toast in the corner.
 *
 * A page load renders its messages itself: <x-flash> sits in the layout and draws whatever is waiting. A Livewire
 * update never re-renders the layout, so a message flashed by wire:click would sit in the session unseen until the
 * next page load. This hook picks it up the moment the action returns and dispatches it to the browser instead, so
 * every screen confirms what it did without having to ask for it. Registered in App\Providers\AppServiceProvider.
 *
 * The one case it keeps its hands off is an action that redirects: the message belongs to the page being opened,
 * and Livewire deliberately keeps the flash alive across a redirect for that page to show.
 */
class ToastBridge extends ComponentHook
{
    /** The browser event the toast host listens for (resources/js/toasts.js). */
    public const EVENT = 'praxis-toast';

    /** After a wire:click or any other action method. */
    public function call($method, $params, $returnEarly, $metadata, $componentContext)
    {
        return fn () => $this->send();
    }

    /** After a wire:model change, in case the screen confirms it (an updated... hook may flash). */
    public function update($propertyName, $fullPath, $newValue)
    {
        return fn () => $this->send();
    }

    private function send(): void
    {
        // The next page is about to show them itself
        if ($this->storeGet('redirect')) {
            return;
        }

        foreach (Toasts::pull() as $toast) {
            $this->component->dispatch(self::EVENT, message: $toast['message'], tone: $toast['tone']);
        }
    }
}
