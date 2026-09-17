<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ComingSoonController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin routes
|--------------------------------------------------------------------------
| Loaded from bootstrap/app.php with the 'web' middleware group, 'admin'
| prefix and 'admin.' route-name prefix already applied. No public
| registration exists anywhere here — see CLAUDE.md.
*/

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Super-Admin-only; enforced in UserController via $this->authorize(),
    // not by hiding the nav link.
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::patch('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');

    // Functionality lands in later phases (see docs/MIGRATION_PLAN.md's
    // phase list). Real nav entries now, honest "not built yet" pages
    // rather than dead links or fake functionality.
    Route::get('/templates', [ComingSoonController::class, 'templates'])->name('templates.index');
    Route::get('/certificates', [ComingSoonController::class, 'certificates'])->name('certificates.index');
    Route::get('/bulk-generation', [ComingSoonController::class, 'bulkGeneration'])->name('bulk-generation.index');
    Route::get('/batches', [ComingSoonController::class, 'batches'])->name('batches.index');
});
