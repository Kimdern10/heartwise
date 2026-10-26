<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\DataExportController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\SocialLoginController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::view('terms', 'legal.terms')->name('legal.terms');
Route::view('privacy', 'legal.privacy')->name('legal.privacy');
Route::view('cookies', 'legal.cookies')->name('legal.cookies');
Route::view('safety', 'legal.safety')->name('legal.safety');
Route::view('guidelines', 'legal.guidelines')->name('legal.guidelines');
Route::view('connection-blocked', 'blocked-connection')->name('connection.blocked');

// Sign in with Google / Apple
Route::middleware('guest')->group(function () {
    Route::get('auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])
        ->whereIn('provider', ['google', 'apple'])->name('social.redirect');
});
Route::match(['get', 'post'], 'auth/{provider}/callback', [SocialLoginController::class, 'callback'])
    ->whereIn('provider', ['google', 'apple'])->name('social.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('photos/{photo}/{size}', PhotoController::class)
        ->whereIn('size', ['sm', 'lg'])
        ->name('photos.show');

    // Admins can open any member's profile to review it.
    Route::middleware('onboarded')->group(function () {
        Route::livewire('people/{user}', 'pages::people.show')->name('people.show');
    });

    // Member-only area (admins are sent to the admin panel instead).
    Route::middleware('member')->group(function () {
        // The sign-up questions every new member answers first.
        Route::livewire('welcome', 'pages::onboarding')->name('onboarding');

        Route::middleware('onboarded')->group(function () {
            Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
            Route::livewire('my-profile', 'pages::profile.edit')->name('my-profile.edit');
            Route::livewire('my-profile/verify', 'pages::profile.verify')->name('my-profile.verify');

            Route::livewire('premium', 'pages::premium')->name('premium');
            Route::post('premium/checkout', [BillingController::class, 'checkout'])->name('premium.checkout');
            Route::get('premium/manage', [BillingController::class, 'portal'])->name('premium.portal');

            Route::middleware('profile.complete')->group(function () {
                Route::livewire('discover', 'pages::discover')->name('discover');
                Route::livewire('search', 'pages::search')->name('search');
                Route::livewire('activity/{tab?}', 'pages::activity')->whereIn('tab', ['likes', 'views', 'favorites'])->name('activity');
                Route::livewire('preferences', 'pages::preferences')->name('preferences');
                Route::livewire('matches', 'pages::matches')->name('matches');
                Route::livewire('messages', 'pages::messages.index')->name('messages.index');
                Route::livewire('messages/{match}', 'pages::messages.show')->name('messages.show');
            });

            Route::livewire('settings/privacy', 'pages::settings.privacy')->name('privacy.edit');
            Route::get('settings/privacy/export', DataExportController::class)
                ->middleware('throttle:3,60')
                ->name('privacy.export');
        });
    });
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
