<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use TCPDF2DBarcode;

/**
 * QR content and rendering, kept in its own dedicated service per
 * CLAUDE.md's "Keep QR logic in a dedicated QrCodeService" rule. The QR
 * encodes ONLY the future verification URL (never certificate data
 * directly) — see docs/CERTIFICATE_SYSTEM.md §QR contents.
 *
 * No new package: `tecnickcom/tcpdf` (already a dependency for the simple
 * QR tool's/PDF Studio's QR PNGs) bundles native QR generation —
 * `TCPDF2DBarcode::getBarcodePngData()` (used by `pngBytes()` below)
 * generates a QR as standalone PNG bytes with no PDF document involved at
 * all. Adding a separate QR library (e.g. endroid/qr-code) would duplicate
 * functionality already present in a dependency this project needs anyway.
 * This class previously also had a `drawOnPdf()` method
 * (`write2DBarcode()`, drawing a QR directly into an open `setasign/fpdi`
 * PDF document) for the old server-side single-certificate/PdfEditorBridge
 * PDF rendering pipelines — removed as dead code along with those
 * pipelines in the admin workflow cleanup (`setasign/fpdi` had no other
 * caller and was dropped from composer.json). PDF Studio never rendered
 * PDFs server-side at all — the QR is composited client-side by the
 * pdfeditor engine from this same PNG.
 */
class QrCodeService
{
    /**
     * The single URL-building implementation every caller uses — the
     * advanced system's QR PNG/in-PDF drawing and admin copy-link, AND
     * (via `verificationUrlForCodeword()` below) the independent simple QR
     * tool's equivalents. This class knows nothing about either domain
     * model beyond a bare codeword string, which is exactly why it's safe
     * to share between the two otherwise-decoupled systems — see
     * docs/CERTIFICATE_SYSTEM.md §Simple QR tool for the isolation
     * boundary this class deliberately sits outside of. Uses Laravel's own
     * `route()` helper against the public `certificate.verify` route
     * (routes/web.php), so it always resolves against the real `APP_URL`
     * and can never drift from the route it's meant to match.
     */
    public function verificationUrlFor(Certificate $certificate): string
    {
        return $this->verificationUrlForCodeword($certificate->codeword);
    }

    public function verificationUrlForCodeword(string $codeword): string
    {
        return route('certificate.verify', ['codeword' => $codeword]);
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
