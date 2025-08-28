<?php

namespace CapsuleCmdr\SeatOsmm\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OsmmMaintenanceMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $enabled = (int) (osmm_setting('osmm_maintenance_enabled', 0));

        \Log::info('OSMM Maint MW hit', [
            'enabled' => $enabled,
            'user_id' => optional($request->user())->id,
            'bypass'  => $request->user()?->can('osmm.maint_bypass') ?? false,
            'path'    => $request->path(),
        ]);

        // Skip if not enabled
        if ($enabled !== 1) return $next($request);

        // Allow bypass if user has permission
        $user = $request->user();
        if ($user && $user->can('osmm.maint_bypass')) {
            return $next($request);
        }

        // Allow the maintenance landing itself, auth, and static assets
        if ($request->routeIs('osmm.maint.landing') ||
            $request->is('login*','logout*','assets*','vendor*','web/css/*','web/js/*','web/img/*','images/*')) {
            return $next($request);
        }

        // Redirect everything else
        return redirect()->route('osmm.maint.landing');
    }
}
