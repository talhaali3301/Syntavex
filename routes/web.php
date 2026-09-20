<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReviewQueueController;
use App\Http\Controllers\RunInspectorController;
use App\Http\Controllers\RunsController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/runs', [RunsController::class, 'index'])->name('runs.index');

    // Declared before the wildcard so "export" is never read as a run id.
    Route::get('/runs/export', [RunsController::class, 'export'])->name('runs.export');

    Route::get('/runs/{run}', [RunInspectorController::class, 'show'])
        ->whereNumber('run')
        ->name('runs.show');

    Route::get('/reviews', [ReviewQueueController::class, 'index'])->name('reviews.index');

    Route::post('/reviews/{approval}/decision', [ReviewQueueController::class, 'decide'])
        ->name('reviews.decide');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';


