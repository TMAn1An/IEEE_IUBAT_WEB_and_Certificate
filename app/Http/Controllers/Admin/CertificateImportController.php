<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadCertificateImportRequest;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Services\Certificates\Import\CertificateImportService;
use App\Services\Certificates\Import\CertificateImportValidator;
use App\Services\Certificates\Import\ExcelFileReader;
use App\Services\Certificates\Import\ImportMappingTarget;
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
 * Historical-Excel import with column mapping. See
 * docs/CERTIFICATE_SYSTEM.md §Excel import for the full step-by-step and
 * the reasoning behind carrying state (the stored file's UUID + the chosen
 * mapping) through hidden form fields across the upload -> mapping ->
 * preview -> confirm steps, rather than a persistent import-batch table.
 */
class CertificateImportController extends Controller
{
    public function __construct(
        private readonly ExcelFileReader $reader,
        private readonly CertificateImportValidator $validator,
        private readonly CertificateImportService $importer,
    ) {}

    public function chooseTemplate(): View
    {
        $this->authorize('create', Certificate::class);

        // Unlike the live QR-generation flow, import isn't restricted to
        // Active templates -- historical Excel data frequently belongs to
        // an event/category that has since been archived, and importing
        // its records is exactly the kind of "not live issuance" use case
        // status shouldn't block. See docs/CERTIFICATE_SYSTEM.md §Excel import.
        $templates = CertificateTemplate::query()->orderBy('name')->get();

        return view('admin.certificates.import.choose-template', ['templates' => $templates]);
    }

    public function showUpload(CertificateTemplate $template): View
    {
        $this->authorize('create', Certificate::class);
        $this->authorize('view', $template);

        return view('admin.certificates.import.upload', ['template' => $template]);
    }

    public function handleUpload(UploadCertificateImportRequest $request, CertificateTemplate $template): View|RedirectResponse
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

        return view('admin.certificates.import.mapping', [
            'template' => $template,
            'headers' => $data['headers'],
            'sampleRow' => $data['rows'][0] ?? [],
            'storedFile' => $storedFile,
            'mapping' => $this->guessMapping($data['headers'], $template),
        ]);
    }

    /**
     * A best-effort starting point only -- the admin reviews/overrides every
     * dropdown on the mapping screen regardless. Matches on obvious
     * substrings ("name", "codeword", "certificate number") and on a
     * header matching a field's label case-insensitively; anything else is
     * left unmapped rather than guessed wrong. See
     * docs/CERTIFICATE_SYSTEM.md §Excel column mapping.
     *
     * @param  list<string>  $headers
     * @return array<int, string>
     */
    private function guessMapping(array $headers, CertificateTemplate $template): array
    {
        $fieldsByLabel = $template->fields
            ->filter(fn ($f) => $f->field_type->isAssignable())
            ->keyBy(fn ($f) => strtolower(trim($f->label)));

        $recipientField = $template->fields->firstWhere('is_recipient_name', true);

        $mapping = [];
        $recipientAssigned = false;

        foreach ($headers as $index => $header) {
            $normalized = strtolower(trim($header));

            if ($recipientField !== null && ! $recipientAssigned && str_contains($normalized, 'name') && ! str_contains($normalized, 'institution')) {
                $mapping[$index] = $recipientField->field_key;
                $recipientAssigned = true;
            } elseif (str_contains($normalized, 'codeword')) {
                $mapping[$index] = ImportMappingTarget::CODEWORD;
            } elseif (str_contains($normalized, 'certificate') && str_contains($normalized, 'number')) {
                $mapping[$index] = ImportMappingTarget::CERTIFICATE_NUMBER;
            } elseif ($fieldsByLabel->has($normalized)) {
                $mapping[$index] = $fieldsByLabel->get($normalized)->field_key;
            }
        }

        return $mapping;
    }

    public function preview(Request $request, CertificateTemplate $template): View
    {
        $this->authorize('create', Certificate::class);
        $this->authorize('view', $template);

        [$path, $storedFile] = $this->resolveStoredFile($request);
        $mapping = $this->sanitizeMapping($request->input('mapping', []));
        $data = $this->readStoredFile($path);

        $mappingErrors = $this->validator->validateMapping($template, $mapping);
        if ($mappingErrors !== []) {
            return view('admin.certificates.import.mapping', [
                'template' => $template,
                'headers' => $data['headers'],
                'sampleRow' => $data['rows'][0] ?? [],
                'storedFile' => $storedFile,
                'mapping' => $mapping,
                'mappingErrors' => $mappingErrors,
            ]);
        }

        $rows = $this->validator->validateRows($template, $data['headers'], $data['rows'], $mapping);
        $validRows = array_filter($rows, fn ($r) => $r->isValid());
        $invalidRows = array_filter($rows, fn ($r) => ! $r->isValid());

        return view('admin.certificates.import.preview', [
            'template' => $template,
            'storedFile' => $storedFile,
            'mapping' => $mapping,
            'totalRows' => count($rows),
            'validCount' => count($validRows),
            'invalidCount' => count($invalidRows),
            'sampleErrors' => array_slice($invalidRows, 0, 25),
            'moreErrors' => max(0, count($invalidRows) - 25),
        ]);
    }

    public function errorReport(Request $request, CertificateTemplate $template): Response
    {
        $this->authorize('create', Certificate::class);
        $this->authorize('view', $template);

        [$path] = $this->resolveStoredFile($request);
        $mapping = $this->sanitizeMapping($request->input('mapping', []));
        $data = $this->readStoredFile($path);

        $invalidRows = array_filter(
            $this->validator->validateRows($template, $data['headers'], $data['rows'], $mapping),
            fn ($r) => ! $r->isValid()
        );

        $lines = ['Row,Errors'];
        foreach ($invalidRows as $row) {
            $lines[] = $row->rowNumber.',"'.str_replace('"', '""', implode(' ', $row->errors)).'"';
        }

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="import-errors.csv"',
        ]);
    }

    public function confirm(Request $request, CertificateTemplate $template): View
    {
        $this->authorize('create', Certificate::class);
        $this->authorize('view', $template);

        [$path, $storedFile] = $this->resolveStoredFile($request);
        $mapping = $this->sanitizeMapping($request->input('mapping', []));
        $data = $this->readStoredFile($path);

        $mappingErrors = $this->validator->validateMapping($template, $mapping);
        if ($mappingErrors !== []) {
            throw ValidationException::withMessages(['mapping' => $mappingErrors]);
        }

        $rows = $this->validator->validateRows($template, $data['headers'], $data['rows'], $mapping);
        $validRows = array_values(array_filter($rows, fn ($r) => $r->isValid()));
        $invalidCount = count($rows) - count($validRows);

        $summary = $this->importer->import($template, $validRows, $request->user());

        Storage::disk('local')->delete($path);

        return view('admin.certificates.import.result', [
            'template' => $template,
            'summary' => $summary,
            'skippedTotal' => $invalidCount + $summary->skipped,
        ]);
    }

    /** @return array{0: string, 1: string} [absolute path, uuid] */
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
            if ($target === '' || $target === ImportMappingTarget::IGNORE) {
                continue;
            }
            $mapping[(int) $columnIndex] = $target;
        }

        return $mapping;
    }
}
