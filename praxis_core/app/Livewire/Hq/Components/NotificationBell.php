<?php

namespace App\Livewire\Hq\Components;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class NotificationBell extends Component
{
    public $notifications = [];
    public $unreadCount = 0;

    public function mount()
    {
        $this->loadNotifications();
    }

    public function loadNotifications()
    {
        try {
            $userId = Auth::id() ?? '3f0bd9fe-1ab7-11f1-ab8b-44a84221081c';

            $this->notifications = DB::table('notifications')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(6)
                ->get();
                
            $this->unreadCount = $this->notifications->where('is_read', 0)->count();
        } catch (\Exception $e) {
            $this->notifications = collect([]);
            $this->unreadCount = 0;
        }
    }

    public function markAndRedirect($id, $url = '#')
    {
        try {
            DB::table('notifications')->where('id', $id)->update(['is_read' => 1]);
        } catch (\Exception $e) {}

        if ($url !== '#' && !empty($url)) {
            return $this->redirect($url, navigate: true);
        }

        $this->loadNotifications(); 
    }

    public function render()
    {
        return view('livewire.hq.components.notification-bell');
    }
}