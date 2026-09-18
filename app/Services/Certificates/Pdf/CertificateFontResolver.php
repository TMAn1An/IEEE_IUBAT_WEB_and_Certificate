<?php

namespace App\Services\Certificates\Pdf;

/**
 * Picks which embedded TCPDF font to render a given field value with.
 *
 * Font strategy (see docs/CERTIFICATE_SYSTEM.md §Font strategy for the full
 * writeup): TCPDF has no HarfBuzz-style shaping engine, so it cannot mix
 * scripts within a single Cell/MultiCell call with correct shaping, and
 * per-character script-run segmentation is out of scope for Phase 5 (real
 * names are overwhelmingly single-script in practice). This resolver picks
 * ONE font for the whole field value based on whether it contains any
 * Bengali codepoints (U+0980-U+09FF):
 *   - dejavusans (bundled with TCPDF) for Latin, including accented/
 *     diacritic characters (José García) — full Unicode coverage for Latin
 *     script.
 *   - notosansbengali (resources/fonts/, embedded — see
 *     resources/fonts/source/NotoSansBengali-OFL.txt for its license) for
 *     any value containing Bengali script.
 *
 * Known limitation, documented rather than silently accepted: complex
 * Bengali conjunct clusters (যুক্তাক্ষর) render as TCPDF maps individual
 * Unicode codepoints to glyphs, without guaranteed correct ligature
 * substitution/reordering a full shaping engine would provide. Simple/common
 * Bengali names render correctly (verified against the real demo
 * certificate in the Phase 5 spike); visually spot-check unusual names
 * before high-stakes printing. A shaping-aware renderer is a valid future
 * upgrade if this proves insufficient in practice — not built now to avoid
 * over-engineering a case that hasn't caused a real problem yet.
 */
class CertificateFontResolver
{
    private const BENGALI_PATTERN = '/[\x{0980}-\x{09FF}]/u';

    private const DEJAVU_SANS = 'dejavusans';

    private const DEJAVU_SANS_BOLD = 'dejavusansb';

    private const NOTO_BENGALI = 'notosansbengali';

    public function fontFor(string $text, bool $bold): string
    {
        if (preg_match(self::BENGALI_PATTERN, $text) === 1) {
            // Noto Sans Bengali is only embedded in its regular weight (see
            // resources/fonts/tcpdf/) -- TCPDF fakes bold via a "B" style
            // flag applied to the regular glyph set when no dedicated bold
            // file is registered, which is an acceptable Phase 5 tradeoff.
            return self::NOTO_BENGALI;
        }

        return $bold ? self::DEJAVU_SANS_BOLD : self::DEJAVU_SANS;
    }

    public function fontFilePath(string $fontName): ?string
    {
        if ($fontName === self::NOTO_BENGALI) {
            return resource_path('fonts/tcpdf/notosansbengali.php');
        }

        return null;
    }
}
