<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HqClearance
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Are they logged in at all?
        if (!Auth::check()) {
            return redirect('/hq/login'); // Adjust if your HQ login URL is different
        }

        // 2. Fetch their true clearance level
        $role = DB::table('roles')->where('id', Auth::user()->role_id)->first();

        // 3. The Guillotine: If they are not Level 4 or 5, kill the session.
        if (!$role || $role->clearance_level < 4) {
            Auth::logout();
            abort(403, 'SECURITY HALT: This sector is strictly restricted to JBS Praxis Command.');
        }

        return $next($request);
    }
}