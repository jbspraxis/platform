<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Keeps /hq for JBS Praxis staff only.
 *
 * Staff are active accounts in the users table (clients are client_users and sign in
 * with the 'client' guard, so they never reach this check as staff). With a parameter
 * (hq.staff:4) the route also needs that clearance level, which keeps finance, payroll,
 * master data, CRM and role management with senior staff.
 */
class HqStaff
{
    public function handle(Request $request, Closure $next, ?string $minClearance = null)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        if (! self::allows(Auth::user(), $minClearance === null ? null : (int) $minClearance)) {
            abort(403, 'This area is for JBS Praxis staff only.');
        }

        return $next($request);
    }

    public static function allows($user, ?int $minClearance = null): bool
    {
        // Only staff accounts (users table); a client account (ClientUser) is never staff
        if (! $user instanceof \App\Models\User || $user->status !== 'Active') {
            return false;
        }

        if ($minClearance === null) {
            return true;
        }

        $clearance = (int) (DB::table('roles')->where('id', $user->role_id)->value('clearance_level') ?? 0);

        return $clearance >= $minClearance;
    }
}
