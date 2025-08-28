<?php

namespace CapsuleCmdr\SeatOsmm\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OsmmMaintenanceMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // IMPORTANT: If this middleware is GLOBAL, we can't rely on route names here.
        // Allow the maintenance page itself (by path) to avoid loops.
        if ($request->is('maintenance') || $request->is('maintenance/*')) {
            return $next($request);
        }

        // Allow only static assets & robots so the landing page can load cleanly.
        // (Keep this list conservative.)
        if ($request->is(
            'vendor/capsulecmdr/seat-osmm/js/*',
            'web/css/*', 'web/js/*', 'web/img/*',
            'vendor/*', 'images/*', 'storage/*',
            'favicon*', 'robots.txt'
        )) {
            return $next($request);
        }

        // Allow auth endpoints so people can still log in/out if needed.
        // if ($request->is('login*', 'logout*', 'password/*', 'auth/*', 'sso/*')) {
        //     return $next($request);
        // }

        // Read the setting (we saved it as text '1' / '0')
        $enabled = (int) (osmm_setting('osmm_maintenance_enabled', '0'));
        if ($enabled !== 1) {
            return $next($request);
        }

        // Bypass for allowed users
        $user = $request->user();
        if ($user && $user->can('osmm.maint_bypass')) {
            return $next($request);
        }

        Log::info('OSMM Maintenance redirect', ['path' => $request->path(), 'uid' => optional($user)->id]);

        // Redirect everything else to the landing page
        return redirect('/maintenance');
    }
}
