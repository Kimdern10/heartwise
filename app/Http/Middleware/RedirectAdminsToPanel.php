<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admins run the site from the admin panel; the dating pages (Discover,
 * Likes, Messages, Premium…) are for members only.
 */
class RedirectAdminsToPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
