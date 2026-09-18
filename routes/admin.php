<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CertificateController;
use App\Http\Controllers\Admin\CertificateImportController;
use App\Http\Controllers\Admin\CertificateQrController;
use App\Http\Controllers\Admin\ComingSoonController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\TemplateDesignerController;
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

    // Background PDF + visual designer (Phase 4). showBackground is a GET so
    // the designer's PDF.js viewer can fetch it directly (same-origin,
    // session-cookie authorized) — see docs/CERTIFICATE_SYSTEM.md.
    Route::post('/templates/{template}/background', [TemplateController::class, 'uploadBackground'])->name('templates.background.store');
    Route::get('/templates/{template}/background', [TemplateController::class, 'showBackground'])->name('templates.background.show');
    Route::get('/templates/{template}/designer', [TemplateDesignerController::class, 'edit'])->name('templates.designer.edit');
    Route::post('/templates/{template}/designer', [TemplateDesignerController::class, 'update'])->name('templates.designer.update');

    Route::get('/templates/{template}/fields/create', [TemplateFieldController::class, 'create'])->name('templates.fields.create');
    Route::post('/templates/{template}/fields', [TemplateFieldController::class, 'store'])->name('templates.fields.store');
    Route::get('/templates/{template}/fields/{field}/edit', [TemplateFieldController::class, 'edit'])->name('templates.fields.edit');
    Route::patch('/templates/{template}/fields/{field}', [TemplateFieldController::class, 'update'])->name('templates.fields.update');
    Route::delete('/templates/{template}/fields/{field}', [TemplateFieldController::class, 'destroy'])->name('templates.fields.destroy');
    Route::post('/templates/{template}/fields/{field}/move-up', [TemplateFieldController::class, 'moveUp'])->name('templates.fields.move-up');
    Route::post('/templates/{template}/fields/{field}/move-down', [TemplateFieldController::class, 'moveDown'])->name('templates.fields.move-down');

    // Single-certificate issuance (Phase 5, PDF-designer path — paused, not
    // removed; see docs/CERTIFICATE_SYSTEM.md §Simplified QR workflow). No
    // 'edit'/'destroy' -- issued certificates are immutable (§Snapshot
    // strategy); revoke/reissue are Phase 8. Literal-segment routes
    // (/issue, /generate-qr, /import) must come before /{certificate} so
    // they aren't swallowed by the model-bound route.
    Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index');
    Route::get('/certificates/issue', [CertificateController::class, 'chooseTemplate'])->name('certificates.choose-template');
    Route::get('/certificates/issue/{template}', [CertificateController::class, 'create'])->name('certificates.create');
    Route::post('/certificates/issue/{template}', [CertificateController::class, 'store'])->name('certificates.store');

    // Phase 6 — the simplified, primary workflow: dynamic form -> DB row +
    // codeword + on-demand QR, no PDF. See docs/CERTIFICATE_SYSTEM.md
    // §Simplified QR workflow.
    Route::get('/certificates/generate-qr', [CertificateQrController::class, 'chooseTemplate'])->name('certificates.qr.choose-template');
    Route::get('/certificates/generate-qr/{template}', [CertificateQrController::class, 'create'])->name('certificates.qr.create');
    Route::post('/certificates/generate-qr/{template}', [CertificateQrController::class, 'store'])->name('certificates.qr.store');
    Route::get('/certificates/{certificate}/qr.png', [CertificateQrController::class, 'qrImage'])->name('certificates.qr.image');

    // Phase 6 — historical Excel import with column mapping. Each step
    // resubmits the stored file's UUID (never a client-supplied path) plus
    // the chosen mapping; see docs/CERTIFICATE_SYSTEM.md §Excel import.
    Route::get('/certificates/import', [CertificateImportController::class, 'chooseTemplate'])->name('certificates.import.choose-template');
    Route::get('/certificates/import/{template}', [CertificateImportController::class, 'showUpload'])->name('certificates.import.upload');
    Route::post('/certificates/import/{template}/upload', [CertificateImportController::class, 'handleUpload'])->name('certificates.import.upload.store');
    Route::post('/certificates/import/{template}/preview', [CertificateImportController::class, 'preview'])->name('certificates.import.preview');
    Route::post('/certificates/import/{template}/errors', [CertificateImportController::class, 'errorReport'])->name('certificates.import.errors');
    Route::post('/certificates/import/{template}/confirm', [CertificateImportController::class, 'confirm'])->name('certificates.import.confirm');

    Route::get('/certificates/{certificate}', [CertificateController::class, 'show'])->name('certificates.show');
    Route::get('/certificates/{certificate}/download', [CertificateController::class, 'download'])->name('certificates.download');

    // Functionality lands in later phases (see docs/MIGRATION_PLAN.md's
    // phase list). Real nav entries now, honest "not built yet" pages
    // rather than dead links or fake functionality.
    Route::get('/bulk-generation', [ComingSoonController::class, 'bulkGeneration'])->name('bulk-generation.index');
    Route::get('/batches', [ComingSoonController::class, 'batches'])->name('batches.index');
});
