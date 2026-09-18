<?php

namespace App\Http\Controllers\Admin\QrTool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadQrImportRequest;
use App\Models\QrCategory;
use App\Models\QrCertificate;
use App\Services\Certificates\Import\ExcelFileReader;
use App\Services\QrTool\Import\QrCategoryImportService;
use App\Services\QrTool\Import\QrCategoryImportValidator;
use App\Services\QrTool\Import\QrImportMappingTarget;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Historical Excel import for the simple QR tool, matching the ACTUAL old
 * tool's Excel headings (SL, Conference, Role, Name, Session, Codeword,
 * Created At, QR File — inspected directly from
 * IEEEQRCODEGENERATOR-main/app.py). No CertificateTemplate dependency
 * anywhere in this class. See docs/CERTIFICATE_SYSTEM.md §Simple QR tool.
 *
 * Reuses `ExcelFileReader` from the advanced system's Import namespace --
 * that one class is genuinely generic (headers/rows extraction only, no
 * schema assumptions), unlike everything else in this controller.
 */
class QrImportController extends Controller
{
    public function __construct(
        private readonly ExcelFileReader $reader,
        private readonly QrCategoryImportValidator $validator,
        private readonly QrCategoryImportService $importer,
    ) {}

    public function chooseCategory(): View
    {
        $this->authorize('create', QrCertificate::class);

        $categories = QrCategory::query()->orderBy('name')->get();

        return view('admin.qr-tool.import.choose-category', ['categories' => $categories]);
    }

    public function showUpload(QrCategory $category): View
    {
        $this->authorize('create', QrCertificate::class);
        $this->authorize('view', $category);

        return view('admin.qr-tool.import.upload', ['category' => $category]);
    }

    public function handleUpload(UploadQrImportRequest $request, QrCategory $category): View|RedirectResponse
    {
        $storedFile = (string) Str::uuid();
        $path = "imports/{$storedFile}.xlsx";
        Storage::disk('local')->put($path, file_get_contents($request->file('file')->getRealPath()));

        try {
            $data = $this->reader->read(Storage::disk('local')->path($path));
        } catch (RuntimeException $e) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return view('admin.qr-tool.import.mapping', [
            'category' => $category,
            'headers' => $data['headers'],
            'sampleRow' => $data['rows'][0] ?? [],
            'storedFile' => $storedFile,
            'mapping' => $this->guessMapping($data['headers'], $category),
        ]);
    }

    /**
     * Best-effort starting point matching the old tool's own headings
     * exactly (Conference/Role/Name/Session/Codeword/Created At/SL/QR
     * File) — the admin reviews/overrides every row regardless.
     *
     * @param  list<string>  $headers
     * @return array<int, string>
     */
    private function guessMapping(array $headers, QrCategory $category): array
    {
        $fieldsByLabel = $category->fields->keyBy(fn ($f) => strtolower(trim($f->label)));
        $recipientField = $category->fields->firstWhere('is_recipient_name', true);

        $mapping = [];
        foreach ($headers as $index => $header) {
            $normalized = strtolower(trim($header));

            if (in_array($normalized, ['sl', 'qr file'], true)) {
                continue; // ignored, per the old tool's real columns -- see docs/CERTIFICATE_SYSTEM.md
            }

            if ($normalized === 'name' && $recipientField !== null) {
                $mapping[$index] = $recipientField->key;
            } elseif ($normalized === 'codeword') {
                $mapping[$index] = QrImportMappingTarget::CODEWORD;
            } elseif ($normalized === 'conference') {
                $mapping[$index] = QrImportMappingTarget::EVENT_NAME;
            } elseif ($normalized === 'created at') {
                $mapping[$index] = QrImportMappingTarget::CREATED_AT;
            } elseif ($fieldsByLabel->has($normalized)) {
                $mapping[$index] = $fieldsByLabel->get($normalized)->key;
            }
        }

        return $mapping;
    }

