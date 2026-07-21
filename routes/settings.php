<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});

Route::middleware(['auth', 'verified', 'not.suspended'])->group(function () {
    Route::get('settings/subscription', [SubscriptionController::class, 'portal'])
        ->name('settings.subscription');
    Route::get('settings/invoices', [BillingController::class, 'index'])
        ->name('settings.invoices');
    Route::get('settings/invoices/{id}', [BillingController::class, 'show'])
        ->name('settings.invoices.show');
    Route::get('settings/invoices/{id}/download', [BillingController::class, 'downloadPdf'])
        ->name('settings.invoices.download');
    Route::post('settings/invoices/{id}/resend', [BillingController::class, 'resendReceipt'])
        ->middleware('throttle:5,1')
        ->name('settings.invoices.resend');
});
