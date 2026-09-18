<?php

namespace App\Http\Controllers\Admin\QrTool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadQrImportRequest;
use App\Models\QrCategory;
use App\Models\QrCertificate;
use App\Models\QrGroup;
use App\Services\Certificates\Import\ExcelFileReader;
use App\Services\QrTool\Import\QrCategoryImportService;
use App\Services\QrTool\Import\QrCategoryImportValidator;
use App\Services\QrTool\Import\QrImportMappingTarget;
use App\Services\QrTool\QrCategoryService;
use App\Services\QrTool\QrGroupService;
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
 * Every import happens INTO a destination QrGroup, chosen up front (this
 * class is the ONE importer implementation — a per-group "Import Excel"
 * button skips straight to showUpload(), and the general "Import Excel"
 * nav entry starts at chooseGroup() and lands on the exact same upload/
 * mapping/preview/confirm flow). See docs/CERTIFICATE_SYSTEM.md §Simple QR
 * tool: group-based import.
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
        private readonly QrCategoryService $categories,
        private readonly QrGroupService $groups,
    ) {}

    public function chooseGroup(): View
    {
        $this->authorize('create', QrCertificate::class);

        $groups = QrGroup::query()
            ->withCount('certificates')
            ->orderBy('event_type')
            ->orderBy('event_name')
            ->orderBy('role')
            ->get();

        return view('admin.qr-tool.import.choose-group', ['groups' => $groups]);
    }

    /**
     * Optional convenience (never automatic/guessed): explicitly create or
     * reuse a group from typed-in Event Type/Event Name/Role, then jump
     * straight into the upload step for it.
     */
    public function createGroupAndRedirect(Request $request): RedirectResponse
    {
        $this->authorize('create', QrCertificate::class);

        $data = $request->validate([
            'event_type' => ['nullable', 'string', 'max:255'],
            'event_name' => ['nullable', 'string', 'max:255'],
            'role' => ['required', 'string', 'max:255'],
        ]);

        $group = $this->groups->resolve($data['event_type'] ?? null, $data['event_name'] ?? null, $data['role']);

        return redirect()->route('admin.qr.import.upload', $group);
    }

    public function showUpload(QrGroup $group): View
    {
        $this->authorize('create', QrCertificate::class);

        return view('admin.qr-tool.import.upload', ['group' => $group]);
    }

    public function handleUpload(UploadQrImportRequest $request, QrGroup $group): View|RedirectResponse
    {
        $category = $this->requirePrimaryCategory();

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
            'group' => $group,
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
        $fieldsByLabel = $category->fields->where('key', '!=', 'role')->keyBy(fn ($f) => strtolower(trim($f->label)));
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
                $mapping[$index] = QrImportMappingTarget::CONFERENCE_VALIDATE;
            } elseif ($normalized === 'role') {
                $mapping[$index] = QrImportMappingTarget::ROLE_VALIDATE;
            } elseif ($normalized === 'created at') {
                $mapping[$index] = QrImportMappingTarget::CREATED_AT;
            } elseif ($fieldsByLabel->has($normalized)) {
                $mapping[$index] = $fieldsByLabel->get($normalized)->key;
            }
        }

        return $mapping;
    }

    public function preview(Request $request, QrGroup $group): View
    {
        $this->authorize('create', QrCertificate::class);
        $category = $this->requirePrimaryCategory();

        [$path, $storedFile] = $this->resolveStoredFile($request);
        $mapping = $this->sanitizeMapping($request->input('mapping', []));
        $data = $this->readStoredFile($path);

        $mappingErrors = $this->validator->validateMapping($category, $mapping);
        if ($mappingErrors !== []) {
            return view('admin.qr-tool.import.mapping', [
                'group' => $group,
                'category' => $category,
                'headers' => $data['headers'],
                'sampleRow' => $data['rows'][0] ?? [],
                'storedFile' => $storedFile,
                'mapping' => $mapping,
                'mappingErrors' => $mappingErrors,
            ]);
        }

        $rows = $this->validator->validateRows($category, $group, $data['rows'], $mapping);
        $validRows = array_filter($rows, fn ($r) => $r->isValid());
        $duplicateRows = array_filter($rows, fn ($r) => in_array(QrCategoryImportValidator::DUPLICATE_ERROR, $r->errors, true));
        $otherInvalidRows = array_filter($rows, fn ($r) => ! $r->isValid() && ! in_array(QrCategoryImportValidator::DUPLICATE_ERROR, $r->errors, true));

        return view('admin.qr-tool.import.preview', [
            'group' => $group,
            'category' => $category,
            'storedFile' => $storedFile,
            'mapping' => $mapping,
            'totalRows' => count($rows),
            'validCount' => count($validRows),
            'duplicateCount' => count($duplicateRows),
            'invalidCount' => count($otherInvalidRows),
            'sampleDuplicates' => array_slice($duplicateRows, 0, 25),
            'moreDuplicates' => max(0, count($duplicateRows) - 25),
            'sampleErrors' => array_slice($otherInvalidRows, 0, 25),
            'moreErrors' => max(0, count($otherInvalidRows) - 25),
        ]);
    }

    public function errorReport(Request $request, QrGroup $group): Response
    {
        $this->authorize('create', QrCertificate::class);
        $category = $this->requirePrimaryCategory();

        [$path] = $this->resolveStoredFile($request);
        $mapping = $this->sanitizeMapping($request->input('mapping', []));
        $data = $this->readStoredFile($path);

        $invalidRows = array_filter(
            $this->validator->validateRows($category, $group, $data['rows'], $mapping),
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

    public function confirm(Request $request, QrGroup $group): View
    {
        $this->authorize('create', QrCertificate::class);
        $category = $this->requirePrimaryCategory();

        [$path, $storedFile] = $this->resolveStoredFile($request);
        $mapping = $this->sanitizeMapping($request->input('mapping', []));
        $data = $this->readStoredFile($path);

        $mappingErrors = $this->validator->validateMapping($category, $mapping);
        if ($mappingErrors !== []) {
            throw ValidationException::withMessages(['mapping' => $mappingErrors]);
        }

        $rows = $this->validator->validateRows($category, $group, $data['rows'], $mapping);
        $validRows = array_values(array_filter($rows, fn ($r) => $r->isValid()));
        $invalidCount = count($rows) - count($validRows);

        $summary = $this->importer->import($category, $group, $validRows, $request->user());

        Storage::disk('local')->delete($path);

        return view('admin.qr-tool.import.result', [
            'group' => $group,
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

    private function requirePrimaryCategory(): QrCategory
    {
        $category = $this->categories->primary();
        abort_if($category === null, 500, 'The QR tool\'s primary category is not configured. Run php artisan db:seed --class=QrCategorySeeder.');

        return $category;
    }
}
