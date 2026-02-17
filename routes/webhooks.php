<?php

use App\Http\Controllers\Webhooks\LemonSqueezyController;
use Illuminate\Support\Facades\Route;

Route::post('/lemon-squeezy', [LemonSqueezyController::class, 'handle'])->name('webhooks.lemon-squeezy');