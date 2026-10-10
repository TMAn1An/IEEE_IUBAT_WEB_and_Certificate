<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FinalizeReservationRequest;
use App\Http\Requests\Admin\PrepareBatchRequest;
use App\Http\Requests\Admin\SaveStudioProjectRequest;
use App\Models\Certificate;
use App\Models\CertificateBatch;
use App\Models\CertificateBatchReservation;
use App\Models\CertificateTemplate;
use App\Services\Certificates\Export\ExcelFormulaGuard;
use App\Services\Certificates\PdfStudio\BatchZipService;
use App\Services\Certificates\PdfStudio\DirectBatchService;
use App\Services\Certificates\PdfStudio\ReservationFinalizeService;
use App\Services\Certificates\PdfStudio\TemplateProjectService;
use App\Services\Certificates\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * JSON API consumed by the embedded PDF Studio (the pdfeditor adapter under
 * src/integration/ in that repo) — authenticated via the same session/CSRF
 * as the rest of the admin, authorized via CertificatePolicy. See
 * docs/PDF_STUDIO_INTEGRATION.md for the full contract.
 */
class PdfStudioApiController extends Controller
{
    public function __construct(
        private readonly TemplateProjectService $projectService,
        private readonly DirectBatchService $batchService,
        private readonly ReservationFinalizeService $finalizeService,
        private readonly BatchZipService $zipService,
        private readonly QrCodeService $qrCodeService,
    ) {}

