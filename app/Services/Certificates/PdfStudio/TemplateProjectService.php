<?php

namespace App\Services\Certificates\PdfStudio;

use App\Models\CertificateTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Reads/writes the pdfeditor's own, unmodified `.pdftemplate` bundle format
 * (a ZIP: project.json + template.pdf + fonts/*.bin — see that repo's
 * src/lib/storage/bundle.ts) as a CertificateTemplate's saved PDF Studio
 * project. This service never parses the PDF itself and never touches
 * fonts/layout semantics — it only reads `project.json` far enough to
 * derive the Excel column schema (field id/label/column/required) and
 * resolve the admin-chosen QR/recipient field ids. See
 * docs/PDF_STUDIO_INTEGRATION.md.
 */
class TemplateProjectService
{
    public function save(CertificateTemplate $template, UploadedFile $bundle, string $qrFieldId, ?string $recipientFieldId): CertificateTemplate
    {
        $projectJson = $this->readProjectJson($bundle->getRealPath());
        $schema = $this->deriveSchema($projectJson, $qrFieldId, $recipientFieldId);

        $path = sprintf('certificate-templates/%d/project-%s.pdftemplate', $template->id, Str::uuid());
        Storage::disk('local')->putFileAs(
            dirname($path),
            $bundle,
            basename($path)
        );

        // The previous bundle at $template->editor_project_path is deliberately
        // NOT deleted here: any certificate_batches row created against the
        // template before this save pins that old path in its own
        // editor_project_path column (see DirectBatchService::confirm()) and
        // must keep rendering against it. See
        // docs/PDF_STUDIO_INTEGRATION.md's "Batch template immutability".
        $template->update([
            'editor_project_path' => $path,
            'editor_schema' => $schema,
            'editor_schema_version' => $template->editor_schema_version + 1,
        ]);

        return $template->fresh();
    }

    /** @return array{json: string}|never */
    public function bundleAbsolutePath(CertificateTemplate $template): string
    {
        if ($template->editor_project_path === null) {
            throw new RuntimeException('No PDF Studio project saved for this template.');
        }

        return Storage::disk('local')->path($template->editor_project_path);
    }

    /**
     * Resolves the immutable project bundle a given stored path points at.
     * Used for batch-scoped reads (CertificateBatch::$editor_project_path)
     * as well as the template's current path, so both go through the same
     * existence check.
     */
    public function bundleAbsolutePathFor(string $relativePath): string
    {
        if (! Storage::disk('local')->exists($relativePath)) {
            throw new RuntimeException('The saved PDF Studio project bundle is missing from storage.');
        }

        return Storage::disk('local')->path($relativePath);
    }

    /** @return array<string, mixed> */
    private function readProjectJson(string $zipAbsolutePath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($zipAbsolutePath) !== true) {
            throw new RuntimeException('Could not open the uploaded file as a project bundle.');
        }

        $json = $zip->getFromName('project.json');
        $zip->close();

        if ($json === false) {
            throw new RuntimeException('The project bundle has no project.json.');
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('The project bundle\'s project.json is not valid JSON.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $project
     * @return array{
     *   fields: list<array{id: string, label: string, column: string, required: bool, type: string}>,
     *   qr_field_id: string,
     *   recipient_field_id: ?string,
     *   project_name: string,
     * }
     */
    private function deriveSchema(array $project, string $qrFieldId, ?string $recipientFieldId): array
    {
        $fields = $project['fields'] ?? [];
        $mapping = $project['mapping'] ?? [];

        $fieldsById = [];
        foreach ($fields as $field) {
            if (isset($field['id'])) {
                $fieldsById[$field['id']] = $field;
            }
        }

        if (! isset($fieldsById[$qrFieldId])) {
            throw new RuntimeException('The chosen QR field was not found in the saved project.');
        }
        if ($fieldsById[$qrFieldId]['type'] !== 'image') {
            throw new RuntimeException('The QR field must be an image field.');
        }

        $schemaFields = [];
        foreach ($fields as $field) {
            $id = $field['id'] ?? null;
            if ($id === null || $id === $qrFieldId) {
                continue; // the QR field's value is always system-generated, never a required column
            }

            $source = $mapping[$id] ?? ['kind' => 'none'];
            if (($source['kind'] ?? null) !== 'column') {
                continue; // fixed text/date/image fields need no participant column
            }

            $schemaFields[] = [
                'id' => $id,
                'label' => $field['label'] ?? $id,
                'column' => $source['column'],
                'required' => (bool) ($field['required'] ?? false),
                'type' => $field['type'] ?? 'text',
            ];
        }

        return [
            'fields' => $schemaFields,
            'qr_field_id' => $qrFieldId,
            'recipient_field_id' => $recipientFieldId,
            'project_name' => $project['name'] ?? $qrFieldId,
        ];
    }
}
