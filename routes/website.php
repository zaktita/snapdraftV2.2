<?php

use App\Http\Controllers\Marketing\BlogController;
use App\Http\Controllers\Marketing\ContactController;
use App\Http\Controllers\Marketing\PricingController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// Public marketing pages. The home route lives in web.php because it
// redirects authenticated users to the dashboard.

Route::get('/features', fn () => view('website.features'))->name('features');

Route::get('/pricing', [PricingController::class, 'index'])->name('pricing');

Route::get('/faq', fn () => view('website.faq'))->name('faq');

Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'submit'])
    ->middleware('throttle:5,1')
    ->name('contact.submit');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->name('blog.show');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
