<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CertificateController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeletionRequestController;
use App\Http\Controllers\Admin\PdfCertificatesController;
use App\Http\Controllers\Admin\PdfStudioApiController;
use App\Http\Controllers\Admin\PdfStudioController;
use App\Http\Controllers\Admin\QrTool\QrCategoryController;
use App\Http\Controllers\Admin\QrTool\QrCategoryFieldController;
use App\Http\Controllers\Admin\QrTool\QrGenerateController;
use App\Http\Controllers\Admin\QrTool\QrGroupController;
use App\Http\Controllers\Admin\QrTool\QrImportController;
use App\Http\Controllers\Admin\QrTool\QrOptionsController;
use App\Http\Controllers\Admin\QrTool\QrRecordsController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin routes
|--------------------------------------------------------------------------
| Loaded from bootstrap/app.php with the 'web' middleware group, 'admin'
| prefix and 'admin.' route-name prefix already applied. No public
| registration exists anywhere here — see CLAUDE.md.
|
| Two independent admin tools live here (see docs/PDF_STUDIO_INTEGRATION.md
| for the full product writeup):
|   A. PDF Certificates (pdf-certificates.* / pdf-studio.* / pdf-studio.api.*)
|   B. QR Generator (qr.*) — fully independent of A, unaffected by it.
| Form + Page Builder (forms.* / pages.*) are a separate package, untouched.
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

    // ------------------------------------------------------------------
    // A. PDF Certificates — see docs/PDF_STUDIO_INTEGRATION.md.
    // "New template or Saved templates" -> the real pdfeditor-based
    // editor (pdf-studio.*) -> Excel instructions/import/preview/confirm
    // (pdf-studio.api.*) -> batch history/resume/re-download, all one
    // connected flow, no legacy designer/manual-handoff step anywhere.
    // ------------------------------------------------------------------
    Route::prefix('pdf-certificates')->name('pdf-certificates.')->group(function () {
        Route::get('/', [PdfCertificatesController::class, 'index'])->name('index');
        Route::get('/create', [PdfCertificatesController::class, 'create'])->name('create');
        Route::post('/', [PdfCertificatesController::class, 'store'])->name('store');
        Route::get('/{template}/edit', [PdfCertificatesController::class, 'edit'])->name('edit');
        Route::patch('/{template}', [PdfCertificatesController::class, 'update'])->name('update');
        Route::post('/{template}/archive', [PdfCertificatesController::class, 'archive'])->name('archive');
        Route::get('/{template}/batches', [PdfCertificatesController::class, 'batches'])->name('batches');
    });

    // Legacy entry points, redirected to their PDF Certificates
    // replacement rather than left dangling — see docs/CHANGELOG.md's
    // "Admin workflow cleanup" entry for the full before/after map.
    Route::get('/templates', fn () => redirect()->route('admin.pdf-certificates.index'));
    Route::get('/templates/create', fn () => redirect()->route('admin.pdf-certificates.create'));
    Route::get('/templates/{template}/edit', fn ($template) => redirect()->route('admin.pdf-studio.show', $template));
    Route::get('/certificates/issue', fn () => redirect()->route('admin.pdf-certificates.index'));

    // Mounts the embedded, real pdfeditor build for a given template (and,
    // optionally, straight into Generate mode for a given batch). Serves
    // the editor shell; the JSON API below is what its adapter code
    // (studio.html -> src/integration/ in the pdfeditor repo) calls, all
    // same-origin, auth+CSRF protected like everything else here.
    Route::get('/certificates/studio/{template}', [PdfStudioController::class, 'show'])->name('pdf-studio.show');
    Route::get('/certificates/studio/{template}/prepare', [PdfStudioController::class, 'prepare'])->name('pdf-studio.prepare');
    Route::get('/certificates/studio/{template}/batches/{batch}', [PdfStudioController::class, 'show'])->name('pdf-studio.show-batch');

    Route::prefix('api/pdf-studio')->name('pdf-studio.api.')->group(function () {
        Route::get('/templates/{template}/source-pdf', [PdfStudioApiController::class, 'sourcePdf'])->name('templates.source-pdf');
        Route::get('/templates/{template}/project', [PdfStudioApiController::class, 'getProject'])->name('templates.project.show');
        Route::put('/templates/{template}/project', [PdfStudioApiController::class, 'saveProject'])->name('templates.project.store');
        Route::get('/templates/{template}/schema', [PdfStudioApiController::class, 'schema'])->name('templates.schema');
        Route::get('/templates/{template}/sample.xlsx', [PdfStudioApiController::class, 'sampleXlsx'])->name('templates.sample');
        Route::post('/templates/{template}/batches', [PdfStudioApiController::class, 'prepareBatch'])->name('templates.batches.prepare');
        Route::post('/templates/{template}/batches/confirm', [PdfStudioApiController::class, 'confirmBatch'])->name('templates.batches.confirm');

        Route::get('/batches/{batch}/project', [PdfStudioApiController::class, 'getBatchProject'])->name('batches.project');
        Route::get('/batches/{batch}/manifest', [PdfStudioApiController::class, 'manifest'])->name('batches.manifest');
        Route::get('/batches/{batch}/status', [PdfStudioApiController::class, 'status'])->name('batches.status');
        Route::get('/batches/{batch}/download.zip', [PdfStudioApiController::class, 'downloadZip'])->name('batches.download');

        Route::post('/reservations/{reservation}/finalize', [PdfStudioApiController::class, 'finalizeReservation'])->name('reservations.finalize');
    });
    // Live, deterministic, never persisted separately from the codeword —
    // see QrCodeService::pngBytes()'s own docblock for why there's nothing
    // to cache here. Named outside the api. group so PdfStudioApiController
    // can reference them via route() without the prefix.
    Route::get('/api/pdf-studio/reservations/{reservation}/qr.png', [PdfStudioApiController::class, 'qrImage'])->name('pdf-studio.reservations.qr');
    Route::get('/api/pdf-studio/reservations/{reservation}/photo', [PdfStudioApiController::class, 'photo'])->name('pdf-studio.reservations.photo');

    // Certificate RECORDS browser — every certificate issued through PDF
    // Certificates lands in this same `certificates` table, so this stays
    // as the one place to search/view/download/audit them. The old manual
    // single-certificate issuance form (choose-template/create/store) is
    // removed; PDF Certificates' batch flow is the only way to issue one
    // now, even for a single recipient. No 'edit'/'destroy' — issued
    // certificates are immutable; revoke/reissue are a later phase.
    Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index');
    // Must be registered before the {certificate}-bound routes below, or
    // "deleted" would be parsed as a certificate id. Read-only, super_admin
    // only — see docs/CERTIFICATE_SYSTEM.md §Admin lists.
    Route::get('/certificates/deleted', [CertificateController::class, 'deleted'])->name('certificates.deleted');
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

    // ------------------------------------------------------------------
    // B. QR Generator — fully independent of PDF Certificates/
    // CertificateTemplate/PDF Studio. See docs/CERTIFICATE_SYSTEM.md
    // §Simple QR tool. Literal-segment routes (/generate, /records,
    // /import, /categories) live under their own prefix specifically so
    // they never collide with or get swallowed by the /certificates/*
    // model-bound routes above.
    // ------------------------------------------------------------------
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

    // Form + Page Builder: the routes (/admin/forms/..., /admin/pages/...,
    // named admin.forms.* / admin.pages.*) are registered by the
    // tman1an/formbuilder package with this app's 'auth' + 'active'
    // middleware -- see config/formbuilder.php and docs/FORM_BUILDER.md.
    // Untouched by this cleanup.

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
});
