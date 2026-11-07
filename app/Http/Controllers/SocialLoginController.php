<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\User;
use App\Rules\NotDisposableEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * "Continue with Google / Apple". New people still answer the onboarding
 * questions (including date of birth and the rules) before using the site.
 */
class SocialLoginController extends Controller
{
    public static function enabled(string $provider): bool
    {
        return filled(config("services.$provider.client_id"))
            && (filled(config("services.$provider.client_secret")) || filled(config("services.$provider.private_key")));
    }

    public function redirect(string $provider): SymfonyRedirectResponse
    {
        abort_unless(self::enabled($provider), 404);

        $driver = Socialite::driver($provider);

        if ($provider === 'apple' && $driver instanceof AbstractProvider) {
            $driver->scopes(['name', 'email']);
        }

        return $driver->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        abort_unless(self::enabled($provider), 404);

        try {
            $social = Socialite::driver($provider)->user();
        } catch (Throwable $e) {
            report($e);

            // On your own computer, show the real reason to make setup problems easy to fix.
            $detail = app()->isLocal() ? ' ('.class_basename($e).': '.mb_strimwidth($e->getMessage(), 0, 160, '…').')' : '';

            return redirect()->route('login')->with('status', __('Sign-in with :provider didn’t work. Please try again.', ['provider' => ucfirst($provider)]).$detail);
        }

        if (request()->filled('error')) {
            // The person pressed "Cancel" on the Google/Apple screen.
            return redirect()->route('login')->with('status', __('Sign-in was cancelled.'));
        }

        $account = SocialAccount::query()->where('provider', $provider)->where('provider_id', $social->getId())->first();

        if ($account) {
            return $this->login($account->user);
        }

        $email = $social->getEmail();

        if ($email && NotDisposableEmail::isDisposable($email)) {
            return redirect()->route('register')->with('status', __('Please use your real, permanent email address — temporary email services aren’t allowed.'));
        }

        if (! $email) {
            return redirect()->route('register')->with('status', __('We need an email address to create your account. Please sign up with email instead.'));
        }

        $user = DB::transaction(function () use ($provider, $social, $email) {
            // Google and Apple only return addresses they have verified, so it is safe to link by email.
            $user = User::query()->where('email', $email)->first() ?? User::create([
                'name' => $social->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Str::password(32),
            ]);

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $user->socialAccounts()->create(['provider' => $provider, 'provider_id' => $social->getId()]);

            return $user;
        });

        return $this->login($user);
    }

    private function login(User $user): RedirectResponse
    {
        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended($user->isOnboarded() ? route('dashboard') : route('onboarding'));
    }
}