    public function getProject(CertificateTemplate $template): StreamedResponse
    {
        $this->authorize('create', Certificate::class);

        abort_unless($template->hasEditorProject(), 404);

        return Storage::disk('local')->response($template->editor_project_path, 'project.pdftemplate', [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * The batch-scoped counterpart to getProject(): serves the project
     * bundle PINNED to this batch at confirm time
     * (CertificateBatch::$editor_project_path), not the template's current
     * one — this is what the Studio adapter fetches in Generate mode so a
     * template re-save after a batch was created never changes what that
     * batch's remaining rows render. Falls back to the template's current
     * project only for legacy batches created before this column existed
     * (editor_project_path null). See docs/PDF_STUDIO_INTEGRATION.md's
     * "Batch template immutability".
     */
    public function getBatchProject(CertificateBatch $batch): StreamedResponse
    {
        $this->authorize('create', Certificate::class);

        $path = $batch->editor_project_path ?? $batch->template->editor_project_path;

        abort_unless($path !== null && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, 'project.pdftemplate', [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * The "demo certificate" PDF uploaded at template-creation time
     * (PdfCertificatesController::store(), via TemplateBackgroundService) —
     * served here only so the Studio adapter can preload it as a brand-new
     * project's starting PDF (StudioApp.tsx's initial-PDF auto-load
     * effect). Once a project has been saved, PdfStudioController::show()
     * stops advertising this URL at all — see that method's docblock.
     */
    public function sourcePdf(CertificateTemplate $template): StreamedResponse
    {
        $this->authorize('create', Certificate::class);

        abort_unless($template->hasBackground(), 404);

        return Storage::disk('local')->response(
            $template->source_pdf_path,
            $template->original_filename ?: 'certificate-template.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    public function saveProject(SaveStudioProjectRequest $request, CertificateTemplate $template): JsonResponse
    {
        $template = $this->projectService->save(
            $template,
            $request->file('project'),
            $request->string('qr_field_id')->toString(),
            $request->filled('recipient_field_id') ? $request->string('recipient_field_id')->toString() : null,
        );

        return response()->json(['schema' => $template->editor_schema, 'schema_version' => $template->editor_schema_version]);
    }

    public function schema(CertificateTemplate $template): JsonResponse
    {
        $this->authorize('create', Certificate::class);

        abort_unless($template->editor_schema !== null, 404);

        return response()->json($template->editor_schema);
    }

    public function sampleXlsx(CertificateTemplate $template): BinaryFileResponse
    {
        $this->authorize('create', Certificate::class);

        abort_unless($template->editor_schema !== null, 404);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $headers = array_map(fn ($f) => $f['column'], $template->editor_schema['fields']);
        $sheet->fromArray($headers, null, 'A1');

        $sample = array_map(fn ($f) => ExcelFormulaGuard::sanitize($f['type'] === 'image' ? 'photo1.jpg' : 'Sample value'), $template->editor_schema['fields']);
        $sheet->fromArray($sample, null, 'A2');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $tmp = tempnam(sys_get_temp_dir(), 'sample').'.xlsx';
        $writer->save($tmp);

        return response()->download($tmp, 'sample-participants.xlsx')->deleteFileAfterSend();
    }

    public function prepareBatch(PrepareBatchRequest $request, CertificateTemplate $template): JsonResponse
    {
        $photos = [];
        foreach ($request->file('photos', []) as $photo) {
            $photos[$photo->getClientOriginalName()] = $photo;
        }

        try {
            $result = $this->batchService->prepare($template, $request->file('participants'), $photos);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function confirmBatch(Request $request, CertificateTemplate $template): JsonResponse
    {
        $this->authorize('create', Certificate::class);

        $validated = $request->validate([
            'token' => ['required', 'uuid'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        try {
            $batch = $this->batchService->confirm($template, $validated['token'], $validated['idempotency_key'], $request->user());
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['batch_id' => $batch->id]);
    }

    public function manifest(CertificateBatch $batch): JsonResponse
    {
        $this->authorize('create', Certificate::class);

        $batch->load('reservations');

        $schemaFields = collect($batch->template->editor_schema['fields'] ?? []);
        $columns = $schemaFields->pluck('column')->all();

        return response()->json([
            'batch' => [
                'id' => $batch->id,
                'status' => $batch->status->value,
                'total_rows' => $batch->total_rows,
                'successful_rows' => $batch->successful_rows,
                'failed_rows' => $batch->failed_rows,
            ],
            'columns' => $columns,
            // `reservations[].data` is keyed by editor FIELD ID (the stable
            // identifier, consistent with how the rest of the certificate
            // system keys `data`) — but the saved project's own field
            // mapping (`FieldSource.column`) looks values up by COLUMN
            // NAME. This id->column table is what the Studio adapter uses
            // to re-key `data` into a SheetRow the unmodified render engine
            // can actually resolve. See docs/PDF_STUDIO_INTEGRATION.md.
            'fields' => $schemaFields->map(fn ($f) => ['id' => $f['id'], 'column' => $f['column']])->values(),
            'reservations' => $batch->reservations->map(fn (CertificateBatchReservation $r) => [
                'id' => $r->id,
                'row_index' => $r->row_index,
                'status' => $r->status->value,
                'certificate_number' => $r->certificate_number,
                'codeword' => $r->codeword,
                'recipient_name' => $r->recipient_name,
                'data' => $r->data,
                'qr_url' => route('admin.pdf-studio.reservations.qr', $r),
                'photo_url' => $r->photo_path ? route('admin.pdf-studio.reservations.photo', $r) : null,
                'error_message' => $r->error_message,
            ])->values(),
        ]);
    }

    public function qrImage(CertificateBatchReservation $reservation): Response
    {
        $this->authorize('create', Certificate::class);

        $url = $this->qrCodeService->verificationUrlForCodeword($reservation->codeword);
        $png = $this->qrCodeService->pngBytes($url);

        return response($png, 200, ['Content-Type' => 'image/png']);
    }

    public function photo(CertificateBatchReservation $reservation): StreamedResponse
    {
        $this->authorize('create', Certificate::class);

        abort_unless($reservation->photo_path !== null && Storage::disk('local')->exists($reservation->photo_path), 404);

        return Storage::disk('local')->response($reservation->photo_path);
    }

    public function finalizeReservation(FinalizeReservationRequest $request, CertificateBatchReservation $reservation): JsonResponse
    {
        $bytes = file_get_contents($request->file('pdf')->getRealPath());

        try {
            $result = $this->finalizeService->finalize($reservation, $bytes, $request->user());
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $status = $result['status'] === 'conflict' ? 409 : 200;

        return response()->json($result, $status);
    }

    public function status(CertificateBatch $batch): JsonResponse
    {
        $this->authorize('create', Certificate::class);

        return response()->json([
            'id' => $batch->id,
            'status' => $batch->status->value,
            'total_rows' => $batch->total_rows,
            'successful_rows' => $batch->successful_rows,
            'failed_rows' => $batch->failed_rows,
        ]);
    }

    public function downloadZip(CertificateBatch $batch): BinaryFileResponse
    {
        $this->authorize('create', Certificate::class);

        $result = $this->zipService->build($batch);

        return response()->download($result['path'], "batch-{$batch->id}.zip", [
            'X-Batch-Incomplete' => $result['incomplete'] ? '1' : '0',
        ])->deleteFileAfterSend();
    }
}
