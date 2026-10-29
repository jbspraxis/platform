<?php

namespace App\Livewire\Hq\Components;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class Topbar extends Component
{
    public $notifications = [];
    public $unreadCount = 0;

    public function mount()
    {
        $this->loadNotifications();
    }

    public function loadNotifications()
    {
        $this->notifications = DB::table('notifications')
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();
            
        $this->unreadCount = $this->notifications->where('is_read', 0)->count();
    }

    public function markAsRead($id)
    {
        DB::table('notifications')->where('id', $id)->update(['is_read' => 1]);
        $this->loadNotifications(); // Refresh the list and count
    }

    public function render()
    {
        return view('livewire.hq.components.topbar');
    }
}