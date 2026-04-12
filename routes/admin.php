<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {

    // ── Dashboard ────────────────────────────────────────────────
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // ── Users ────────────────────────────────────────────────────
    Route::get('/users', [AdminDashboardController::class, 'users'])->name('users');
    Route::put('/users/{user}', [AdminDashboardController::class, 'updateUser'])
        ->middleware('throttle:30,1')
        ->name('users.update');
    Route::post('/users/{user}/suspend', [AdminDashboardController::class, 'suspendUser'])
        ->middleware('throttle:20,1')
        ->name('users.suspend');
    Route::post('/users/{user}/reactivate', [AdminDashboardController::class, 'reactivateUser'])
        ->middleware('throttle:20,1')
        ->name('users.reactivate');
    Route::put('/users/{user}/tier', [AdminDashboardController::class, 'updateUserTier'])
        ->middleware('throttle:20,1')
        ->name('users.tier');
    Route::delete('/users/{user}', [AdminDashboardController::class, 'deleteUser'])
        ->middleware('throttle:5,1')
        ->name('users.delete');
    Route::post('/users/{user}/password-reset', [AdminDashboardController::class, 'sendPasswordReset'])
        ->middleware('throttle:5,1')
        ->name('users.password-reset');

    // ── Impersonation ────────────────────────────────────────────────────
    Route::post('/users/{user}/impersonate', [AdminDashboardController::class, 'impersonateUser'])
        ->middleware('throttle:10,1')
        ->name('users.impersonate');
});

// Stop-impersonation is outside admin middleware: the active user is the impersonated non-admin
Route::middleware(['auth'])->post('/admin/impersonate/stop', [AdminDashboardController::class, 'stopImpersonation'])->name('admin.impersonate.stop');

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {

    // ── Plans ────────────────────────────────────────────────────
    Route::get('/plans', [AdminDashboardController::class, 'plans'])->name('plans');

    // ── Subscriptions ────────────────────────────────────────────
    Route::get('/subscriptions', [AdminDashboardController::class, 'subscriptions'])->name('subscriptions');
    Route::post('/subscriptions/{subscription}/cancel', [AdminDashboardController::class, 'cancelSubscription'])->name('subscriptions.cancel');

    // ── Credits ──────────────────────────────────────────────────
    Route::get('/credits', [AdminDashboardController::class, 'credits'])->name('credits');
    Route::post('/credits/{user}/adjust', [AdminDashboardController::class, 'adjustCredits'])
        ->middleware('throttle:20,1')
        ->name('credits.adjust');

    // ── Projects ─────────────────────────────────────────────────
    Route::get('/projects', [AdminDashboardController::class, 'projects'])->name('projects');
    Route::delete('/projects/{project}', [AdminDashboardController::class, 'deleteProject'])->name('projects.delete');

    // ── Analytics ────────────────────────────────────────────────
    Route::get('/analytics', [AdminDashboardController::class, 'analytics'])->name('analytics');
    Route::get('/usage', [AdminDashboardController::class, 'usage'])->name('usage'); // legacy alias
});
