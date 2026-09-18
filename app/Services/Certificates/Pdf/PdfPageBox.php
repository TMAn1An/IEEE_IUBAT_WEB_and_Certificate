<?php

namespace App\Services\Certificates\Pdf;

/**
 * The real, raw page box of a source PDF — llx/lly are frequently non-zero
 * (verified against the actual Canva-exported demo certificate: MediaBox
 * [0.0 8.579974 842.25 604.07996]). width/height are the box's own extent
 * (urx-llx, ury-lly), NOT the raw ury/urx values. See
 * docs/CERTIFICATE_SYSTEM.md §PDF coordinate conversion.
 */
final readonly class PdfPageBox
{
    public function __construct(
        public float $llx,
        public float $lly,
        public float $width,
        public float $height,
    ) {}
}
