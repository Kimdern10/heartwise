<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

new class extends Component {
    public string $code = '';

    public ?string $notice = null;

    public function verify(): void
    {
        $this->validate(['code' => ['required', 'digits:6']], ['code.digits' => __('Enter the 6-digit code from the email.')]);

        $user = Auth::user();

        if ($user->hasVerifiedEmail() || $user->verifyEmailCode($this->code)) {
            $this->redirectIntended(route('dashboard', absolute: false).'?verified=1', navigate: true);

            return;
        }

        $this->code = '';
        $this->addError('code', $user->fresh()->email_code_attempts >= 5 || $user->email_code_expires_at?->isPast()
            ? __('That code has expired. Please send a new one.')
            : __('That code is not right. Please check and try again.'));
    }

    public function resend(): void
    {
        $key = 'email-code:'.Auth::id();

        if (! RateLimiter::attempt($key, 3, fn () => true, 600)) {
            $this->addError('code', __('Please wait a few minutes before asking for another code.'));

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        $this->resetErrorBag();
        $this->notice = __('A new code is on its way to :email.', ['email' => Auth::user()->email]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <form wire:submit="verify" class="flex flex-col items-center gap-5"
        x-data x-init="$nextTick(() => $el.querySelector('input')?.focus())">
        <flux:otp wire:model="code" length="6" name="code" :label="__('Verification code')" label:sr-only class="mx-auto" />

        <flux:error name="code" />

        @if (app()->isLocal() && in_array(config('mail.default'), ['log', 'array'], true))
            <div class="w-full rounded-2xl border border-dashed border-amber-400 bg-amber-50 p-3 text-center text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200" data-test="dev-code">
                <p class="font-semibold">{{ __('Email not connected yet') }}</p>
                @if ($devCode = cache('dev-email-code:'.auth()->id()))
                    <p>{{ __('No email service is connected yet, so here is your code:') }} <strong class="tracking-widest">{{ $devCode }}</strong></p>
                @else
                    <p>{{ __('No email service is connected yet. Click “Send a new code” and it will appear here.') }}</p>
                @endif
            </div>
        @endif

        @if ($notice)
            <flux:text class="text-center font-medium !text-green-600 dark:!text-green-400">{{ $notice }}</flux:text>
        @endif

        <flux:button type="submit" variant="primary" class="w-full" data-test="verify-code-button">
            {{ __('Verify email') }}
        </flux:button>
    </form>

    <div class="flex flex-col items-center gap-2 text-sm">
        <flux:text>{{ __('Didn’t get it? Check spam, or') }}</flux:text>
        <flux:button size="sm" variant="ghost" wire:click="resend" data-test="resend-code-button">{{ __('Send a new code') }}</flux:button>
    </div>
</div>
