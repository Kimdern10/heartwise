<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * New members answer the sign-up questions (the onboarding wizard) before
 * they can use the rest of the site, like Hinge.
 */
class EnsureOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_admin && ! $user->isOnboarded()) {
            return redirect()->route('onboarding');
        }

        return $next($request);
    }
}
