<?php

use App\Http\Controllers\Admin\SelfieController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');
    Route::livewire('profiles', 'pages::admin.profiles')->name('profiles');
    Route::livewire('photos', 'pages::admin.photos')->name('photos');
    Route::livewire('verifications', 'pages::admin.verifications')->name('verifications');
    Route::livewire('reports', 'pages::admin.reports')->name('reports');
    Route::livewire('messages', 'pages::admin.messages')->name('messages');
    Route::livewire('users', 'pages::admin.users')->name('users');
    Route::livewire('security', 'pages::admin.security')->name('security');

    Route::get('verifications/{verificationRequest}/selfie', SelfieController::class)->name('verifications.selfie');
});
