<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeletableRecordType;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\DeletionRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The certificate RECORDS browser — search/view/download/audit every
 * certificate issued through PDF Certificates (see
 * docs/PDF_STUDIO_INTEGRATION.md). The old manual single-certificate
 * issuance form (choose template -> fill fields -> issue) was removed in
 * the admin workflow cleanup: PDF Certificates' batch flow is the only way
 * to issue a certificate now, even for a single recipient. No 'edit' route
 * — issued certificates are immutable (§Snapshot strategy); revoke/reissue
 * are a later phase.
 */
class CertificateController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Certificate::class);

        $search = trim((string) $request->query('search', ''));

        $certificates = Certificate::query()
            ->with('template')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('certificate_number', 'like', "%{$search}%")
                        ->orWhere('recipient_name', 'like', "%{$search}%")
                        ->orWhere('codeword', 'like', "%{$search}%");
                });
            })
            ->latest('issued_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.certificates.index', [
            'certificates' => $certificates,
            'search' => $search,
        ]);
    }

    /** See Admin\QrTool\QrRecordsController::deleted() — identical reasoning. */
    public function deleted(): View
    {
        $this->authorize('viewDeleted', Certificate::class);

        $certificates = Certificate::onlyTrashed()
            ->with('template')
            ->latest('deleted_at')
            ->paginate(20);

        return view('admin.certificates.deleted', ['certificates' => $certificates]);
    }

    public function show(Certificate $certificate): View
    {
        $this->authorize('view', $certificate);

        return view('admin.certificates.show', [
            'certificate' => $certificate->load('template', 'creator'),
            'deletionRequest' => DeletionRequest::latestFor(DeletableRecordType::Certificate, $certificate->id),
        ]);
    }

    public function download(Certificate $certificate): StreamedResponse
    {
        $this->authorize('download', $certificate);

        if ($certificate->pdf_path === null || ! Storage::disk('local')->exists($certificate->pdf_path)) {
            throw new NotFoundHttpException;
        }

        return Storage::disk('local')->response(
            $certificate->pdf_path,
            "{$certificate->certificate_number}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }
}
