<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateBatch;
use App\Models\CertificateTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

/**
 * Serves the pinned, pre-built pdfeditor "studio.html" shell (see
 * docs/PDF_STUDIO_INTEGRATION.md and scripts/sync-pdf-editor-assets.php)
 * with a per-request config script injected — the only dynamic part of an
 * otherwise static asset bundle. Everything else (JS/CSS/WASM/workers) is
 * served directly by the web server from public/vendor/pdf-editor/*, never
 * through PHP.
 *
 * Auth/authorization happen here, before the shell is ever returned — the
 * static assets themselves carry no data and are safe to be publicly
 * cacheable, but this route (and every API route the shell calls) sits
 * behind the same `auth`+`active` middleware and CertificatePolicy as the
 * rest of the certificate admin.
 */
class PdfStudioController extends Controller
{
    public function show(CertificateTemplate $template, ?CertificateBatch $batch = null): Response
    {
        $this->authorize('create', Certificate::class);

        if ($batch !== null && $batch->certificate_template_id !== $template->id) {
            abort(404);
        }

        $assetPath = config('pdf-studio.asset_path');
        $shellPath = public_path("{$assetPath}/studio.html");

        abort_unless(File::exists($shellPath), 500, 'PDF Studio assets are not synced — run scripts/sync-pdf-editor-assets.php.');

        $html = File::get($shellPath);

        // Preload the demo PDF uploaded at template-creation time as a
        // fresh project's starting point — only while no project has been
        // saved yet. Once editor_project_path is set, the adapter's normal
        // fetchProject() load takes over and this stays null forever (the
        // bundle's own PDF bytes are what matters from then on, not the
        // originally-uploaded file). See docs/PDF_STUDIO_INTEGRATION.md.
        $initialPdfUrl = (! $template->hasEditorProject() && $template->hasBackground())
            ? route('admin.pdf-studio.api.templates.source-pdf', $template)
            : null;

        $config = json_encode([
            'apiBase' => url('/admin/api/pdf-studio'),
            'templateId' => $template->id,
            'templateName' => $template->name,
            'csrfToken' => csrf_token(),
            'batchId' => $batch?->id,
            'initialPdfUrl' => $initialPdfUrl,
        ], JSON_UNESCAPED_SLASHES);

        // <base href> must land in <head>, before the <script>/<link> tags
        // Vite already emitted there (relative to Vite's `base: './'`) — by
        // the time the body placeholder is reached, the browser has already
        // resolved (and started fetching) those head tags against the
        // wrong document URL. Inserted right after <head> so it's the
        // first thing the parser sees.
        $baseHref = asset($assetPath).'/';
        $html = str_replace('<head>', '<head><base href="'.e($baseHref).'">', $html);

        $configScript = sprintf('<script id="studio-config" type="application/json">%s</script>', $config);
        $html = str_replace('<!--STUDIO_CONFIG_PLACEHOLDER-->', $configScript, $html);

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * The plain-Blade "upload participant Excel" step — kept out of the
     * React editor (it's ordinary validation/preview UI, not anything PDF
     * Studio's engine does) but still inside the same admin interface and
     * same page flow: Design (studio.show) -> Prepare (this) -> Generate
     * (studio.show-batch), with no download/external-tool step between any
     * of them. See docs/PDF_STUDIO_INTEGRATION.md.
     */
    public function prepare(CertificateTemplate $template): View
    {
        $this->authorize('create', Certificate::class);

        abort_unless($template->editor_schema !== null, 404, 'Save a PDF Studio project for this template first.');

        return view('admin.pdf-studio.prepare', compact('template'));
    }
}
