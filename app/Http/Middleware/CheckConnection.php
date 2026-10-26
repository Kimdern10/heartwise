<?php

namespace App\Http\Middleware;

use App\Support\ConnectionInspector;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the admin's connection rules (block VPNs, block countries, US only)
 * and records where members connect from. The device timezone comes from the
 * "tz" cookie set by resources/js/app.js.
 */
class CheckConnection
{
    /** Pages anyone can always reach (and Stripe's server-to-server webhooks). */
    private const EXEMPT = ['stripe/*', 'up', 'terms', 'privacy', 'cookies', 'safety', 'guidelines', 'connection-blocked'];

    public function __construct(private ConnectionInspector $inspector) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is(self::EXEMPT) || $request->user()?->is_admin) {
            return $next($request);
        }

        $result = $this->inspector->inspect((string) $request->ip(), $request->cookie('tz'), $request->user());

        if ($result['blocked']) {
            return response()->view('blocked-connection', ['reason' => $result['reason']], 403);
        }

        return $next($request);
    }
}
