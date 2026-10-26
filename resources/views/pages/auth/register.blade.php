<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Join :app', ['app' => config('app.name')])" :description="__('Free to join. 18+ only. Real people only.')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-social-buttons />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Email Address (name, birthday and the rest come next, one question at a time) -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <div class="space-y-2">
                <label class="flex items-start gap-3 text-sm text-zinc-700 dark:text-zinc-300">
                    <input type="checkbox" name="terms" value="1" @checked(old('terms')) required
                        class="mt-0.5 size-4 shrink-0 accent-rose-600" data-test="terms-checkbox" />
                    <span>
                        {{ __('I am 18 or older and I agree to the') }}
                        <a href="{{ route('legal.terms') }}" target="_blank" class="text-rose-600 underline">{{ __('Terms') }}</a>,
                        <a href="{{ route('legal.privacy') }}" target="_blank" class="text-rose-600 underline">{{ __('Privacy Policy') }}</a>
                        {{ __('and') }}
                        <a href="{{ route('legal.guidelines') }}" target="_blank" class="text-rose-600 underline">{{ __('Community Guidelines') }}</a>.
                    </span>
                </label>
                <flux:error name="terms" />
            </div>

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('Create account') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
