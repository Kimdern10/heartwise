<?php

namespace App\Http\Middleware;

use App\Support\ConnectionInspector;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the admin's connection rules (block VPNs, block countries, US only),
 * records where members connect from, and spots banned members coming back.
 * The device timezone comes from the "tz" cookie set by resources/js/app.js;
 * "hw_device" is a private random ID for this browser.
 */
class CheckConnection
{
    /** Pages anyone can always reach (and Stripe's server-to-server webhooks). */
    private const EXEMPT = ['stripe/*', 'up', 'terms', 'privacy', 'cookies', 'safety', 'guidelines', 'connection-blocked', 'sitemap.xml', 'robots.txt'];

    public function __construct(private ConnectionInspector $inspector) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is(self::EXEMPT) || $request->user()?->isStaff()) {
            return $next($request);
        }

        $deviceId = $request->cookie('hw_device');

        if (! is_string($deviceId) || strlen($deviceId) !== 40) {
            $deviceId = Str::random(40);
            Cookie::queue(Cookie::forever('hw_device', $deviceId, httpOnly: true, sameSite: 'lax'));
        }

        $tz = $request->cookie('tz');
        $result = $this->inspector->inspect((string) $request->ip(), is_string($tz) ? $tz : null, $request->user(), $deviceId);

        if ($result['blocked']) {
            return response()->view('blocked-connection', ['reason' => $result['reason']], 403);
        }

        return $next($request);
    }
}
