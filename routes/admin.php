<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // Admin Dashboard
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    
    // User Management
    Route::get('/users', [AdminDashboardController::class, 'users'])->name('users');
    Route::post('/users/{id}/suspend', [AdminDashboardController::class, 'suspendUser'])->name('users.suspend');
    Route::post('/users/{id}/reactivate', [AdminDashboardController::class, 'reactivateUser'])->name('users.reactivate');
    Route::put('/users/{id}/tier', [AdminDashboardController::class, 'updateUserTier'])->name('users.tier');
    Route::delete('/users/{id}', [AdminDashboardController::class, 'deleteUser'])->name('users.delete');
    
    // Usage Monitoring
    Route::get('/usage', [AdminDashboardController::class, 'usage'])->name('usage');
});
