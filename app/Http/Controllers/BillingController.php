<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Premium checkout and "manage / cancel" through Stripe's hosted pages,
 * so card details never touch our server.
 */
class BillingController extends Controller
{
    public static function enabled(): bool
    {
        return filled(config('cashier.key')) && filled(config('cashier.secret'));
    }

    public function checkout(Request $request): Response
    {
        $validated = $request->validate([
            'plan' => ['required', Rule::in(array_keys(config('premium.plans')))],
        ]);

        $user = $request->user();
        $priceId = config("premium.plans.{$validated['plan']}.price_id");

        if (! self::enabled() || ! $priceId) {
            return back()->with('status', __('Payments are not set up yet. Please try again later.'));
        }

        if ($user->subscribed('premium')) {
            return redirect()->route('premium.portal');
        }

        return $user->newSubscription('premium', $priceId)
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => route('premium', ['welcome' => 1]),
                'cancel_url' => route('premium'),
                'customer_update' => ['address' => 'auto'],
            ]);
    }

    /**
     * Stripe's billing portal: change plan, update card, cancel, see invoices.
     */
    public function portal(Request $request): RedirectResponse
    {
        abort_unless(self::enabled() && $request->user()->hasStripeId(), 404);

        return $request->user()->redirectToBillingPortal(route('premium'));
    }
}
