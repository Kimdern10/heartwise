<?php

namespace App\Http\Middleware;

use App\Enums\AccountStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs out suspended or banned members, and records when members were last active.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->status === AccountStatus::Suspended && $user->suspended_until?->isPast()) {
            $user->forceFill(['status' => AccountStatus::Active, 'suspended_until' => null, 'status_reason' => null])->save();
        }

        if (! $user->isActive()) {
            $message = $user->status === AccountStatus::Banned
                ? __('This account has been closed for breaking our community guidelines.')
                : __('This account is suspended until :date.', ['date' => $user->suspended_until?->toFormattedDateString() ?? __('further notice')]);

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', $message);
        }

        if (! $user->last_active_at || $user->last_active_at->lt(now()->subMinutes(5))) {
            $user->forceFill(['last_active_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
