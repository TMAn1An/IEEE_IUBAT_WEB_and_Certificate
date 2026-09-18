<?php

namespace App\Services\Certificates\Pdf;

use RuntimeException;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfReader\PageBoundaries;
use setasign\Fpdi\PdfReader\PdfReader;

/**
 * Reads a PDF's true page box (CropBox, falling back to MediaBox per spec —
 * the same box PDF.js uses for its viewport in the Phase 4 designer, and the
 * same box FPDI's importPage() normalizes against) via FPDI's public,
 * standalone PdfReader API. Deliberately does NOT go through the
 * Fpdi/TCPDF import class — getPdfReader() there is protected — so this can
 * be called independently to validate/derive dimensions without building a
 * full TCPDF document first. See docs/CERTIFICATE_SYSTEM.md §PDF coordinate
 * conversion for why this exists and why `certificate_templates.page_width`/
 * `page_height` (a Phase 4 designer convenience column) are never trusted
 * for rendering.
 */
class PdfPageBoxReader
{
    public function read(string $absolutePath): PdfPageBox
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException("PDF file not found: {$absolutePath}");
        }

        $reader = new PdfReader(new PdfParser(StreamReader::createByFile($absolutePath)));
        $box = $reader->getPage(1)->getBoundary(PageBoundaries::CROP_BOX);

        if ($box === false) {
            throw new RuntimeException("PDF has no usable page box: {$absolutePath}");
        }

        return new PdfPageBox(
            llx: $box->getLlx(),
            lly: $box->getLly(),
            width: $box->getWidth(),
            height: $box->getHeight(),
        );
    }
}
