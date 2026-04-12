<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\CanvasController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FalProxyController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\ImageEditController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SimpleTextWizardController;
use App\Http\Controllers\QuickGenerateController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Wizards\CSVWizardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Google OAuth (guest middleware — must be accessible before login)
Route::middleware('guest')->group(function () {
    Route::get('/auth/google', [SocialAuthController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [SocialAuthController::class, 'callback'])->name('auth.google.callback');
});

// Home route: show marketing homepage when unauthenticated, dashboard when logged in
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return Inertia::render('website/home');
})->name('home');

// Startup landing page
Route::get('/startup', function () {
    return Inertia::render('website/startup');
})->name('startup');

// Guest-accessible beta invite validation + waitlist signup
Route::get('invite/validate', [\App\Http\Controllers\BetaInviteController::class, 'validateCode'])
    ->middleware('throttle:10,1')
    ->name('invite.validate');

Route::post('waitlist', [\App\Http\Controllers\WaitlistController::class, 'store'])
    ->middleware('throttle:3,1')
    ->name('waitlist.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Beta invite code redemption — needs auth but no subscription
    Route::post('invite/redeem', [\App\Http\Controllers\BetaInviteController::class, 'redeem'])
        ->middleware('throttle:5,5')
        ->name('invite.redeem');

    // ── Subscription-gated area ─────────────────────────────────────────────
    // Everything below requires an active subscription. Non-subscribers are
    // redirected to /subscription/plans. This prevents copycats from exploring
    // the wizard UX and blocks wasted time generating with zero credits.
    Route::middleware('has.credits')->group(function () {

        // Simple Wizard (Direct Generation)
        Route::get('simple-wizard', [SimpleTextWizardController::class, 'index'])->name('simple-wizard.index');
        Route::post('simple-wizard/generate', [SimpleTextWizardController::class, 'generate'])->name('simple-wizard.generate');

        // Project Creation - Wizard Selection Page (must be before resource routes)
        Route::get('projects/create', function () {
            return Inertia::render('projects/create');
        })->name('projects.create');

        // Project Creation Wizards
        Route::get('projects/create/csv', function () {
            return Inertia::render('projects/wizards/csv');
        })->name('projects.wizards.csv');

        Route::post('projects/wizards/csv', [CSVWizardController::class, 'store'])
            ->middleware('throttle.user:5,1')
            ->name('projects.wizards.csv.store');

        Route::get('projects/wizards/csv/sessions/{session}', [CSVWizardController::class, 'show'])
            ->name('projects.wizards.csv.session');

        Route::get('projects/create/images', function () {
            return Inertia::render('projects/wizards/images');
        })->name('projects.wizards.images');

        Route::get('projects/create/text', function () {
            return Inertia::render('projects/wizards/text');
        })->name('projects.wizards.text');

        // Cluster Generation Test Lab — admin-only
        Route::middleware('admin')->group(function () {
            Route::get('test/cluster-generation', [\App\Http\Controllers\Test\ClusterTestController::class, 'index'])
                ->name('test.cluster-generation');
            Route::post('test/cluster-generation/analyze', [\App\Http\Controllers\Test\ClusterTestController::class, 'analyze'])
                ->name('test.cluster-generation.analyze');
            Route::post('test/cluster-generation/generate', [\App\Http\Controllers\Test\ClusterTestController::class, 'generate'])
                ->name('test.cluster-generation.generate');
        });

        // Quick Generate Routes
        Route::get('quick-generate', [QuickGenerateController::class, 'index'])
            ->name('quick-generate.index');

        Route::post('quick-generate', [QuickGenerateController::class, 'store'])
            ->name('quick-generate.store');

        Route::get('quick-generate/{session}', [QuickGenerateController::class, 'show'])
            ->name('quick-generate.show');

        Route::get('quick-generate/{session}/result', [QuickGenerateController::class, 'result'])
            ->name('quick-generate.result');

        Route::get('quick-generate/{session}/status', [QuickGenerateController::class, 'status'])
            ->name('quick-generate.status');

        // Projects Resource Routes (except create/store/destroy which need throttle)
        Route::resource('projects', ProjectController::class)->except(['create', 'store', 'destroy']);
        Route::post('projects', [ProjectController::class, 'store'])
            ->middleware('throttle.user:20,1')
            ->name('projects.store');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])
            ->middleware('throttle.user:20,1')
            ->name('projects.destroy');
        Route::post('projects/{id}/toggle-favorite', [ProjectController::class, 'toggleFavorite'])
            ->name('projects.toggle-favorite');

        // AI Generation Routes
        Route::post('projects/{id}/generate', [ProjectController::class, 'generateMore'])
            ->middleware('throttle.user:10,1')
            ->name('projects.generate-more');

        Route::get('projects/{id}/generation-progress', [ProjectController::class, 'generationProgress'])
            ->name('projects.generation-progress');

        // Image Management Routes
        Route::prefix('projects/{projectId}/images')->group(function () {
            Route::put('{imageId}', [ImageController::class, 'update'])->name('images.update');
            Route::delete('{imageId}', [ImageController::class, 'destroy'])->name('images.destroy');
            Route::post('{imageId}/regenerate', [ImageController::class, 'regenerate'])
                ->middleware('throttle.user:10,1')
                ->name('images.regenerate');
            Route::post('bulk-delete', [ImageController::class, 'bulkDestroy'])->name('images.bulk-destroy');
            Route::post('bulk-download', [ImageController::class, 'bulkDownload'])->name('images.bulk-download');
            Route::post('update-order', [ImageController::class, 'updateOrder'])->name('images.update-order');
            Route::post('{imageId}/toggle-favorite', [ImageController::class, 'toggleFavorite'])->name('images.toggle-favorite');
        });

        // Canvas Editor
        Route::get('canvas-editor', function () {
            $projectId = request()->query('projectId');
            $imageUrl  = request()->query('image');
            $projectTitle = request()->query('title', 'Untitled');

            return Inertia::render('canvas-editor', [
                'projectId'    => $projectId,
                'imageUrl'     => $imageUrl,
                'projectTitle' => $projectTitle,
            ]);
        })->name('canvas.editor');

        // Canvas Editor Actions
        Route::post('projects/{projectId}/images/{imageId}/save-edit', [CanvasController::class, 'saveEdit'])
            ->name('canvas.save-edit');
        Route::post('projects/{projectId}/canvas/export', [CanvasController::class, 'exportCanvas'])
            ->name('canvas.export');

        // AI-powered canvas editing — MVP operations
        Route::post('/api/generate-with-mask', [ImageEditController::class, 'generateWithMask'])
            ->middleware('throttle.user:20,1')
            ->name('api.generate-with-mask');

        Route::post('/api/ai-edit-image', [ImageEditController::class, 'aiEditImage'])
            ->middleware('throttle.user:20,1')
            ->name('api.ai-edit-image');

        Route::post('/api/erase-image', [ImageEditController::class, 'erase'])
            ->middleware('throttle.user:20,1')
            ->name('api.erase-image');

        // Advanced canvas operations (deferred from beta — hidden from UI)
        Route::post('/api/expand-image', [ImageEditController::class, 'expandImage'])
            ->name('api.expand-image');
        Route::post('/api/upscale-image', [ImageEditController::class, 'upscaleImage'])
            ->name('api.upscale-image');
        Route::post('/api/remove-background', [ImageEditController::class, 'removeBackground'])
            ->name('api.remove-background');
        Route::post('/api/resize-canvas', [ImageEditController::class, 'resizeCanvas'])
            ->name('api.resize-canvas');
        Route::post('/api/generate-from-prompt', [ImageEditController::class, 'generateFromPrompt'])
            ->name('api.generate-from-prompt');

        // FAL.ai AI upscale — proxied server-side so the API key stays off the client
        Route::post('/api/fal/upscale/submit', [FalProxyController::class, 'submitUpscale'])
            ->middleware('throttle.user:5,1')
            ->name('api.fal.upscale.submit');
        Route::get('/api/fal/upscale/status/{requestId}', [FalProxyController::class, 'upscaleStatus'])
            ->middleware('throttle.user:120,1')
            ->name('api.fal.upscale.status');

    }); // end has.credits group

    // Search & Updates (accessible without subscription — view-only)
    Route::get('search', [SearchController::class, 'index'])->name('search');
    Route::post('search', [SearchController::class, 'search'])->name('search.query');

    Route::get('updates', function () {
        return Inertia::render('updates');
    })->name('updates');

    // Feedback
    Route::get('/feedback', [FeedbackController::class, 'show'])->name('feedback');
    Route::post('/feedback', [FeedbackController::class, 'submit'])->name('feedback.submit');

    // Subscription & Billing — redirected to feedback during beta
    Route::prefix('subscription')->name('subscription.')->group(function () {
        Route::get('/plans', fn() => redirect()->route('feedback'))->name('plans');
        Route::get('/portal', fn() => redirect()->route('feedback'))->name('portal');
        Route::post('/upgrade', fn() => redirect()->route('feedback'))->name('upgrade');
        Route::post('/downgrade', fn() => redirect()->route('feedback'))->name('downgrade');
        Route::post('/purchase-credits', fn() => redirect()->route('feedback'))->name('purchase-credits');
    });

    // Invoices
    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/invoices', [\App\Http\Controllers\BillingController::class, 'index'])->name('invoices');
        Route::get('/invoices/{id}', [\App\Http\Controllers\BillingController::class, 'show'])->name('invoices.show');
        Route::get('/invoices/{id}/download', [\App\Http\Controllers\BillingController::class, 'downloadPdf'])->name('invoices.download');
    });
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/website.php';
require __DIR__.'/webhooks.php';