<?php

use App\Enums\UpcomingFeature;
use App\Http\Controllers\Approvals\ApprovalController;
use App\Http\Controllers\Approvals\DepositQueueController;
use App\Http\Controllers\Approvals\RegistrationController;
use App\Http\Controllers\Auth\RegistrationStatusController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Deposits\DepositController;
use App\Http\Controllers\Dev\LayoutPreviewController;
use App\Http\Controllers\Grocery\GroceryPurchaseController;
use App\Http\Controllers\Grocery\PayableController;
use App\Http\Controllers\Grocery\PurchaseToolsController;
use App\Http\Controllers\Ledger\AdjustmentController;
use App\Http\Controllers\Ledger\LedgerController;
use App\Http\Controllers\Members\MemberController;
use App\Http\Controllers\Members\MemberFileController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\UpcomingFeatureController;
use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Applicants wait here until their registration is decided; the
// registration gate sends them nowhere else.
Route::get('registration/status', RegistrationStatusController::class)
    ->middleware('auth')
    ->name('registration.status');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Users are deactivated, never deleted.
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');

    Route::resource('roles', RoleController::class)->except(['show']);

    // Hard delete is refused for members with financial records.
    Route::resource('members', MemberController::class);
    Route::post('members/{member}/deactivate', [MemberController::class, 'deactivate'])->name('members.deactivate');
    Route::post('members/{member}/reactivate', [MemberController::class, 'reactivate'])->name('members.reactivate');
    Route::get('members/{member}/nid', [MemberFileController::class, 'nid'])->name('members.nid');
    Route::get('members/{member}/photo', [MemberFileController::class, 'photo'])->name('members.photo');

    // Purchases are voided, never deleted: there is no destroy route.
    Route::get('grocery/item-names', [PurchaseToolsController::class, 'itemNames'])->name('grocery.item-names');
    Route::post('grocery/totals', [PurchaseToolsController::class, 'totals'])->name('grocery.totals');
    Route::resource('grocery', GroceryPurchaseController::class)->parameters(['grocery' => 'purchase'])->except(['destroy']);
    Route::post('grocery/{purchase}/void', [GroceryPurchaseController::class, 'void'])->name('grocery.void');
    Route::get('grocery/{purchase}/receipt', [GroceryPurchaseController::class, 'receipt'])->name('grocery.receipt');

    Route::get('payables', [PayableController::class, 'index'])->name('payables.index');
    Route::post('payables/{payable}/reimburse', [PayableController::class, 'reimburse'])->name('payables.reimburse');

    // Deposits are never edited or deleted; mistakes are reversed.
    Route::resource('deposits', DepositController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('deposits/{deposit}/cancel', [DepositController::class, 'cancel'])->name('deposits.cancel');
    Route::post('deposits/{deposit}/approve', [DepositController::class, 'approve'])->name('deposits.approve');
    Route::post('deposits/{deposit}/reject', [DepositController::class, 'reject'])->name('deposits.reject');
    Route::post('deposits/{deposit}/reverse', [DepositController::class, 'reverse'])->name('deposits.reverse');

    Route::get('approvals', ApprovalController::class)->name('approvals.index');
    Route::get('approvals/deposits', DepositQueueController::class)->name('approvals.deposits.index');
    Route::get('approvals/registrations', [RegistrationController::class, 'index'])->name('approvals.registrations.index');
    Route::get('approvals/registrations/{member}', [RegistrationController::class, 'show'])->name('approvals.registrations.show');
    Route::post('approvals/registrations/{member}/approve', [RegistrationController::class, 'approve'])->name('approvals.registrations.approve');
    Route::post('approvals/registrations/{member}/reject', [RegistrationController::class, 'reject'])->name('approvals.registrations.reject');

    // Ledger entries are append-only: there is no edit or delete route.
    Route::get('ledger', [LedgerController::class, 'index'])->name('ledger.index');
    Route::get('ledger/adjustments/create', [AdjustmentController::class, 'create'])->name('ledger.adjustments.create');
    Route::post('ledger/adjustments', [AdjustmentController::class, 'store'])->name('ledger.adjustments.store');

    // Sidebar screens not built yet show a "coming soon" page.
    Route::get('{feature}', UpcomingFeatureController::class)
        ->whereIn('feature', array_column(UpcomingFeature::cases(), 'value'))
        ->name('upcoming');

    // Temporary: the app shell and page templates with placeholder content.
    // The controller returns 404 outside the local environment.
    Route::get('layout-preview', LayoutPreviewController::class)->name('layout-preview');
});

// Temporary: visual check of the design system tokens. Local only.
if (app()->isLocal()) {
    Route::inertia('design-preview', 'design-preview')->name('design-preview');
}

require __DIR__.'/settings.php';
