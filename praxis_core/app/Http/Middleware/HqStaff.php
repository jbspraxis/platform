<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Keeps /hq for JBS Praxis staff only.
 *
 * Staff are active users whose role has clearance 4 or more, or who are on the
 * internal team (lifecycle_stage 'Strike Team'). Clients and leads share the
 * same 'web' login, so 'auth' alone is not enough. With a parameter
 * (hq.staff:4) the route also needs that clearance level, which keeps finance,
 * payroll, master data, CRM and role management with senior staff.
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
        if (! $user || $user->status !== 'Active') {
            return false;
        }

        $clearance = (int) (DB::table('roles')->where('id', $user->role_id)->value('clearance_level') ?? 0);
        $isStaff = $clearance >= 4 || $user->lifecycle_stage === 'Strike Team';

        return $isStaff && ($minClearance === null || $clearance >= $minClearance);
    }
}
