<?php

use App\Http\Controllers\Marketing\AlternativeController;
use App\Http\Controllers\Marketing\BlogController;
use App\Http\Controllers\Marketing\ContactController;
use App\Http\Controllers\Marketing\GlossaryController;
use App\Http\Controllers\Marketing\PricingController;
use App\Http\Controllers\Marketing\TemplateController;
use App\Http\Controllers\Marketing\UseCaseController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// Public marketing pages. The home route lives in web.php because it
// redirects authenticated users to the dashboard.

Route::get('/robots.txt', RobotsController::class)->name('robots');

Route::get('/features', fn () => view('website.features'))->name('features');

Route::get('/pricing', [PricingController::class, 'index'])->name('pricing');

Route::get('/faq', fn () => view('website.faq'))->name('faq');

Route::get('/about', \App\Http\Controllers\Marketing\AboutController::class)->name('about');

Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'submit'])
    ->middleware('throttle:5,1')
    ->name('contact.submit');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->name('blog.show');

Route::get('/use-cases', [UseCaseController::class, 'index'])->name('use-cases.index');
Route::get('/use-cases/{slug}', [UseCaseController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->name('use-cases.show');

Route::get('/alternatives', [AlternativeController::class, 'index'])->name('alternatives.index');
Route::get('/alternatives/{slug}', [AlternativeController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->name('alternatives.show');
Route::get('/compare/{slug}-vs-snapdraft', [AlternativeController::class, 'compare'])
    ->where('slug', '[a-z0-9-]+')
    ->name('alternatives.compare');

Route::get('/glossary', [GlossaryController::class, 'index'])->name('glossary.index');
Route::get('/glossary/{slug}', [GlossaryController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->name('glossary.show');

Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
Route::get('/templates/{slug}', [TemplateController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->name('templates.show');

Route::get('/sitemap.xml', [SitemapController::class, '__invoke'])->name('sitemap');
Route::get('/sitemap-core.xml', [SitemapController::class, 'core'])->name('sitemap.core');
Route::get('/sitemap-blog.xml', [SitemapController::class, 'blog'])->name('sitemap.blog');
Route::get('/sitemap-use-cases.xml', [SitemapController::class, 'useCases'])->name('sitemap.use-cases');
Route::get('/sitemap-alternatives.xml', [SitemapController::class, 'alternatives'])->name('sitemap.alternatives');
Route::get('/sitemap-glossary.xml', [SitemapController::class, 'glossary'])->name('sitemap.glossary');
Route::get('/sitemap-templates.xml', [SitemapController::class, 'templates'])->name('sitemap.templates');
