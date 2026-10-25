<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileIsComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->profile) {
            return redirect()->route('my-profile.edit')
                ->with('status', __('Create your profile first so people can get to know you.'));
        }

        return $next($request);
    }
}
