<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use setasign\Fpdi\Tcpdf\Fpdi;
use TCPDF2DBarcode;

/**
 * QR content and rendering, kept separate from CertificatePdfService per
 * CLAUDE.md's "Keep QR logic in a dedicated QrCodeService" rule. The QR
 * encodes ONLY the future verification URL (never certificate data
 * directly) — see docs/CERTIFICATE_SYSTEM.md §QR contents.
 *
 * No new package: TCPDF bundles native QR generation. `write2DBarcode()`
 * draws a real vector shape into an open PDF document (used by
 * CertificatePdfService); `TCPDF2DBarcode::getBarcodePngData()` (used by
 * `pngBytes()` below) generates the same QR as standalone PNG bytes with no
 * PDF document involved at all — confirmed working in the Phase 6 spike.
 * Adding a separate QR library (e.g. endroid/qr-code) would duplicate
 * functionality already present in a dependency this project needs anyway.
 */
class QrCodeService
{
    /**
     * The single URL-building implementation every caller uses (the QR
     * PNG/in-PDF drawing here, the admin certificate detail page's copy
     * link, and the manual QA phone-scan flow) — never build this string
     * any other way. Uses Laravel's own `route()` helper against the public
     * `certificate.verify` route (routes/web.php), so it always resolves
     * against the real `APP_URL` and can never drift from the route it's
     * meant to match. See docs/CERTIFICATE_SYSTEM.md §QR generator
     * integration.
     */
    public function verificationUrlFor(Certificate $certificate): string
    {
        return route('certificate.verify', ['codeword' => $certificate->codeword]);
    }

    /** @param  array{x: float, y: float, width: float, height: float}  $tcpdfBox  Already converted — see PdfCoordinateConverter. */
    public function drawOnPdf(Fpdi $pdf, array $tcpdfBox, string $url): void
    {
        $pdf->write2DBarcode(
            $url,
            'QRCODE,M',
            $tcpdfBox['x'],
            $tcpdfBox['y'],
            $tcpdfBox['width'],
            $tcpdfBox['height'],
            [],
            'N'
        );
    }

    /**
     * Standalone PNG bytes for the admin's "Download QR PNG" / on-screen
     * preview — no PDF document, nothing written to disk by this method
     * (generated on demand every request; see
     * docs/CERTIFICATE_SYSTEM.md §Storage — the QR is fully deterministic
     * from the verification URL, so there's nothing to persist).
     */
    public function pngBytes(string $url, int $moduleSize = 8): string
    {
        $barcode = new TCPDF2DBarcode($url, 'QRCODE,M');

        return $barcode->getBarcodePngData($moduleSize, $moduleSize, [0, 0, 0]);
    }
}
