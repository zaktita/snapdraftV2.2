<?php

use App\Http\Controllers\Marketing\HomeController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\BetaApplicationController;
use App\Http\Controllers\CanvasController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FalProxyController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\ImageEditController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\QuickGenerateController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SimpleTextWizardController;
use App\Http\Controllers\Wizards\CSVWizardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Google OAuth (guest middleware - must be accessible before login)
Route::middleware('guest')->group(function () {
    Route::get('/auth/google', [SocialAuthController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [SocialAuthController::class, 'callback'])->name('auth.google.callback');
});

// Home route: show marketing homepage when unauthenticated, dashboard when logged in
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return app(HomeController::class)();
})->name('home');

Route::get('/privacy', fn () => view('website.privacy'))->name('privacy');
Route::get('/terms', fn () => view('website.terms'))->name('terms');
Route::get('/refund', fn () => view('website.refund'))->name('refund');

// Guest-accessible beta invite validation + waitlist signup
Route::get('invite/validate', [\App\Http\Controllers\BetaInviteController::class, 'validateCode'])
    ->middleware('throttle:10,1')
    ->name('invite.validate');

Route::post('waitlist', [\App\Http\Controllers\WaitlistController::class, 'store'])
    ->middleware('throttle:3,1')
    ->name('waitlist.store');

Route::get('beta/apply', [BetaApplicationController::class, 'create'])
    ->name('beta.apply.form');

