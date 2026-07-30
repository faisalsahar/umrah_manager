<?php

use App\Http\Middleware\ConfirmPasswordUnlessPasswordless;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');

    Route::livewire('settings/language', 'pages::settings.language')->name('language.edit');

    // Platform-admin-only — see mount()'s own abort_unless; kept under settings
    // rather than the main nav, alongside every other personal/account screen.
    Route::livewire('settings/users', 'pages::settings.users')->name('users.edit');

    Route::livewire('settings/security', 'pages::settings.security')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                // Like password.confirm, but skips accounts with no password (OAuth logins).
                [ConfirmPasswordUnlessPasswordless::class],
                [],
            ),
        )
        ->name('security.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
