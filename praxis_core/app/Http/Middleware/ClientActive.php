<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Signs a client out as soon as their account is suspended. The database session
 * store only records staff ids, so a suspension cannot delete a client's sessions;
 * this check runs on every portal request (and Livewire update) instead.
 */
class ClientActive
{
    public function handle(Request $request, Closure $next)
    {
        $client = Auth::guard('client')->user();

        if ($client && $client->status !== 'Active') {
            Auth::guard('client')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('client.login');
        }

        return $next($request);
    }
}
