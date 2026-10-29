<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Keeps /hq for JBS Praxis staff only: active accounts in the users table (clients are
 * client_users and sign in with the 'client' guard). What each staff member may open
 * or change is decided by their roles: routes use can:<module>.view, components check
 * $user->can('<module>.<action>') (config/access.php).
 */
class HqStaff
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        if (! self::allows(Auth::user())) {
            abort(403, 'This area is for JBS Praxis staff only.');
        }

        return $next($request);
    }

    public static function allows($user): bool
    {
        // Only staff accounts (users table); a client account (ClientUser) is never staff
        return $user instanceof \App\Models\User && $user->status === 'Active';
    }
}
