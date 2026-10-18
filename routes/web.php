<?php

use App\Http\Controllers\Dev\LayoutPreviewController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Users are deactivated, never deleted.
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');

    Route::resource('roles', RoleController::class)->except(['show']);

    // Temporary: the app shell and page templates with placeholder content.
    // The controller returns 404 outside the local environment.
    Route::get('layout-preview', LayoutPreviewController::class)->name('layout-preview');
});

// Temporary: visual check of the design system tokens. Local only.
if (app()->isLocal()) {
    Route::inertia('design-preview', 'design-preview')->name('design-preview');
}

require __DIR__.'/settings.php';
