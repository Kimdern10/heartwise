<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\IdentityCheck;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Sends the member to Stripe's secure ID check page.
 */
class IdentityController extends Controller
{
    public function start(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(IdentityCheck::enabled(), 404);

        if ($user->isIdVerified()) {
            return redirect()->route('my-profile.verify');
        }

        if (! RateLimiter::attempt('identity:'.$user->id, 3, fn () => true, 86400)) {
            return redirect()->route('my-profile.verify')->with('status', __('You can start up to 3 ID checks a day. Please try again tomorrow.'));
        }

        try {
            return redirect()->away(IdentityCheck::start($user, route('my-profile.verify', ['identity' => 'return'])));
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('my-profile.verify')->with('status', __('The ID check could not be started. Please try again later.'));
        }
    }
}
