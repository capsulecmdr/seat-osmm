<?php

namespace CapsuleCmdr\SeatOsmm\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OsmmMaintenanceMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // 0) Always allow the landing page itself
        if ($request->routeIs('osmm.maint.landing')) {
            return $next($request);
        }

        // 1) Read setting (stored as '1'/'0' strings)
        $enabled = (int) (osmm_setting('osmm_maintenance_enabled', '0'));

        // 2) Not enabled? Proceed.
        if ($enabled !== 1) {
            return $next($request);
        }

        // 3) Bypass for permitted users
        $user = $request->user();
        if ($user && $user->can('osmm.maint_bypass')) {
            return $next($request);
        }

        // 4) Allow ONLY static assets so the landing page renders nicely
        //    (be conservative—avoid broad globs like 'web/*')
        if ($request->is(
            'web/css/*', 'web/js/*', 'web/img/*',
            'vendor/*', 'images/*', 'storage/*',
            'favicon*', 'robots.txt'
        )) {
            return $next($request);
        }

        // 5) (Optional) log one line to confirm interception
        Log::info('OSMM Maintenance redirect', [
            'path'   => $request->path(),
            'userId' => optional($user)->id,
        ]);

        // 6) Redirect everything else
        return redirect()->route('osmm.maint.landing');
    }
}
