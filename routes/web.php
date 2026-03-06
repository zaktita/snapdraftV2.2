<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\CanvasController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImageEditController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Wizards\CSVWizardController;
use App\Http\Controllers\Wizards\ImagesWizardController;
use App\Http\Controllers\Wizards\TextWizardController;
use App\Http\Controllers\Wizards\BrandAnalysisWizardController;
use App\Http\Controllers\Wizards\BrandKitWizardController;
use App\Http\Controllers\SimpleTextWizardController;
use App\Http\Controllers\QuickGenerateController;
use App\Http\Controllers\Auth\SocialAuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

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

Route::middleware(['auth', 'verified'])->group(function () {
    // Simple Wizard (Direct Generation)
    Route::get('simple-wizard', [SimpleTextWizardController::class, 'index'])->name('simple-wizard.index');
    Route::post('simple-wizard/generate', [SimpleTextWizardController::class, 'generate'])->name('simple-wizard.generate');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Project Creation - Wizard Selection Page (must be before resource routes)
    Route::get('projects/create', function () {
        return Inertia::render('projects/create');
    })->name('projects.create');

    // Project Creation Wizards
    Route::get('projects/create/csv', function () {
        return Inertia::render('projects/wizards/csv');
    })->name('projects.wizards.csv');

    Route::post('projects/wizards/csv', [CSVWizardController::class, 'store'])
        ->middleware(['has.credits', 'check.csv.limit', 'check.project.limit'])
        ->name('projects.wizards.csv.store');

    // CSV Wizard Session Routes (processing + result)
    Route::get('projects/wizards/csv/sessions/{session}', [CSVWizardController::class, 'showSession'])
        ->name('projects.wizards.csv.session.show');

    Route::get('projects/wizards/csv/sessions/{session}/result', [CSVWizardController::class, 'resultSession'])
        ->name('projects.wizards.csv.session.result');

    Route::get('projects/wizards/csv/sessions/{session}/status', [CSVWizardController::class, 'statusSession'])
        ->name('projects.wizards.csv.session.status');

    Route::get('projects/create/images', function () {
        return Inertia::render('projects/wizards/images');
    })->name('projects.wizards.images');

    Route::post('projects/wizards/images', [ImagesWizardController::class, 'store'])
        ->middleware('has.credits')
        ->name('projects.wizards.images.store');

    Route::get('projects/create/text', function () {
        return Inertia::render('projects/wizards/text');
    })->name('projects.wizards.text');

    Route::post('projects/wizards/text', [TextWizardController::class, 'store'])
        ->middleware('has.credits')
        ->name('projects.wizards.text.store');

    // Brand Kit Wizard
    Route::get('projects/create/brand-kit', [BrandKitWizardController::class, 'index'])
        ->name('projects.wizards.brand-kit');

    Route::post('projects/wizards/brand-kit', [BrandKitWizardController::class, 'store'])
        ->middleware('has.credits')
        ->name('projects.wizards.brand-kit.store');

    // Brand Analysis Wizard (lab/testing)
    Route::get('projects/wizards/brand-analysis', [BrandAnalysisWizardController::class, 'index'])
        ->name('projects.wizards.brand-analysis');

    Route::post('projects/wizards/brand-analysis', [BrandAnalysisWizardController::class, 'store'])
        ->middleware('has.credits')
        ->name('projects.wizards.brand-analysis.store');

    Route::post('projects/wizards/brand-analysis/generate', [BrandAnalysisWizardController::class, 'generate'])
        ->middleware('has.credits')
        ->name('projects.wizards.brand-analysis.generate');

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

    // Projects Resource Routes (except create, which we handle above)
    Route::resource('projects', ProjectController::class)->except(['create']);
    Route::post('projects/{id}/toggle-favorite', [ProjectController::class, 'toggleFavorite'])
        ->name('projects.toggle-favorite');
    
    // AI Generation Routes (with stricter rate limiting and credits check)
    Route::post('projects/{id}/generate', [ProjectController::class, 'generateMore'])
        ->middleware(['throttle.user:10,1', 'has.credits']) // 10 requests per minute + credits check
        ->name('projects.generate-more');

    // CSV Upload for existing project (Generate More)
    Route::post('projects/{projectId}/csv', [CSVWizardController::class, 'storeForExistingProject'])
        ->middleware(['throttle.user:10,1', 'has.credits'])
        ->name('projects.csv.store');
    
    Route::get('projects/{id}/generation-progress', [ProjectController::class, 'generationProgress'])
        ->name('projects.generation-progress');

    // Image Management Routes
    Route::prefix('projects/{projectId}/images')->group(function () {
        Route::put('{imageId}', [ImageController::class, 'update'])->name('images.update');
        Route::delete('{imageId}', [ImageController::class, 'destroy'])->name('images.destroy');
        Route::post('{imageId}/regenerate', [ImageController::class, 'regenerate'])
            ->middleware(['throttle.user:10,1', 'has.credits'])
            ->name('images.regenerate');
        Route::post('bulk-delete', [ImageController::class, 'bulkDestroy'])->name('images.bulk-destroy');
        Route::post('bulk-download', [ImageController::class, 'bulkDownload'])->name('images.bulk-download');
        Route::post('update-order', [ImageController::class, 'updateOrder'])->name('images.update-order');
        Route::post('{imageId}/toggle-favorite', [ImageController::class, 'toggleFavorite'])->name('images.toggle-favorite');
    });

    // Search & Updates
    Route::get('search', [SearchController::class, 'index'])->name('search');
    Route::post('search', [SearchController::class, 'search'])->name('search.query');

    Route::get('updates', function () {
        return Inertia::render('updates');
    })->name('updates');

    // Canvas Editor
    Route::get('canvas-editor', function () {
        $projectId = request()->query('projectId');
        $imageUrl = request()->query('image');
        $projectTitle = request()->query('title', 'Untitled');
        
        return Inertia::render('canvas-editor', [
            'projectId' => $projectId,
            'imageUrl' => $imageUrl,
            'projectTitle' => $projectTitle
        ]);
    })->name('canvas.editor');

    // Canvas Editor Actions
    Route::post('projects/{projectId}/images/{imageId}/save-edit', [CanvasController::class, 'saveEdit'])
        ->name('canvas.save-edit');
    Route::post('projects/{projectId}/canvas/export', [CanvasController::class, 'exportCanvas'])
        ->name('canvas.export');
    
    // AI-powered canvas editing endpoint
    Route::post('/api/generate-with-mask', [ImageEditController::class, 'generateWithMask'])
        ->name('api.generate-with-mask');
    
    // AI-powered image expansion (outpainting)
    Route::post('/api/expand-image', [ImageEditController::class, 'expandImage'])
        ->name('api.expand-image');
    
    // Image upscaling
    Route::post('/api/upscale-image', [ImageEditController::class, 'upscaleImage'])
        ->name('api.upscale-image');
    
    // Background removal
    Route::post('/api/remove-background', [ImageEditController::class, 'removeBackground'])
        ->name('api.remove-background');
    
    // Resize canvas (crop or expand with AI)
    Route::post('/api/resize-canvas', [ImageEditController::class, 'resizeCanvas'])
        ->name('api.resize-canvas');
    
    // AI-powered prompt-based image generation/editing
    Route::post('/api/generate-from-prompt', [ImageEditController::class, 'generateFromPrompt'])
        ->name('api.generate-from-prompt');

    // AI Edit (image + prompt, no mask)
    Route::post('/api/ai-edit-image', [ImageEditController::class, 'aiEditImage'])
        ->name('api.ai-edit-image');

    // Erase (inpaint masked area and provide composite)
    Route::post('/api/erase-image', [ImageEditController::class, 'erase'])
        ->name('api.erase-image');
    
    // Subscription & Billing
    Route::prefix('subscription')->name('subscription.')->group(function () {
        Route::get('/plans', [\App\Http\Controllers\SubscriptionController::class, 'index'])->name('plans');
        Route::get('/portal', [\App\Http\Controllers\SubscriptionController::class, 'portal'])->name('portal');
        Route::post('/upgrade', [\App\Http\Controllers\SubscriptionController::class, 'upgrade'])->name('upgrade');
        Route::post('/downgrade', [\App\Http\Controllers\SubscriptionController::class, 'downgrade'])->name('downgrade');
        Route::post('/purchase-credits', [\App\Http\Controllers\SubscriptionController::class, 'purchaseCredits'])->name('purchase-credits');
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