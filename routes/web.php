<?php

use App\Http\Controllers\DataExportController;
use App\Http\Controllers\PhotoController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::view('terms', 'legal.terms')->name('legal.terms');
Route::view('privacy', 'legal.privacy')->name('legal.privacy');
Route::view('cookies', 'legal.cookies')->name('legal.cookies');
Route::view('safety', 'legal.safety')->name('legal.safety');
Route::view('guidelines', 'legal.guidelines')->name('legal.guidelines');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('my-profile', 'pages::profile.edit')->name('my-profile.edit');
    Route::livewire('my-profile/verify', 'pages::profile.verify')->name('my-profile.verify');

    Route::get('photos/{photo}/{size}', PhotoController::class)
        ->whereIn('size', ['sm', 'lg'])
        ->name('photos.show');

    Route::middleware('profile.complete')->group(function () {
        Route::livewire('discover', 'pages::discover')->name('discover');
        Route::livewire('search', 'pages::search')->name('search');
        Route::livewire('activity/{tab?}', 'pages::activity')->whereIn('tab', ['likes', 'views', 'favorites'])->name('activity');
        Route::livewire('preferences', 'pages::preferences')->name('preferences');
        Route::livewire('people/{user}', 'pages::people.show')->name('people.show');
        Route::livewire('matches', 'pages::matches')->name('matches');
        Route::livewire('messages', 'pages::messages.index')->name('messages.index');
        Route::livewire('messages/{match}', 'pages::messages.show')->name('messages.show');
    });

    Route::livewire('settings/privacy', 'pages::settings.privacy')->name('privacy.edit');
    Route::get('settings/privacy/export', DataExportController::class)
        ->middleware('throttle:3,60')
        ->name('privacy.export');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
