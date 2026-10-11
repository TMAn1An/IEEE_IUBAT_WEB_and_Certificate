<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CertificateTemplateStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCertificateTemplateRequest;
use App\Http\Requests\Admin\UpdateCertificateTemplateRequest;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Services\Templates\TemplateBackgroundService;
use App\Services\Templates\TemplateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * The single, unified entry point for the PDF Certificates workflow — see
 * docs/PDF_STUDIO_INTEGRATION.md's "PDF Certificates entry flow". Replaces
 * the old split across TemplateController (name/slug CRUD), the manual
 * background/designer screens, and a template-list "PDF Studio" button the
 * admin had to go hunting for. A template is immediately editable in the
 * real PDF Studio editor as soon as it's created — no draft/active
 * activation gate, no separate designer page.
 */
class PdfCertificatesController extends Controller
{
    public function __construct(
        private readonly TemplateService $templates,
        private readonly TemplateBackgroundService $backgrounds,
    ) {}

    /**
     * "New template or Saved templates" — the landing page. Archived
     * templates are hidden here (still fully usable via a direct batch
     * link/resume; archiving only declutters this list).
     */
    public function index(): View
    {
        $this->authorize('viewAny', CertificateTemplate::class);

        return view('admin.pdf-certificates.index', [
            'templates' => CertificateTemplate::query()
                ->where('status', '!=', CertificateTemplateStatus::Archived)
                ->withCount('batches')
                ->orderByDesc('updated_at')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', CertificateTemplate::class);

        return view('admin.pdf-certificates.create');
    }

    /**
     * Name + demo certificate PDF in one step. Creates the template, stores
     * the PDF, then sends the admin straight into the real editor
     * (pdf-studio.show) with that PDF preloaded as a fresh project — see
     * PdfStudioController::show()'s `initialPdfUrl` config.
     */
    public function store(StoreCertificateTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $slug = ($data['slug'] ?? null) ?: $this->templates->generateUniqueSlug($data['name']);

        $template = CertificateTemplate::create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'status' => CertificateTemplateStatus::Draft,
            'created_by' => $request->user()->id,
        ]);

        $this->backgrounds->upload($template, $request->file('demo_pdf'));

        return redirect()
            ->route('admin.pdf-studio.show', $template)
            ->with('status', 'Template created. Draw the QR field and any text/image fields, then save.');
    }

    /** Rename/describe a template, or archive it — no field/background management here anymore. */
    public function edit(CertificateTemplate $template): View
    {
        $this->authorize('update', $template);

        return view('admin.pdf-certificates.edit', ['template' => $template]);
    }

    public function update(UpdateCertificateTemplateRequest $request, CertificateTemplate $template): RedirectResponse
    {
        $template->update($request->validated());

        return redirect()->route('admin.pdf-certificates.edit', $template)->with('status', 'Template details updated.');
    }

    public function archive(CertificateTemplate $template): RedirectResponse
    {
        $this->authorize('update', $template);

        $this->templates->archive($template);

        return redirect()->route('admin.pdf-certificates.index')->with('status', 'Template archived and hidden from this list. Its batches and certificates are unaffected.');
    }

    /**
     * Batch history for one template: start a batch via the "Upload
     * participants" step, resume an in-progress one, or re-download a
     * completed one's ZIP — see docs/PDF_STUDIO_INTEGRATION.md's "Batch
     * history" section. Everything here reuses the existing PDF Studio
     * batch endpoints; this view is the missing "how do I get back to a
     * batch" list.
     */
    public function batches(CertificateTemplate $template): View
    {
        $this->authorize('create', Certificate::class);

        return view('admin.pdf-certificates.batches', [
            'template' => $template,
            'batches' => $template->batches()
                ->where('source', 'pdf_studio')
                ->latest('created_at')
                ->get(),
        ]);
    }
}
