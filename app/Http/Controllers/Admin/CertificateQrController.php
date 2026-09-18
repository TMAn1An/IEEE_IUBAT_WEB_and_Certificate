<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CertificateTemplateStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IssueCertificateRequest;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Services\Certificates\QrCodeService;
use App\Services\Certificates\SimpleCertificateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Phase 6's primary certificate workflow: pick a template, fill its dynamic
 * form, save the data + a codeword + a QR to the database -- no PDF. See
 * docs/CERTIFICATE_SYSTEM.md §Simplified QR workflow.
 */
class CertificateQrController extends Controller
{
    public function __construct(private readonly SimpleCertificateService $issuance) {}

    public function chooseTemplate(): View
    {
        $this->authorize('create', Certificate::class);

        $templates = CertificateTemplate::query()
            ->where('status', CertificateTemplateStatus::Active)
            ->orderBy('name')
            ->get();

        return view('admin.certificates.qr.choose-template', ['templates' => $templates]);
    }

    public function create(CertificateTemplate $template): View
    {
        $this->authorize('create', Certificate::class);
        $this->authorize('view', $template);
        $this->ensureTemplateIsActive($template);

        return view('admin.certificates.qr.create', [
            'template' => $template,
            'fields' => $template->fields,
        ]);
    }

    public function store(IssueCertificateRequest $request, CertificateTemplate $template): RedirectResponse
    {
        $this->ensureTemplateIsActive($template);

        $certificate = $this->issuance->issue($template, $request->validated()['fields'] ?? [], $request->user());

        return redirect()
            ->route('admin.certificates.show', $certificate)
            ->with('status', 'Certificate '.$certificate->certificate_number.' created. QR ready below.');
    }

    public function qrImage(Certificate $certificate, QrCodeService $qr): Response
    {
        $this->authorize('view', $certificate);

        $png = $qr->pngBytes($qr->verificationUrlFor($certificate));

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="'.$certificate->certificate_number.'-qr.png"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function ensureTemplateIsActive(CertificateTemplate $template): void
    {
        if ($template->status !== CertificateTemplateStatus::Active) {
            throw ValidationException::withMessages([
                'template' => 'Only active templates can be used to issue certificates.',
            ]);
        }
    }
}
