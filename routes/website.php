<?php
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('website.home');
})->name('website.home');
