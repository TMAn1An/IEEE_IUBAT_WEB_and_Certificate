<?php

namespace App\Services\Templates;

use App\Models\CertificateTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores the admin-uploaded "demo certificate" PDF supplied at
 * template-creation time (PdfCertificatesController::store()) — the
 * starting point PdfStudioController::show() preloads into a brand-new
 * project (see docs/PDF_STUDIO_INTEGRATION.md's "PDF Certificates entry
 * flow"). Deliberately does not parse the PDF's page dimensions server-side
 * (no FPDI/Imagick dependency for that): the real page size is read from
 * the file itself via PDF.js, inside the editor, once it's loaded there.
 * Originally written for the old manual background/designer flow (removed
 * in the admin workflow cleanup) — reused as-is here, same storage
 * mechanics, new caller.
 */
class TemplateBackgroundService
{
    /**
     * Stores $file as $template's background. Never trusts the client's
     * filename for the storage path (see docs/SECURITY.md §File upload
     * safety) — only for the human-readable `original_filename` column.
     * Deletes the previous file only after the new one is safely stored and
     * the database row is committed, so a mid-request failure never leaves
     * the template without a valid file.
     */
    public function upload(CertificateTemplate $template, UploadedFile $file): CertificateTemplate
    {
        $previousPath = $template->source_pdf_path;

        $path = $file->storeAs(
            "certificate-templates/{$template->id}",
            Str::uuid()->toString().'.pdf',
            'local'
        );

        $template->update([
            'source_pdf_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'file_mime' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);

        if ($previousPath && $previousPath !== $path) {
            Storage::disk('local')->delete($previousPath);
        }

        return $template->fresh();
    }
}
