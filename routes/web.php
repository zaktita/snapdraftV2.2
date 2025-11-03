<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\Wizards\CSVWizardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    // Project Creation - Wizard Selection Page (must be before resource routes)
    Route::get('projects/create', function () {
        return Inertia::render('projects/create');
    })->name('projects.create');

    // Project Creation Wizards
    Route::get('projects/create/csv', function () {
        return Inertia::render('projects/wizards/csv');
    })->name('projects.wizards.csv');

    Route::post('projects/wizards/csv', [CSVWizardController::class, 'store'])
        ->name('projects.wizards.csv.store');

    Route::get('projects/create/images', function () {
        return Inertia::render('projects/wizards/images');
    })->name('projects.wizards.images');

    Route::get('projects/create/text', function () {
        return Inertia::render('projects/wizards/text');
    })->name('projects.wizards.text');

    // Projects Resource Routes (except create, which we handle above)
    Route::resource('projects', ProjectController::class)->except(['create']);
    Route::post('projects/{id}/toggle-favorite', [ProjectController::class, 'toggleFavorite'])
        ->name('projects.toggle-favorite');

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
});

require __DIR__.'/settings.php';
require __DIR__.'/website.php';
