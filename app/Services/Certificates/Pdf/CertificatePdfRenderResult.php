<?php

namespace App\Services\Certificates\Pdf;

final readonly class CertificatePdfRenderResult
{
    /** @param  list<string>  $overflowWarnings  Human-readable "field X's text may not fit its box" notices — see docs/CERTIFICATE_SYSTEM.md §Failure handling. Rendering still succeeds; these are surfaced to the admin, not thrown. */
    public function __construct(
        public string $pdfContents,
        public PdfPageBox $pageBox,
        public array $overflowWarnings,
    ) {}
}
