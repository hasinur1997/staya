<?php

use App\Http\Controllers\Settings\MailSettingsController;
use App\Http\Controllers\Settings\MessSettingsController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    // The mess-wide settings (Admin section), not the personal ones above.
    Route::get('mess-settings', [MessSettingsController::class, 'edit'])->name('mess-settings.edit');
    Route::put('mess-settings', [MessSettingsController::class, 'update'])->name('mess-settings.update');
    Route::post('mess-settings/reset', [MessSettingsController::class, 'reset'])
        ->middleware('throttle:3,1')
        ->name('mess-settings.reset');

    // How the app sends email (SMTP), instead of MAIL_* in .env.
    Route::get('mail-settings', [MailSettingsController::class, 'edit'])->name('mail-settings.edit');
    Route::put('mail-settings', [MailSettingsController::class, 'update'])->name('mail-settings.update');
    Route::post('mail-settings/test', [MailSettingsController::class, 'test'])
        ->middleware('throttle:5,1')
        ->name('mail-settings.test');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
