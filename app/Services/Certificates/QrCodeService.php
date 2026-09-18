<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * QR content and rendering, kept separate from CertificatePdfService per
 * CLAUDE.md's "Keep QR logic in a dedicated QrCodeService" rule. The QR
 * encodes ONLY the future verification URL (never certificate data
 * directly) — see docs/CERTIFICATE_SYSTEM.md §QR contents.
 *
 * No new package: TCPDF bundles native QR generation
 * (`TCPDF::write2DBarcode()`), drawn as a real vector shape, not a
 * rasterized image — confirmed in the Phase 5 spike. Adding a separate QR
 * library (e.g. endroid/qr-code) would duplicate functionality already
 * present in a dependency this project needs anyway for PDF rendering.
 */
class QrCodeService
{
    /**
     * The public verification page/route is Phase 6 work and does not exist
     * yet. This URL is still generated now (per the Phase 5 brief) so
     * issued certificates never need their QR reprinted once Phase 6 adds
     * the matching route — only the codeword (already unique, already
     * CSPRNG-generated) has to match.
     */
    public function verificationUrlFor(Certificate $certificate): string
    {
        $path = str_replace('{token}', $certificate->codeword, config('certificates.verification_url_path'));

        return rtrim(config('app.url'), '/').$path;
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
}
