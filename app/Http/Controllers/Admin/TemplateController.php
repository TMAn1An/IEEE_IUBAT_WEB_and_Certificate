<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CertificateTemplateStatus;
use App\Enums\TemplateFieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCertificateTemplateRequest;
use App\Http\Requests\Admin\UpdateCertificateTemplateRequest;
use App\Http\Requests\Admin\UploadTemplateBackgroundRequest;
use App\Models\CertificateTemplate;
use App\Services\Templates\TemplateBackgroundService;
use App\Services\Templates\TemplateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TemplateController extends Controller
{
    public function __construct(
        private readonly TemplateService $templates,
        private readonly TemplateBackgroundService $backgrounds,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', CertificateTemplate::class);

        return view('admin.templates.index', [
            'templates' => CertificateTemplate::withCount('fields')->orderByDesc('created_at')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', CertificateTemplate::class);

        return view('admin.templates.create');
    }

    public function store(StoreCertificateTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = ($data['slug'] ?? null) ?: $this->templates->generateUniqueSlug($data['name']);
        $data['status'] = CertificateTemplateStatus::Draft;
        $data['created_by'] = $request->user()->id;

        $template = CertificateTemplate::create($data);

        return redirect()
            ->route('admin.templates.edit', $template)
            ->with('status', 'Template created. Add fields below before activating it.');
    }

    public function edit(CertificateTemplate $template): View
    {
        $this->authorize('update', $template);

        return view('admin.templates.edit', [
            'template' => $template,
            'fields' => $template->fields, // already ordered by sort_order (see CertificateTemplate::fields())
            'fieldTypes' => TemplateFieldType::assignable(),
            'activationErrors' => collect($this->templates->activationErrors($template)),
            'canManageLayout' => auth()->user()->can('manageLayout', $template),
        ]);
    }

    public function update(UpdateCertificateTemplateRequest $request, CertificateTemplate $template): RedirectResponse
    {
        $template->update($request->validated());

        return redirect()->route('admin.templates.edit', $template)->with('status', 'Template details updated.');
    }

    public function activate(CertificateTemplate $template): RedirectResponse
    {
        $this->authorize('update', $template);

        try {
            $this->templates->activate($template);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Template activated.');
    }

    public function archive(CertificateTemplate $template): RedirectResponse
    {
        $this->authorize('update', $template);

        $this->templates->archive($template);

        return back()->with('status', 'Template archived. It will not be available for new certificate generation.');
    }

    public function uploadBackground(UploadTemplateBackgroundRequest $request, CertificateTemplate $template): RedirectResponse
    {
        $this->backgrounds->upload($template, $request->file('background'));

        return redirect()
            ->route('admin.templates.edit', $template)
            ->with('status', 'Certificate background uploaded. Open the designer to place fields on it.');
    }

    /**
     * Streams the stored PDF back to the browser so the designer's PDF.js
     * viewer can fetch it. Private-disk file, so this authorized route is
     * the only way to reach it — never a public storage URL (see
     * docs/SECURITY.md). Same-origin fetch from the designer page sends the
     * admin's session cookie automatically, so no separate token is needed.
     */
    public function showBackground(CertificateTemplate $template): StreamedResponse
    {
        $this->authorize('update', $template);

        abort_unless($template->hasBackground(), 404);

        return Storage::disk('local')->response(
            $template->source_pdf_path,
            $template->original_filename ?: 'certificate-background.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }
}
