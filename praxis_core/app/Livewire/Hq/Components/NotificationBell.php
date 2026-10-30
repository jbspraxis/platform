<?php

namespace App\Livewire\Hq\Components;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The bell in HQ's top bar: the person's 6 latest notifications and the exact unread total. Every load sends
 * the total to the page (browser event hq-unread), where hq-pwa.js sets the app icon badge; a push that arrives
 * while HQ is open reloads the bell (Livewire event hq-push-received). docs/PWA.md
 */
class NotificationBell extends Component
{
    public $notifications = [];
    public $unreadCount = 0;

    public function mount()
    {
        $this->loadNotifications();
    }

    #[On('hq-push-received')]
    public function loadNotifications()
    {
        $userId = Auth::id();
        if (! $userId) {
            $this->notifications = collect();
            $this->unreadCount = 0;

            return;
        }

        try {
            $this->notifications = DB::table('notifications')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(6)
                ->get();

            // The total, not just the unread ones among the 6 shown
            $this->unreadCount = DB::table('notifications')->where('user_id', $userId)->where('is_read', 0)->count();
        } catch (\Exception $e) {
            $this->notifications = collect();
            $this->unreadCount = 0;

            return;
        }

        $this->dispatch('hq-unread', count: $this->unreadCount);
    }

    /** Opens a notification: marks it read (only the person's own) and goes to its screen. */
    public function markAndRedirect($id)
    {
        $note = null;
        try {
            $own = fn () => DB::table('notifications')->where('id', (string) $id)->where('user_id', Auth::id());
            $note = $own()->first(['id', 'link_url']);
            if ($note) {
                $own()->update(['is_read' => 1]);
            }
        } catch (\Exception $e) {
            $note = null;
        }

        $link = $note ? self::safeLink($note->link_url) : null;
        if ($link) {
            // Same host: change page in place; another host (an old link): load it normally
            $host = parse_url($link, PHP_URL_HOST);

            return $this->redirect($link, navigate: ! $host || $host === request()->getHost());
        }

        $this->loadNotifications();
    }

    /** A stored link as something safe to open: a path on this host or an http(s) address, never a script. */
    private static function safeLink(?string $link): ?string
    {
        $link = trim((string) $link);
        if ($link === '' || $link === '#') {
            return null;
        }
        if (str_starts_with($link, '/') && ! str_starts_with($link, '//')) {
            return $link;
        }

        return in_array(strtolower((string) parse_url($link, PHP_URL_SCHEME)), ['http', 'https'], true) ? $link : null;
    }

    public function render()
    {
        return view('livewire.hq.components.notification-bell');
    }
}
