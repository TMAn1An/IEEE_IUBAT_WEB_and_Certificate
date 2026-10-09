<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CertificateController;
use App\Http\Controllers\Admin\ComingSoonController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeletionRequestController;
use App\Http\Controllers\Admin\Forms\FormController;
use App\Http\Controllers\Admin\Forms\FormSubmissionController;
use App\Http\Controllers\Admin\QrTool\QrCategoryController;
use App\Http\Controllers\Admin\QrTool\QrCategoryFieldController;
use App\Http\Controllers\Admin\QrTool\QrGenerateController;
use App\Http\Controllers\Admin\QrTool\QrGroupController;
use App\Http\Controllers\Admin\QrTool\QrImportController;
use App\Http\Controllers\Admin\QrTool\QrOptionsController;
use App\Http\Controllers\Admin\QrTool\QrRecordsController;
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

    // Single-certificate issuance (Phase 5, PDF-designer path — advanced/
    // future, paused, not removed; see docs/CERTIFICATE_SYSTEM.md §Simple
    // QR tool for the isolation boundary). No 'edit'/'destroy' -- issued
    // certificates are immutable (§Snapshot strategy); revoke/reissue are
    // Phase 8.
    Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index');
    // Must be registered before the {certificate}-bound routes below, or
    // "deleted" would be parsed as a certificate id. Read-only, super_admin
    // only — see docs/CERTIFICATE_SYSTEM.md §Admin lists.
    Route::get('/certificates/deleted', [CertificateController::class, 'deleted'])->name('certificates.deleted');
    Route::get('/certificates/issue', [CertificateController::class, 'chooseTemplate'])->name('certificates.choose-template');
    Route::get('/certificates/issue/{template}', [CertificateController::class, 'create'])->name('certificates.create');
    Route::post('/certificates/issue/{template}', [CertificateController::class, 'store'])->name('certificates.store');
    // withTrashed(): a soft-deleted certificate's detail page still
    // resolves (rather than 404ing) so its "Record Deleted / Completed at"
    // state can be shown -- see docs/CERTIFICATE_SYSTEM.md §Record detail
    // UI. Every OTHER certificate route (index, download, edit-adjacent
    // actions) stays on the default trashed-excluding binding.
    Route::get('/certificates/{certificate}', [CertificateController::class, 'show'])->name('certificates.show')->withTrashed();
    Route::get('/certificates/{certificate}/download', [CertificateController::class, 'download'])->name('certificates.download');
    // Deliberately no DELETE /certificates/{certificate} route anywhere in
    // this file -- see §CORE RULE in docs/CERTIFICATE_SYSTEM.md §Controlled
    // deletion. The only path to a soft-deleted certificate is the
    // request/review workflow below.
    Route::post('/certificates/{certificate}/request-deletion', [DeletionRequestController::class, 'requestForCertificate'])->name('certificates.request-deletion');

    // The simple QR tool — fully independent of CertificateTemplate/the PDF
    // designer/activation lifecycle. See docs/CERTIFICATE_SYSTEM.md §Simple
    // QR tool. Literal-segment routes (/generate, /records, /import,
    // /categories) live under their own prefix specifically so they never
    // collide with or get swallowed by the advanced /certificates/*
    // model-bound routes above.
    Route::prefix('qr-tool')->name('qr.')->group(function () {
        // The old-tool-parity page -- one screen, bound to a single fixed
        // category (config('qr-tool.primary_category_slug')), not a
        // category the admin picks. See docs/CERTIFICATE_SYSTEM.md §Simple
        // QR tool: old-tool-parity rebuild.
        Route::get('/generate', [QrGenerateController::class, 'show'])->name('generate.show');
        Route::post('/generate', [QrGenerateController::class, 'store'])->name('generate.store');
        Route::get('/generate/export.xlsx', [QrGenerateController::class, 'downloadExcel'])->name('generate.download-excel');

        // Persists the old tool's "Add role option"/"Add type option"/
        // "Add conference name" buttons server-side instead of in
        // localStorage -- small JSON endpoints, no page reload.
        Route::post('/options/roles/add', [QrOptionsController::class, 'addRole'])->name('options.roles.add');
        Route::post('/options/roles/remove', [QrOptionsController::class, 'removeRole'])->name('options.roles.remove');
        Route::post('/options/conference-types/add', [QrOptionsController::class, 'addConferenceType'])->name('options.conference-types.add');
        Route::post('/options/conference-types/remove', [QrOptionsController::class, 'removeConferenceType'])->name('options.conference-types.remove');
        Route::post('/options/conference-options/add', [QrOptionsController::class, 'addConferenceOption'])->name('options.conference-options.add');
        Route::post('/options/conference-options/remove', [QrOptionsController::class, 'removeConferenceOption'])->name('options.conference-options.remove');

        Route::get('/records', [QrRecordsController::class, 'index'])->name('records.index');
        // Must be registered before the {certificate}-bound routes below --
        // same reasoning as certificates.deleted above.
        Route::get('/records/deleted', [QrRecordsController::class, 'deleted'])->name('records.deleted');
        // withTrashed() -- see the identical comment on certificates.show above.
        Route::get('/records/{certificate}', [QrRecordsController::class, 'show'])->name('records.show')->withTrashed();
        Route::get('/records/{certificate}/qr.png', [QrGenerateController::class, 'qrImage'])->name('records.qr-image');
        // No DELETE route here either -- same §CORE RULE as the advanced
        // certificates group above.
        Route::post('/records/{certificate}/request-deletion', [DeletionRequestController::class, 'requestForQrCertificate'])->name('records.request-deletion');

        // Auto-created Event Type + Event Name + Role combinations — see
        // QrGroupService and docs/CERTIFICATE_SYSTEM.md §Simple QR tool:
        // automatic grouping. Never created via a form of their own fields;
        // only "create-group" below (a convenience wrapper around the same
        // find-or-create resolver used by Generate QR and by import).
        Route::get('/groups', [QrGroupController::class, 'index'])->name('groups.index');
        Route::get('/groups/{group}', [QrGroupController::class, 'show'])->name('groups.show');
        Route::get('/groups/{group}/export.xlsx', [QrGroupController::class, 'export'])->name('groups.export');

        // One importer implementation, always group-based: a per-group
        // "Import Excel" link skips straight to /import/{group}; the
        // general "Import Excel" nav entry starts at chooseGroup() and
        // lands on the exact same upload/mapping/preview/confirm routes.
        Route::get('/import', [QrImportController::class, 'chooseGroup'])->name('import.choose-group');
        Route::post('/import/create-group', [QrImportController::class, 'createGroupAndRedirect'])->name('import.create-group');
        Route::get('/import/{group}', [QrImportController::class, 'showUpload'])->name('import.upload');
        Route::post('/import/{group}/upload', [QrImportController::class, 'handleUpload'])->name('import.upload.store');
        Route::post('/import/{group}/preview', [QrImportController::class, 'preview'])->name('import.preview');
        Route::post('/import/{group}/errors', [QrImportController::class, 'errorReport'])->name('import.errors');
        Route::post('/import/{group}/confirm', [QrImportController::class, 'confirm'])->name('import.confirm');

        Route::get('/categories', [QrCategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/create', [QrCategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [QrCategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}/edit', [QrCategoryController::class, 'edit'])->name('categories.edit');
        Route::patch('/categories/{category}', [QrCategoryController::class, 'update'])->name('categories.update');
        Route::post('/categories/{category}/activate', [QrCategoryController::class, 'activate'])->name('categories.activate');
        Route::post('/categories/{category}/deactivate', [QrCategoryController::class, 'deactivate'])->name('categories.deactivate');

        Route::get('/categories/{category}/fields/create', [QrCategoryFieldController::class, 'create'])->name('categories.fields.create');
        Route::post('/categories/{category}/fields', [QrCategoryFieldController::class, 'store'])->name('categories.fields.store');
        Route::get('/categories/{category}/fields/{field}/edit', [QrCategoryFieldController::class, 'edit'])->name('categories.fields.edit');
        Route::patch('/categories/{category}/fields/{field}', [QrCategoryFieldController::class, 'update'])->name('categories.fields.update');
        Route::delete('/categories/{category}/fields/{field}', [QrCategoryFieldController::class, 'destroy'])->name('categories.fields.destroy');
        Route::post('/categories/{category}/fields/{field}/move-up', [QrCategoryFieldController::class, 'moveUp'])->name('categories.fields.move-up');
        Route::post('/categories/{category}/fields/{field}/move-down', [QrCategoryFieldController::class, 'moveDown'])->name('categories.fields.move-down');
    });

    // Dynamic Form Builder -- a general-purpose module, independent of the
    // certificate/QR systems. Authorization: App\Policies\FormPolicy.
    // Deliberately no DELETE route for forms or submissions: forms are
    // archived, submissions are permanent. See docs/FORM_BUILDER.md.
    Route::prefix('forms')->name('forms.')->group(function () {
        Route::get('/', [FormController::class, 'index'])->name('index');
        Route::get('/create', [FormController::class, 'create'])->name('create');
        Route::post('/', [FormController::class, 'store'])->name('store');
        // Literal segment -- registered before the {form}-bound routes.
        Route::get('/submissions', [FormSubmissionController::class, 'overview'])->name('submissions.overview');

        Route::get('/{form}/edit', [FormController::class, 'edit'])->name('edit');
        Route::put('/{form}/builder', [FormController::class, 'update'])->name('update');
        Route::post('/{form}/builder/sanitize', [FormController::class, 'sanitize'])->name('sanitize');
        Route::get('/{form}/preview', [FormController::class, 'preview'])->name('preview');
        Route::post('/{form}/publish', [FormController::class, 'publish'])->name('publish');
        Route::post('/{form}/deactivate', [FormController::class, 'deactivate'])->name('deactivate');
        Route::post('/{form}/archive', [FormController::class, 'archive'])->name('archive');
        Route::post('/{form}/restore', [FormController::class, 'restore'])->name('restore');
        Route::post('/{form}/duplicate', [FormController::class, 'duplicate'])->name('duplicate');

        Route::get('/{form}/submissions', [FormSubmissionController::class, 'index'])->name('submissions.index');
        Route::get('/{form}/submissions/export.xlsx', [FormSubmissionController::class, 'export'])->name('submissions.export');
        // scopeBindings(): a submission id from another form 404s (no cross-form IDOR).
        Route::get('/{form}/submissions/{submission}', [FormSubmissionController::class, 'show'])->name('submissions.show')->scopeBindings();
    });

    // Controlled deletion — request -> Super Admin review -> approve/reject.
    // Covers both simple QR records and advanced certificates through the
    // one safe DeletableRecordType enum mapping. See
    // docs/CERTIFICATE_SYSTEM.md §Controlled deletion. Both request-
    // deletion POST routes live above, next to the record type they
    // target; only the review queue itself lives here.
    Route::get('/deletion-requests', [DeletionRequestController::class, 'index'])->name('deletion-requests.index');
    Route::post('/deletion-requests/{deletionRequest}/approve', [DeletionRequestController::class, 'approve'])->name('deletion-requests.approve');
    Route::post('/deletion-requests/{deletionRequest}/reject', [DeletionRequestController::class, 'reject'])->name('deletion-requests.reject');

    // Read-only audit trail -- no edit/delete route exists anywhere for
    // this resource, on purpose. See App\Policies\AuditLogPolicy.
    Route::get('/logbook', [AuditLogController::class, 'index'])->name('logbook.index');

    // Functionality lands in later phases (see docs/MIGRATION_PLAN.md's
    // phase list). Real nav entries now, honest "not built yet" pages
    // rather than dead links or fake functionality.
    Route::get('/bulk-generation', [ComingSoonController::class, 'bulkGeneration'])->name('bulk-generation.index');
    Route::get('/batches', [ComingSoonController::class, 'batches'])->name('batches.index');
});