    public function preview(Request $request, QrCategory $category): View
    {
        $this->authorize('create', QrCertificate::class);
        $this->authorize('view', $category);

        [$path, $storedFile] = $this->resolveStoredFile($request);
        $mapping = $this->sanitizeMapping($request->input('mapping', []));
        $data = $this->readStoredFile($path);

        $mappingErrors = $this->validator->validateMapping($category, $mapping);
        if ($mappingErrors !== []) {
            return view('admin.qr-tool.import.mapping', [
                'category' => $category,
                'headers' => $data['headers'],
                'sampleRow' => $data['rows'][0] ?? [],
                'storedFile' => $storedFile,
                'mapping' => $mapping,
                'mappingErrors' => $mappingErrors,
            ]);
        }

        $rows = $this->validator->validateRows($category, $data['rows'], $mapping);
        $validRows = array_filter($rows, fn ($r) => $r->isValid());
        $invalidRows = array_filter($rows, fn ($r) => ! $r->isValid());

        return view('admin.qr-tool.import.preview', [
            'category' => $category,
            'storedFile' => $storedFile,
            'mapping' => $mapping,
            'totalRows' => count($rows),
            'validCount' => count($validRows),
            'invalidCount' => count($invalidRows),
            'sampleErrors' => array_slice($invalidRows, 0, 25),
            'moreErrors' => max(0, count($invalidRows) - 25),
        ]);
    }

    public function errorReport(Request $request, QrCategory $category): Response
    {
        $this->authorize('create', QrCertificate::class);
        $this->authorize('view', $category);

        [$path] = $this->resolveStoredFile($request);
        $mapping = $this->sanitizeMapping($request->input('mapping', []));
        $data = $this->readStoredFile($path);

        $invalidRows = array_filter(
            $this->validator->validateRows($category, $data['rows'], $mapping),
            fn ($r) => ! $r->isValid()
        );

        $lines = ['Row,Errors'];
        foreach ($invalidRows as $row) {
            $lines[] = $row->rowNumber.',"'.str_replace('"', '""', implode(' ', $row->errors)).'"';
        }

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="qr-import-errors.csv"',
        ]);
    }

    public function confirm(Request $request, QrCategory $category): View
    {
        $this->authorize('create', QrCertificate::class);
        $this->authorize('view', $category);

        [$path, $storedFile] = $this->resolveStoredFile($request);
        $mapping = $this->sanitizeMapping($request->input('mapping', []));
        $data = $this->readStoredFile($path);

        $mappingErrors = $this->validator->validateMapping($category, $mapping);
        if ($mappingErrors !== []) {
            throw ValidationException::withMessages(['mapping' => $mappingErrors]);
        }

        $rows = $this->validator->validateRows($category, $data['rows'], $mapping);
        $validRows = array_values(array_filter($rows, fn ($r) => $r->isValid()));
        $invalidCount = count($rows) - count($validRows);

        $summary = $this->importer->import($category, $validRows, $request->user());

        Storage::disk('local')->delete($path);

        return view('admin.qr-tool.import.result', [
            'category' => $category,
            'summary' => $summary,
            'skippedTotal' => $invalidCount + $summary->skipped,
        ]);
    }

    /** @return array{0: string, 1: string} */
    private function resolveStoredFile(Request $request): array
    {
        $storedFile = (string) $request->input('stored_file');
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $storedFile)) {
            throw new NotFoundHttpException;
        }

        $path = "imports/{$storedFile}.xlsx";
        if (! Storage::disk('local')->exists($path)) {
            throw new NotFoundHttpException;
        }

        return [$path, $storedFile];
    }

    /** @return array{headers: list<string>, rows: list<list<string|null>>} */
    private function readStoredFile(string $path): array
    {
        try {
            return $this->reader->read(Storage::disk('local')->path($path));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<int|string, mixed>  $raw
     * @return array<int, string>
     */
    private function sanitizeMapping(array $raw): array
    {
        $mapping = [];
        foreach ($raw as $columnIndex => $target) {
            $target = (string) $target;
            if ($target === '' || $target === QrImportMappingTarget::IGNORE) {
                continue;
            }
            $mapping[(int) $columnIndex] = $target;
        }

        return $mapping;
    }
}
