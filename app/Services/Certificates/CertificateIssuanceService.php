<?php

namespace App\Services\Certificates;

use App\Enums\CertificateStatus;
use App\Enums\CertificateTemplateStatus;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\User;
use App\Services\Certificates\Pdf\CertificatePdfService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Orchestrates single-certificate issuance end to end. See
 * docs/CERTIFICATE_SYSTEM.md §Issuance lifecycle and §Failure handling for
 * the full writeup of the ordering decided here.
 *
 * Everything (number/codeword minting, PDF rendering, storing the file,
 * inserting the row) happens inside one DB transaction. If anything throws
 * -- including the PDF renderer itself -- the transaction rolls back AND the
 * catch block below deletes the PDF file if one was already written, so a
 * failure never leaves either a DB row without a file or a file without a
 * DB row. This is deliberately simpler than a "status = generating"
 * placeholder row: the whole flow is synchronous, in-process and fast (no
 * external I/O besides one local-disk write), so there's no benefit to a
 * visible intermediate state a user could observe mid-transaction anyway.
 * CertificateStatus::GenerationFailed stays in the enum for a future
 * async/queued path, but nothing in this flow ever writes it.
 */
class CertificateIssuanceService
{
    private const MAX_CODEWORD_ATTEMPTS = 5;

    public function __construct(
        private readonly CertificatePdfService $pdfService,
        private readonly CertificateNumberService $numbers,
        private readonly VerificationCodewordService $codewords,
        private readonly CertificateSnapshotService $snapshots,
        private readonly QrCodeService $qr,
    ) {}

    /**
     * @param  array<string, string>  $fieldValues  field_key => validated value, already confirmed to belong to $template (IssueCertificateRequest).
     */
    public function issue(CertificateTemplate $template, array $fieldValues, User $issuedBy): IssuanceResult
    {
        if ($template->status !== CertificateTemplateStatus::Active) {
            // Defense in depth -- the controller/policy already restrict this,
            // see docs/CERTIFICATE_SYSTEM.md §Permissions.
            throw ValidationException::withMessages([
                'template' => 'Only active templates can be used to issue certificates.',
            ]);
        }

        if (! $template->hasBackground()) {
            throw ValidationException::withMessages([
                'template' => 'This template has no certificate background uploaded yet.',
            ]);
        }

        $recipientField = $template->fields->firstWhere('is_recipient_name', true);
        if ($recipientField === null) {
            // Unreachable in practice -- TemplateService::activationErrors()
            // blocks activation without exactly one recipient-name field --
            // kept as an explicit guard since issuance is the point this
            // value actually gets read and relied on.
            throw ValidationException::withMessages([
                'template' => 'Template has no recipient name field configured.',
            ]);
        }
        $recipientName = (string) ($fieldValues[$recipientField->field_key] ?? '');

        $pdfPath = null;

        try {
            return DB::transaction(function () use ($template, $fieldValues, $recipientName, $issuedBy, &$pdfPath) {
                $certificateNumber = $this->numbers->next();
                $codeword = $this->generateUniqueCodeword();
                $verificationUrl = $this->qr->verificationUrlFor(
                    (new Certificate)->forceFill(['codeword' => $codeword])
                );

                $render = $this->pdfService->render($template, $fieldValues, $certificateNumber, $verificationUrl);

                $pdfPath = sprintf('certificates/%d/%s.pdf', now()->year, Str::uuid());
                Storage::disk('local')->put($pdfPath, $render->pdfContents);

                $certificate = Certificate::create([
                    'certificate_template_id' => $template->id,
                    'certificate_number' => $certificateNumber,
                    'codeword' => $codeword,
                    'recipient_name' => $recipientName,
                    'data' => $fieldValues,
                    'pdf_path' => $pdfPath,
                    'template_snapshot' => $this->snapshots->templateSnapshot($template),
                    'layout_snapshot' => $this->snapshots->layoutSnapshot($template, $render->pageBox),
                    'status' => CertificateStatus::Active,
                    'issued_at' => now(),
                    'created_by' => $issuedBy->id,
                ]);

                return new IssuanceResult($certificate, $render->overflowWarnings);
            });
        } catch (Throwable $e) {
            if ($pdfPath !== null && Storage::disk('local')->exists($pdfPath)) {
                Storage::disk('local')->delete($pdfPath);
            }

            throw $e;
        }
    }

    private function generateUniqueCodeword(): string
    {
        for ($attempt = 1; $attempt <= self::MAX_CODEWORD_ATTEMPTS; $attempt++) {
            $codeword = $this->codewords->generate();
            if ($this->codewords->isUnique($codeword)) {
                return $codeword;
            }
        }

        throw new RuntimeException('Could not generate a unique verification codeword after '.self::MAX_CODEWORD_ATTEMPTS.' attempts.');
    }
}
