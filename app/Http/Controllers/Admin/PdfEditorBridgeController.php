<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CertificateTemplateStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FinalizePdfEditorBatchRequest;
use App\Http\Requests\Admin\ReservePdfEditorBatchRequest;
use App\Models\Certificate;
use App\Models\CertificateBatch;
use App\Models\CertificateTemplate;
use App\Services\Certificates\PdfEditorBridge\PdfEditorBridgeFinalizeService;
use App\Services\Certificates\PdfEditorBridge\PdfEditorBridgeReservationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Admin UI for the PDF Editor Bridge — see docs/CERTIFICATE_SYSTEM.md
 * §PDF Editor Bridge for the full design. This is the integration layer
 * between this app's advanced certificate system and the separate,
 * unmodified, client-side "PDF Template Studio" editor
 * (https://github.com/TMAn1An/pdfeditor): Laravel mints certificates and
 * QR codes and hands them to the admin as a spreadsheet + QR images; the
 * admin designs/exports the real PDFs in the separate editor, unchanged;
 * Laravel then ingests the finished PDFs by filename==codeword.
 *
 * Reuses `CertificatePolicy::create` for authorization — preparing or
 * finalizing a bridge batch is, semantically, "creating certificates" via
 * a different route to the same `certificates` table, and that policy
 * already draws exactly the two-admin-role boundary this needs.
 */
class PdfEditorBridgeController extends Controller
{
    public function __construct(
        private readonly PdfEditorBridgeReservationService $reservationService,
        private readonly PdfEditorBridgeFinalizeService $finalizeService,
    ) {}

    /** The former "Bulk Generation" nav entry — now the real reserve-a-batch form. */
    public function create(): View
    {
        $this->authorize('create', Certificate::class);

        $templates = CertificateTemplate::query()
            ->where('status', CertificateTemplateStatus::Active)
            ->orderBy('name')
            ->get();

        return view('admin.pdf-editor.create', compact('templates'));
    }

    public function store(ReservePdfEditorBatchRequest $request): RedirectResponse
    {
        $template = CertificateTemplate::findOrFail($request->integer('certificate_template_id'));

        try {
            $result = $this->reservationService->reserve(
                $template,
                $request->file('recipients')->getRealPath(),
                $request->user(),
            );
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['recipients' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.batches.show', $result['batch'])
            ->with('bridge-errors', $result['errors'])
            ->with('status', count($result['errors']) === 0
                ? 'All rows reserved. Download the spreadsheet and QR codes below to use in the PDF editor.'
                : sprintf('%d row(s) reserved, %d row(s) failed — see details below.', $result['batch']->successful_rows, $result['batch']->failed_rows));
    }

    /** The former "Batches" nav entry — now a real list of bulk-generation batches. */
    public function index(): View
    {
        $this->authorize('viewAny', Certificate::class);

        $batches = CertificateBatch::query()
            ->with('template')
            ->latest()
            ->paginate(20);

        return view('admin.pdf-editor.index', compact('batches'));
    }

    public function show(CertificateBatch $batch): View
    {
        $this->authorize('viewAny', Certificate::class);

        $batch->load(['template', 'reservations']);

        return view('admin.pdf-editor.show', compact('batch'));
    }

    public function downloadReservationExcel(CertificateBatch $batch): Response
    {
        $this->authorize('viewAny', Certificate::class);

        $path = "certificate-batches/{$batch->id}/reservation.xlsx";
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, "batch-{$batch->id}-recipients.xlsx");
    }

    public function downloadQrZip(CertificateBatch $batch): Response
    {
        $this->authorize('viewAny', Certificate::class);

        $path = "certificate-batches/{$batch->id}/qr-codes.zip";
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, "batch-{$batch->id}-qr-codes.zip");
    }

    public function finalize(FinalizePdfEditorBatchRequest $request, CertificateBatch $batch): RedirectResponse
    {
        $file = $request->file('package');

        $result = $this->finalizeService->finalize(
            $batch,
            $file->getRealPath(),
            $file->getClientOriginalName(),
            $request->user(),
        );

        return redirect()
            ->route('admin.batches.show', $batch)
            ->with('bridge-errors', $result['errors'])
            ->with('status', sprintf('%d certificate(s) finalized, %d failed.', $result['finalized'], $result['failed']));
    }
}
