<?php

namespace App\Http\Controllers\Admin\QrTool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateQrRequest;
use App\Models\QrCategory;
use App\Models\QrCertificate;
use App\Services\Certificates\QrCodeService;
use App\Services\QrTool\QrCertificateIssuanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * The simple QR tool's primary workflow: pick a category, fill its dynamic
 * form, save + codeword + QR -- no PDF, no CertificateTemplate anywhere in
 * this class. See docs/CERTIFICATE_SYSTEM.md §Simple QR tool.
 */
class QrGenerateController extends Controller
{
    public function __construct(private readonly QrCertificateIssuanceService $issuance) {}

    public function chooseCategory(): View
    {
        $this->authorize('create', QrCertificate::class);

        $categories = QrCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.qr-tool.generate.choose-category', ['categories' => $categories]);
    }

    public function create(QrCategory $category): View
    {
        $this->authorize('create', QrCertificate::class);
        $this->authorize('view', $category);
        $this->ensureCategoryIsActive($category);

        return view('admin.qr-tool.generate.create', [
            'category' => $category,
            'fields' => $category->fields,
        ]);
    }

    public function store(GenerateQrRequest $request, QrCategory $category): RedirectResponse
    {
        $this->ensureCategoryIsActive($category);

        $certificate = $this->issuance->issue($category, $request->validated()['fields'] ?? [], $request->user());

        return redirect()
            ->route('admin.qr.records.show', $certificate)
            ->with('status', 'QR generated for '.$certificate->recipient_name.'.');
    }

    public function qrImage(QrCertificate $certificate, QrCodeService $qr): Response
    {
        $this->authorize('view', $certificate);

        $url = $qr->verificationUrlForCodeword($certificate->codeword);
        $png = $qr->pngBytes($url);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="'.$certificate->codeword.'-qr.png"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function ensureCategoryIsActive(QrCategory $category): void
    {
        if (! $category->is_active) {
            throw ValidationException::withMessages([
                'category' => 'Only active QR categories can be used to generate a QR.',
            ]);
        }
    }
}
