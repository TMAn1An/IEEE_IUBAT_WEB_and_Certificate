<?php

namespace App\Services\Certificates\Pdf;

use App\Models\CertificateTemplate;
use App\Models\TemplateField;
use App\Services\Certificates\QrCodeService;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Renders a final certificate PDF: the template's real PDF background
 * (imported via FPDI), every dynamic field's value at its Phase 4 designer
 * position/style, the certificate number, and the verification QR code. See
 * docs/CERTIFICATE_SYSTEM.md §PDF generation workflow.
 *
 * Deliberately stateless / side-effect-free: returns the rendered bytes in
 * memory (TCPDF Output('S')) rather than writing to disk itself. Nothing is
 * written anywhere if rendering throws partway through — the caller
 * (CertificateIssuanceService) decides when/whether to persist, which is
 * what makes "failure during PDF generation does not leave a falsely-issued
 * certificate" trivially true rather than something to clean up after.
 */
class CertificatePdfService
{
    public function __construct(
        private readonly PdfPageBoxReader $pageBoxReader,
        private readonly PdfCoordinateConverter $converter,
        private readonly CertificateFontResolver $fonts,
        private readonly QrCodeService $qr,
    ) {}

    /**
     * @param  array<string, string>  $fieldValues  field_key => already-validated display value.
     */
    public function render(
        CertificateTemplate $template,
        array $fieldValues,
        string $certificateNumber,
        string $verificationUrl,
    ): CertificatePdfRenderResult {
        if (! $template->hasBackground()) {
            throw new RuntimeException('Template has no background PDF uploaded.');
        }

        $sourcePath = Storage::disk('local')->path($template->source_pdf_path);
        $pageBox = $this->pageBoxReader->read($sourcePath);

        $pdf = new Fpdi('L', 'pt');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetCreator('IEEE IUBAT Certificate System');
        $pdf->SetTitle("Certificate {$certificateNumber}");

        $pdf->AddFont('notosansbengali', '', $this->fonts->fontFilePath('notosansbengali'));

        $orientation = $pageBox->width >= $pageBox->height ? 'L' : 'P';
        $pdf->setSourceFile($sourcePath);
        $templateId = $pdf->importPage(1);
        $pdf->AddPage($orientation, [$pageBox->width, $pageBox->height]);
        $pdf->useTemplate($templateId, 0, 0, $pageBox->width, $pageBox->height);

        $overflowWarnings = [];

        /** @var TemplateField $field */
        foreach ($template->fields as $field) {
            if (! $field->field_type->isAssignable()) {
                // certificate_number / qr_code are never template_fields rows
                // (see docs/CERTIFICATE_SYSTEM.md §System fields) -- unreachable
                // in practice, kept for defense in depth.
                continue;
            }

            $value = $fieldValues[$field->field_key] ?? '';
            if ($value === '' || $field->position === null) {
                continue;
            }

            $warning = $this->drawTextBox($pdf, $value, $field->position, $field->style ?? [], $pageBox, $field->label);
            if ($warning !== null) {
                $overflowWarnings[] = $warning;
            }
        }

        if ($template->certificate_number_layout !== null) {
            $layout = $template->certificate_number_layout;
            $this->drawTextBox($pdf, $certificateNumber, $layout, $layout['style'] ?? [], $pageBox, 'Certificate number');
        }

        if ($template->qr_code_layout !== null) {
            $box = $this->converter->toTcpdfBox($template->qr_code_layout, $pageBox);
            $this->qr->drawOnPdf($pdf, $box, $verificationUrl);
        }

        $pdfContents = $pdf->Output('', 'S');

        return new CertificatePdfRenderResult($pdfContents, $pageBox, $overflowWarnings);
    }

    /**
     * @param  array{x: float, y: float, width: float, height: float}  $rawBox
     * @param  array<string, mixed>  $style
     */
    private function drawTextBox(Fpdi $pdf, string $text, array $rawBox, array $style, PdfPageBox $pageBox, string $label): ?string
    {
        $box = $this->converter->toTcpdfBox($rawBox, $pageBox);

        $fontSize = (float) ($style['font_size'] ?? 14);
        $bold = ($style['font_weight'] ?? 'normal') === 'bold';
        $align = match ($style['alignment'] ?? 'left') {
            'center' => 'C',
            'right' => 'R',
            default => 'L',
        };
        $lineHeightRatio = (float) ($style['line_height'] ?? 1.2);
        $wrap = (bool) ($style['wrap'] ?? false);
        [$r, $g, $b] = $this->parseColor($style['color'] ?? '#000000');

        $font = $this->fonts->fontFor($text, $bold);
        $pdf->SetFont($font, $bold && $font !== 'notosansbengali' ? 'B' : '', $fontSize);
        $pdf->SetTextColor($r, $g, $b);

        $previousRatio = $pdf->getCellHeightRatio();
        $pdf->setCellHeightRatio($lineHeightRatio);

        $overflow = null;

        if ($wrap) {
            $lineHeight = $fontSize * $lineHeightRatio;
            $availableLines = max(1, (int) floor($box['height'] / $lineHeight));
            $neededLines = $pdf->getNumLines($text, $box['width']);

            if ($neededLines > $availableLines) {
                $overflow = "\"{$label}\" text may not fit its configured box ({$neededLines} lines needed, {$availableLines} fit).";
            }

            $pdf->MultiCell($box['width'], $box['height'], $text, 0, $align, false, 0, $box['x'], $box['y'], true, 0, false, true, 0, 'T');
        } else {
            $pdf->SetXY($box['x'], $box['y']);
            $pdf->Cell($box['width'], $box['height'], $text, 0, 0, $align);
        }

        $pdf->setCellHeightRatio($previousRatio);

        return $overflow;
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function parseColor(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return [0, 0, 0];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
