<?php

use App\Http\Controllers\BillingController;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Premium')] class extends Component {
    public string $plan = 'quarterly';

    #[Computed]
    public function subscription()
    {
        return Auth::user()->subscription('premium');
    }
}; ?>

@php($enabled = BillingController::enabled())
@php($sub = $this->subscription)
@php($isPremium = Auth::user()->subscribed('premium'))

<section class="mx-auto w-full max-w-4xl space-y-8">
    @if (request('welcome'))
        <div class="bg-brand-gradient rounded-3xl p-6 text-center text-white animate-pop">
            <p class="font-display text-3xl">{{ __('Welcome to Premium ✨') }}</p>
            <p class="mt-1 text-white/80">{{ __('It can take a few seconds for your payment to confirm. Refresh if your perks aren’t on yet.') }}</p>
        </div>
    @endif

    <x-auth-session-status :status="session('status')" />

    <div class="text-center animate-fade-up">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-amber-900 dark:bg-amber-500/15 dark:text-amber-300">
            <flux:icon.sparkles variant="solid" class="size-4" /> {{ config('app.name') }} Premium
        </span>
        <h1 class="font-display mt-4 text-5xl">{{ $isPremium ? __('You’re Premium') : __('Get seen. Get matched.') }}</h1>
        <p class="mx-auto mt-3 max-w-xl text-lg text-zinc-600 dark:text-zinc-400">
            {{ $isPremium ? __('Thanks for supporting a safer dating community.') : __('Everything you need to meet the right person faster.') }}
        </p>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach (config('premium.features') as [$icon, $title, $text])
            <div class="hw-card flex gap-3 p-5" data-reveal style="--i: {{ $loop->index % 3 }}">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-zinc-900 text-white dark:bg-white dark:text-zinc-900"><flux:icon :name="$icon" class="size-5" /></span>
                <div>
                    <p class="font-semibold">{{ $title }}</p>
                    <p class="text-sm text-zinc-500">{{ $text }}</p>
                </div>
            </div>
        @endforeach
    </div>

    @if ($isPremium)
        <div class="hw-card space-y-3 p-6 text-center">
            @if ($sub?->onGracePeriod())
                <p class="font-semibold">{{ __('Your Premium is cancelled and ends on :date. You won’t be charged again.', ['date' => $sub->ends_at->toFormattedDateString()]) }}</p>
            @else
                <p class="font-semibold">{{ __('Your Premium renews automatically. You can cancel any time — you keep Premium until the end of the period you paid for.') }}</p>
            @endif
            @if ($enabled && Auth::user()->hasStripeId())
                <flux:button variant="primary" icon="credit-card" :href="route('premium.portal')">{{ __('Manage or cancel subscription') }}</flux:button>
            @endif
        </div>
    @else
        <form method="POST" action="{{ route('premium.checkout') }}" class="space-y-5">
            @csrf
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach (config('premium.plans') as $key => $plan)
                    <label class="relative cursor-pointer">
                        <input type="radio" name="plan" value="{{ $key }}" wire:model.live="plan" class="peer sr-only" />
                        <div class="hw-card h-full p-6 text-center transition peer-checked:ring-2 peer-checked:ring-zinc-900 dark:peer-checked:ring-white">
                            @if (isset($plan['badge']))
                                <span class="absolute -top-3 start-1/2 -translate-x-1/2 rounded-full bg-zinc-900 px-3 py-1 text-xs font-bold text-white dark:bg-white dark:text-zinc-900">{{ $plan['badge'] }}</span>
                            @endif
                            <p class="font-semibold">{{ $plan['label'] }}</p>
                            <p class="font-display mt-2 text-4xl">${{ number_format($plan['amount'] / $plan['months'], 2) }}</p>
                            <p class="text-xs text-zinc-500">{{ __('per month') }}</p>
                            <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">{{ __(':total billed every :n', ['total' => '$'.number_format($plan['amount'], 2), 'n' => $plan['months'] === 1 ? __('month') : $plan['months'].' '.__('months')]) }}</p>
                        </div>
                    </label>
                @endforeach
            </div>

            <flux:button type="submit" variant="primary" class="w-full !h-14 !text-lg" :disabled="! $enabled" data-test="premium-checkout">
                {{ $enabled ? __('Continue to secure checkout') : __('Payments coming soon') }}
            </flux:button>

            <p class="text-center text-xs leading-relaxed text-zinc-500">
                {{ __('Payment is handled securely by Stripe. Your subscription renews automatically at the same price and length until you cancel. Cancel any time in one click from this page — you keep Premium until the end of the period you’ve paid for. Prices in USD; sales tax may apply.') }}
                <a href="{{ route('legal.terms') }}" class="underline">{{ __('Terms') }}</a>
            </p>
        </form>
    @endif
</section>
