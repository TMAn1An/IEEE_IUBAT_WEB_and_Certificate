<?php

namespace App\Services\Certificates\Pdf;

/**
 * The one conversion boundary between Phase 4's stored coordinate space and
 * TCPDF's drawing API. Do not replicate this math anywhere else — every
 * renderer call goes through here. See docs/CERTIFICATE_SYSTEM.md
 * §PDF coordinate conversion for the full derivation and the real-PDF proof.
 *
 * Phase 4 stores {x, y, width, height} in RAW PDF points exactly as
 * PDF.js's convertToPdfPoint()/convertToViewportPoint() produce them: y
 * grows upward, and the origin is the PDF's true page box origin, which is
 * frequently NOT (0,0) — the actual Canva demo certificate has
 * MediaBox llx=0, lly=8.579974.
 *
 * FPDI's importPage() normalizes the imported page so the box's own
 * lower-left corner becomes its LOCAL (0,0) — it subtracts (llx, lly)
 * internally when building the placement matrix. TCPDF's own public
 * drawing API (SetXY/Cell/MultiCell/Image/Rect) then uses a TOP-left
 * origin, y growing downward, relative to that same normalized page.
 *
 * So converting a raw Phase-4 box to a TCPDF box takes two steps:
 *   1. Subtract (llx, lly) to land in the FPDI-normalized space.
 *   2. Flip y relative to the normalized page height.
 */
class PdfCoordinateConverter
{
    /**
     * @param  array{x: float, y: float, width: float, height: float}  $rawBox  In Phase 4's raw PDF-point space.
     * @return array{x: float, y: float, width: float, height: float} In TCPDF's top-left/y-down point space.
     */
    public function toTcpdfBox(array $rawBox, PdfPageBox $page): array
    {
        $normalizedX = $rawBox['x'] - $page->llx;
        $normalizedY = $rawBox['y'] - $page->lly;

        return [
            'x' => $normalizedX,
            'y' => $page->height - $normalizedY - $rawBox['height'],
            'width' => $rawBox['width'],
            'height' => $rawBox['height'],
        ];
    }
}
