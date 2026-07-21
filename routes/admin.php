<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\BetaApplicationAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'not.suspended', 'admin'])->prefix('admin')->name('admin.')->group(function () {

    // ── Dashboard ────────────────────────────────────────────────
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // ── Beta applications ─────────────────────────────────────────
    Route::get('/beta-applications', [BetaApplicationAdminController::class, 'index'])->name('beta-applications');
    Route::post('/beta-applications/{beta_application}/approve', [BetaApplicationAdminController::class, 'approve'])
        ->middleware('throttle:30,1')
        ->name('beta-applications.approve');
    Route::post('/beta-applications/{beta_application}/reject', [BetaApplicationAdminController::class, 'reject'])
        ->middleware('throttle:30,1')
        ->name('beta-applications.reject');

    // ── Users ────────────────────────────────────────────────────
    Route::get('/users', [AdminDashboardController::class, 'users'])->name('users');
    Route::put('/users/{user}', [AdminDashboardController::class, 'updateUser'])
        ->middleware('throttle:30,1')
        ->name('users.update');

    // Destructive actions require recent password confirmation
    Route::middleware('password.confirm')->group(function () {
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
        Route::post('/users/{user}/impersonate', [AdminDashboardController::class, 'impersonateUser'])
            ->middleware('throttle:10,1')
            ->name('users.impersonate');
        Route::post('/credits/{user}/adjust', [AdminDashboardController::class, 'adjustCredits'])
            ->middleware('throttle:20,1')
            ->name('credits.adjust');
        Route::post('/subscriptions/{subscription}/cancel', [AdminDashboardController::class, 'cancelSubscription'])
            ->name('subscriptions.cancel');
        Route::delete('/projects/{project}', [AdminDashboardController::class, 'deleteProject'])
            ->name('projects.delete');
    });

    Route::post('/users/{user}/password-reset', [AdminDashboardController::class, 'sendPasswordReset'])
        ->middleware('throttle:5,1')
        ->name('users.password-reset');

    // ── Plans ────────────────────────────────────────────────────
    Route::get('/plans', [AdminDashboardController::class, 'plans'])->name('plans');

    // ── Subscriptions ────────────────────────────────────────────
    Route::get('/subscriptions', [AdminDashboardController::class, 'subscriptions'])->name('subscriptions');

    // ── Credits ──────────────────────────────────────────────────
    Route::get('/credits', [AdminDashboardController::class, 'credits'])->name('credits');

    // ── Projects ─────────────────────────────────────────────────
    Route::get('/projects', [AdminDashboardController::class, 'projects'])->name('projects');

    // ── Analytics ────────────────────────────────────────────────
    Route::get('/analytics', [AdminDashboardController::class, 'analytics'])->name('analytics');
    Route::get('/usage', [AdminDashboardController::class, 'usage'])->name('usage');

    // ── Queue / failed jobs ──────────────────────────────────────
    Route::get('/failed-jobs', [AdminDashboardController::class, 'failedJobs'])->name('failed-jobs');
    Route::post('/failed-jobs/retry-all', [AdminDashboardController::class, 'retryAllFailedJobs'])
        ->middleware(['password.confirm', 'throttle:5,1'])
        ->name('failed-jobs.retry-all');
    Route::post('/failed-jobs/{uuid}/retry', [AdminDashboardController::class, 'retryFailedJob'])
        ->middleware(['password.confirm', 'throttle:20,1'])
        ->name('failed-jobs.retry');
    Route::delete('/failed-jobs/{uuid}', [AdminDashboardController::class, 'forgetFailedJob'])
        ->middleware(['password.confirm', 'throttle:20,1'])
        ->name('failed-jobs.forget');

    // ── Audit log ────────────────────────────────────────────────
    Route::get('/audit-log', [AdminDashboardController::class, 'auditLog'])->name('audit-log');

    // ── Feedback Export ──────────────────────────────────────────
    Route::get('/feedback/download', [AdminDashboardController::class, 'downloadFeedback'])->name('feedback.download');
});
// Stop-impersonation is outside admin middleware: the active user is the impersonated non-admin
Route::middleware(['auth'])->post('/admin/impersonate/stop', [AdminDashboardController::class, 'stopImpersonation'])->name('admin.impersonate.stop');
