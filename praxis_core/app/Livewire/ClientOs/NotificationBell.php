<?php

namespace App\Livewire\ClientOs;

use App\Support\Pwa\ClientNotify;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The Client Portal's bell (docs/PWA.md, "Client Portal"): the client's latest notifications in their language.
 * Opening one goes through /inbox/open/{id}, which marks it read. The app script (hq-pwa.js) fills in the
 * app's actions (turn on notifications, install) and "This device"; a push that arrives reloads the bell.
 */
class NotificationBell extends Component
{
    use PortalLanguage;

    public array $items = [];

    public int $unreadTotal = 0;

    public function mount(): void
    {
        $client = Auth::guard('client')->user();
        $this->applyOrganisationLocale($client ? DB::table('organizations')->where('id', $client->organization_id)->value('preferred_language') : null);
        $this->load();
    }

    #[On('hq-push-received')]
    public function load(): void
    {
        $clientId = Auth::guard('client')->id();
        if (! $clientId || ! ClientNotify::ready()) {
            $this->items = [];
            $this->unreadTotal = 0;

            return;
        }

        $locale = app()->getLocale();
        $this->items = DB::table('client_notifications')->where('client_user_id', $clientId)
            ->orderByDesc('created_at')->limit(6)->get()
            ->map(function ($row) use ($locale) {
                [$title, $body] = ClientNotify::text($row->kind, $row->params, $locale);

                return [
                    'id' => $row->id,
                    'label' => __('portal.inbox.kinds.' . $row->kind . '.label'),
                    'title' => $title,
                    'body' => $body,
                    'read' => (bool) $row->is_read,
                    'when' => $row->created_at ? Carbon::parse($row->created_at)->locale($locale)->diffForHumans() : '',
                ];
            })->all();
        $this->unreadTotal = ClientNotify::unread($clientId);

        // The app icon's badge (hq-pwa.js)
        $this->dispatch('hq-unread', count: $this->unreadTotal);
    }

    public function markAllRead(): void
    {
        $clientId = Auth::guard('client')->id();
        if ($clientId && ClientNotify::ready()) {
            DB::table('client_notifications')->where('client_user_id', $clientId)->where('is_read', 0)->update(['is_read' => 1]);
        }
        $this->load();
    }

    protected function languageReturnUrl(): string
    {
        return route('client.dashboard');
    }

    public function render()
    {
        return view('livewire.client-os.notification-bell');
    }
}