Route::post('beta/apply', [BetaApplicationController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('beta.apply');

Route::middleware(['auth', 'verified', 'not.suspended'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Private user media (auth-gated - replaces public /storage for uploads)
    Route::get('media/{path}', [\App\Http\Controllers\MediaController::class, 'show'])
        ->where('path', '.*')
        ->name('media.show');

    // Beta invite code redemption - needs auth but no subscription
    Route::post('invite/redeem', [\App\Http\Controllers\BetaInviteController::class, 'redeem'])
        ->middleware('throttle:5,5')
        ->name('invite.redeem');

    // Create routes must be registered before projects/{project} or "create" is captured as an ID.
    Route::middleware('has.credits')->group(function () {
        Route::get('projects/create', function () {
            return Inertia::render('projects/create');
        })->name('projects.create');

        Route::get('projects/create/csv', function () {
            return Inertia::render('projects/wizards/csv');
        })->name('projects.wizards.csv');
    });

    // ── Past projects (view/download) — entitled OR expired with existing projects ──
    Route::middleware('can.view.projects')->group(function () {
        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('projects/{project}', [ProjectController::class, 'show'])
            ->whereNumber('project')
            ->name('projects.show');
        Route::get('projects/{id}/generation-progress', [ProjectController::class, 'generationProgress'])
            ->whereNumber('id')
            ->name('projects.generation-progress');
        Route::post('projects/{projectId}/images/bulk-download', [ImageController::class, 'bulkDownload'])
            ->whereNumber('projectId')
            ->name('images.bulk-download');
    });

    // ── Subscription-gated area ─────────────────────────────────────────────
    // Everything below requires an entitled subscription + credits.
    // Non-subscribers are redirected to /subscription/plans.
    Route::middleware('has.credits')->group(function () {

        // Simple Wizard + quick-generate - local labs only (not in production product)
        if (app()->environment('local')) {
            Route::get('simple-wizard', [SimpleTextWizardController::class, 'index'])->name('simple-wizard.index');
            Route::post('simple-wizard/generate', [SimpleTextWizardController::class, 'generate'])->name('simple-wizard.generate');
        }

        // Project Creation Wizards
        Route::post('projects/wizards/csv', [CSVWizardController::class, 'store'])
            ->middleware(['check.project.limit', 'check.csv.limit', 'throttle.user:5,1'])
            ->name('projects.wizards.csv.store');

        Route::get('projects/wizards/csv/sessions/{session}', [CSVWizardController::class, 'show'])
            ->name('projects.wizards.csv.session');

        // Stub wizards, CSV cluster lab, test labs, quick-generate - local/testing only
        // User-facing create flow is only /projects/create → /projects/create/csv
        if (app()->environment(['local', 'testing'])) {
            Route::get('projects/create/csv-cluster', [\App\Http\Controllers\Wizards\ClusterCsvWizardController::class, 'create'])
                ->name('projects.wizards.csv-cluster');
            Route::post('projects/wizards/csv-cluster', [\App\Http\Controllers\Wizards\ClusterCsvWizardController::class, 'store'])
                ->middleware(['check.project.limit', 'check.csv.limit', 'throttle.user:5,1'])
                ->name('projects.wizards.csv-cluster.store');
            Route::get('projects/wizards/csv-cluster/sessions/{session}', [\App\Http\Controllers\Wizards\ClusterCsvWizardController::class, 'show'])
                ->name('projects.wizards.csv-cluster.session');
            Route::get('projects/wizards/csv-cluster/sessions/{session}/status', [\App\Http\Controllers\Wizards\ClusterCsvWizardController::class, 'status'])
                ->name('projects.wizards.csv-cluster.status');
            Route::get('projects/wizards/csv-cluster/sessions/{session}/result', [\App\Http\Controllers\Wizards\ClusterCsvWizardController::class, 'result'])
                ->name('projects.wizards.csv-cluster.result');
            Route::get('projects/wizards/csv-cluster/sessions/{session}/rows/{rowIndex}/debug', [\App\Http\Controllers\Wizards\ClusterCsvWizardController::class, 'rowDebug'])
                ->whereNumber('rowIndex')
                ->name('projects.wizards.csv-cluster.row-debug');

            Route::get('projects/create/images', function () {
                return Inertia::render('projects/wizards/images');
            })->name('projects.wizards.images');

            Route::get('projects/create/text', function () {
                return Inertia::render('projects/wizards/text');
            })->name('projects.wizards.text');

            Route::middleware('admin')->group(function () {
                Route::get('test/clustering', [\App\Http\Controllers\Test\ClusteringTestController::class, 'index'])
                    ->name('test.clustering');
                Route::post('test/clustering/analyze', [\App\Http\Controllers\Test\ClusteringTestController::class, 'analyze'])
                    ->name('test.clustering.analyze');
                Route::post('test/clustering/{project}/match', [\App\Http\Controllers\Test\ClusteringTestController::class, 'matchCaption'])
                    ->name('test.clustering.match');
                Route::post('test/clustering/{project}/generate-prompt', [\App\Http\Controllers\Test\ClusteringTestController::class, 'generatePrompt'])
                    ->name('test.clustering.generate-prompt');
                Route::post('test/clustering/{project}/generate-image', [\App\Http\Controllers\Test\ClusteringTestController::class, 'generateImage'])
                    ->name('test.clustering.generate-image');

                Route::get('test/cluster-generation', [\App\Http\Controllers\Test\ClusterTestController::class, 'index'])
                    ->name('test.cluster-generation');
                Route::post('test/cluster-generation/analyze', [\App\Http\Controllers\Test\ClusterTestController::class, 'analyze'])
                    ->name('test.cluster-generation.analyze');
                Route::post('test/cluster-generation/generate', [\App\Http\Controllers\Test\ClusterTestController::class, 'generate'])
                    ->name('test.cluster-generation.generate');

                Route::get('test/prompt-forge', [\App\Http\Controllers\Test\PromptForgeTestController::class, 'index'])
                    ->name('test.prompt-forge');
                Route::post('test/prompt-forge', [\App\Http\Controllers\Test\PromptForgeTestController::class, 'store'])
                    ->name('test.prompt-forge.store');
                Route::get('test/prompt-forge/sessions/{session}', [\App\Http\Controllers\Test\PromptForgeTestController::class, 'show'])
                    ->name('test.prompt-forge.session');
                Route::get('test/prompt-forge/sessions/{session}/status', [\App\Http\Controllers\Test\PromptForgeTestController::class, 'status'])
                    ->name('test.prompt-forge.status');
                Route::get('test/prompt-forge/sessions/{session}/result', [\App\Http\Controllers\Test\PromptForgeTestController::class, 'result'])
                    ->name('test.prompt-forge.result');
                Route::get('test/prompt-forge/sessions/{session}/rows/{rowIndex}/debug', [\App\Http\Controllers\Test\PromptForgeTestController::class, 'rowDebug'])
                    ->name('test.prompt-forge.row-debug');

                Route::get('test/master-prompt', [\App\Http\Controllers\Test\MasterPromptLabController::class, 'index'])
                    ->name('test.master-prompt');
                Route::post('test/master-prompt/build', [\App\Http\Controllers\Test\MasterPromptLabController::class, 'build'])
                    ->name('test.master-prompt.build');
                Route::post('test/master-prompt/generate', [\App\Http\Controllers\Test\MasterPromptLabController::class, 'generate'])
                    ->name('test.master-prompt.generate');

                Route::get('test/clustered-master-prompt', [\App\Http\Controllers\Test\ClusteredMasterPromptLabController::class, 'index'])
                    ->name('test.clustered-master-prompt');
                Route::post('test/clustered-master-prompt/cluster', [\App\Http\Controllers\Test\ClusteredMasterPromptLabController::class, 'cluster'])
                    ->name('test.clustered-master-prompt.cluster');
                Route::post('test/clustered-master-prompt/match', [\App\Http\Controllers\Test\ClusteredMasterPromptLabController::class, 'match'])
                    ->name('test.clustered-master-prompt.match');
                Route::post('test/clustered-master-prompt/match-batch', [\App\Http\Controllers\Test\ClusteredMasterPromptLabController::class, 'matchBatch'])
                    ->name('test.clustered-master-prompt.match-batch');
                Route::post('test/clustered-master-prompt/run-row', [\App\Http\Controllers\Test\ClusteredMasterPromptLabController::class, 'runRow'])
                    ->name('test.clustered-master-prompt.run-row');
                Route::post('test/clustered-master-prompt/build', [\App\Http\Controllers\Test\ClusteredMasterPromptLabController::class, 'build'])
                    ->name('test.clustered-master-prompt.build');
                Route::post('test/clustered-master-prompt/generate', [\App\Http\Controllers\Test\ClusteredMasterPromptLabController::class, 'generate'])
                    ->name('test.clustered-master-prompt.generate');
            });

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
        }

        // Project mutations (create/update/delete stay subscription-gated)
        Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::patch('projects/{project}', [ProjectController::class, 'update']);
        Route::post('projects', [ProjectController::class, 'store'])
            ->middleware(['check.project.limit', 'throttle.user:20,1'])
            ->name('projects.store');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])
            ->middleware('throttle.user:20,1')
            ->name('projects.destroy');
        Route::post('projects/{id}/toggle-favorite', [ProjectController::class, 'toggleFavorite'])
            ->name('projects.toggle-favorite');

        // AI Generation Routes
        Route::post('projects/{id}/generate', [ProjectController::class, 'generateMore'])
            ->middleware(['check.csv.limit', 'throttle.user:10,1'])
            ->name('projects.generate-more');

        if (app()->environment('local')) {
            Route::get('projects/{id}/images/{imageId}/generation-debug', [ProjectController::class, 'imageGenerationDebug'])
                ->name('projects.images.generation-debug');
        }

        // Image Management Routes (mutations)
        Route::prefix('projects/{projectId}/images')->group(function () {
            Route::put('{imageId}', [ImageController::class, 'update'])->name('images.update');
            Route::delete('{imageId}', [ImageController::class, 'destroy'])->name('images.destroy');
            Route::post('{imageId}/regenerate', [ImageController::class, 'regenerate'])
                ->middleware('throttle.user:10,1')
                ->name('images.regenerate');
            Route::post('bulk-delete', [ImageController::class, 'bulkDestroy'])->name('images.bulk-destroy');
            Route::post('update-order', [ImageController::class, 'updateOrder'])->name('images.update-order');
            Route::post('{imageId}/toggle-favorite', [ImageController::class, 'toggleFavorite'])->name('images.toggle-favorite');
        });

        // Canvas Editor
        Route::get('canvas-editor', function () {
            $projectId = request()->query('projectId');
            $imageUrl = request()->query('image');
            $projectTitle = request()->query('title', 'Untitled');
            $imageId = request()->query('imageId');

            return Inertia::render('canvas-editor', [
                'projectId' => $projectId ? (int) $projectId : null,
                'imageUrl' => $imageUrl,
                'projectTitle' => $projectTitle,
                'imageId' => $imageId ? (int) $imageId : null,
            ]);
        })->name('canvas.editor');

        // Canvas Editor Actions
        Route::post('projects/{projectId}/images/{imageId}/save-edit', [CanvasController::class, 'saveEdit'])
            ->name('canvas.save-edit');
        Route::post('projects/{projectId}/canvas/export', [CanvasController::class, 'exportCanvas'])
            ->name('canvas.export');

        // AI-powered canvas editing - MVP operations
        Route::post('/api/generate-with-mask', [ImageEditController::class, 'generateWithMask'])
            ->middleware('throttle.user:20,1')
            ->name('api.generate-with-mask');

        Route::post('/api/ai-edit-image', [ImageEditController::class, 'aiEditImage'])
            ->middleware('throttle.user:20,1')
            ->name('api.ai-edit-image');

        Route::post('/api/erase-image', [ImageEditController::class, 'erase'])
            ->middleware('throttle.user:20,1')
            ->name('api.erase-image');

        // Advanced canvas operations
        Route::post('/api/expand-image', [ImageEditController::class, 'expandImage'])
            ->middleware('throttle.user:10,1')
            ->name('api.expand-image');
        Route::post('/api/upscale-image', [ImageEditController::class, 'upscaleImage'])
            ->middleware('throttle.user:10,1')
            ->name('api.upscale-image');
        Route::post('/api/remove-background', [ImageEditController::class, 'removeBackground'])
            ->middleware('throttle.user:10,1')
            ->name('api.remove-background');
        Route::post('/api/resize-canvas', [ImageEditController::class, 'resizeCanvas'])
            ->middleware('throttle.user:20,1')
            ->name('api.resize-canvas');
        Route::post('/api/generate-from-prompt', [ImageEditController::class, 'generateFromPrompt'])
            ->middleware('throttle.user:10,1')
            ->name('api.generate-from-prompt');

        // FAL.ai AI upscale - proxied server-side so the API key stays off the client
        Route::post('/api/fal/upscale/submit', [FalProxyController::class, 'submitUpscale'])
            ->middleware('throttle.user:5,1')
            ->name('api.fal.upscale.submit');
        Route::get('/api/fal/upscale/status/{requestId}', [FalProxyController::class, 'upscaleStatus'])
            ->middleware('throttle.user:120,1')
            ->name('api.fal.upscale.status');

    }); // end has.credits group

    // Search & Updates (accessible without subscription - view-only)
    Route::get('search', [SearchController::class, 'index'])->name('search');
    Route::post('search', [SearchController::class, 'search'])->name('search.query');

    Route::get('updates', function () {
        return Inertia::render('updates');
    })->name('updates');

    // Feedback
    Route::get('/feedback', [FeedbackController::class, 'show'])->name('feedback');
    Route::post('/feedback', [FeedbackController::class, 'submit'])->name('feedback.submit');
    Route::get('/feedback/thank-you', [FeedbackController::class, 'thankYou'])->name('feedback.thank-you');

    // Subscription & Billing (actions stay here; settings UI lives under /settings/*)
    Route::prefix('subscription')->name('subscription.')->group(function () {
        Route::get('/plans', [\App\Http\Controllers\SubscriptionController::class, 'index'])->name('plans');
        Route::redirect('/portal', '/settings/subscription')->name('portal');
        Route::post('/upgrade', [\App\Http\Controllers\SubscriptionController::class, 'upgrade'])
            ->middleware('throttle:10,1')
            ->name('upgrade');
        Route::post('/downgrade', [\App\Http\Controllers\SubscriptionController::class, 'downgrade'])
            ->middleware('throttle:10,1')
            ->name('downgrade');
        Route::post('/resume', [\App\Http\Controllers\SubscriptionController::class, 'resume'])
            ->middleware('throttle:10,1')
            ->name('resume');
        Route::post('/purchase-credits', [\App\Http\Controllers\SubscriptionController::class, 'purchaseCredits'])
            ->middleware('throttle:10,1')
            ->name('purchase-credits');
    });

    // Legacy billing URLs → settings
    Route::redirect('/billing/invoices', '/settings/invoices')->name('billing.invoices');
    Route::get('/billing/invoices/{id}', function (string $id) {
        return redirect()->route('settings.invoices.show', $id);
    })->name('billing.invoices.show');
    Route::get('/billing/invoices/{id}/download', function (string $id) {
        return redirect()->route('settings.invoices.download', $id);
    })->name('billing.invoices.download');
    Route::post('/billing/invoices/{id}/resend', [\App\Http\Controllers\BillingController::class, 'resendReceipt'])
        ->middleware('throttle:5,1')
        ->name('billing.invoices.resend');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/website.php';
require __DIR__.'/webhooks.php';
