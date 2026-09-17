<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ComingSoonController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\TemplateFieldController;
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

    // Certificate templates + their dynamic fields — both admin roles may
    // manage these (see App\Policies\CertificateTemplatePolicy), unlike
    // Users above. No 'show' route: the edit page IS the template's detail/
    // management page (metadata + field list + preview), matching the
    // pattern already used for Users. See docs/CERTIFICATE_SYSTEM.md.
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::get('/templates/create', [TemplateController::class, 'create'])->name('templates.create');
    Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
    Route::get('/templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
    Route::patch('/templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
    Route::post('/templates/{template}/activate', [TemplateController::class, 'activate'])->name('templates.activate');
    Route::post('/templates/{template}/archive', [TemplateController::class, 'archive'])->name('templates.archive');

    Route::get('/templates/{template}/fields/create', [TemplateFieldController::class, 'create'])->name('templates.fields.create');
    Route::post('/templates/{template}/fields', [TemplateFieldController::class, 'store'])->name('templates.fields.store');
    Route::get('/templates/{template}/fields/{field}/edit', [TemplateFieldController::class, 'edit'])->name('templates.fields.edit');
    Route::patch('/templates/{template}/fields/{field}', [TemplateFieldController::class, 'update'])->name('templates.fields.update');
    Route::delete('/templates/{template}/fields/{field}', [TemplateFieldController::class, 'destroy'])->name('templates.fields.destroy');
    Route::post('/templates/{template}/fields/{field}/move-up', [TemplateFieldController::class, 'moveUp'])->name('templates.fields.move-up');
    Route::post('/templates/{template}/fields/{field}/move-down', [TemplateFieldController::class, 'moveDown'])->name('templates.fields.move-down');

    // Functionality lands in later phases (see docs/MIGRATION_PLAN.md's
    // phase list). Real nav entries now, honest "not built yet" pages
    // rather than dead links or fake functionality.
    Route::get('/certificates', [ComingSoonController::class, 'certificates'])->name('certificates.index');
    Route::get('/bulk-generation', [ComingSoonController::class, 'bulkGeneration'])->name('bulk-generation.index');
    Route::get('/batches', [ComingSoonController::class, 'batches'])->name('batches.index');
});
