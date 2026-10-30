<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Opening a Client Portal notification (from the bell or a push): the client's own notification is marked
 * read and they land on its screen in the portal. Anyone else's, or an unknown id, lands on the dashboard.
 */
class ClientInboxController extends Controller
{
    public function __invoke(string $notification)
    {
        $row = DB::table('client_notifications')->where('id', $notification)->where('client_user_id', Auth::guard('client')->id())->first();
        if (! $row) {
            return redirect()->route('client.dashboard');
        }

        DB::table('client_notifications')->where('id', $row->id)->update(['is_read' => 1]);

        // Only a path on the portal itself ("/?tab=financials", "/proposal/…"), never another site
        $link = (string) $row->link_url;

        return redirect(str_starts_with($link, '/') && ! str_starts_with($link, '//') && ! str_contains($link, '\\') ? $link : route('client.dashboard', [], false));
    }
}
