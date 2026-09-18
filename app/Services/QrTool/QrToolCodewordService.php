<?php

namespace App\Services\QrTool;

use App\Models\QrCertificate;

/**
 * Matches the old tool's codeword format exactly -- inspected directly from
 * IEEEQRCODEGENERATOR-main/app.py's `generate_unique_codeword()`:
 *
 *   alphabet = string.ascii_uppercase + string.digits   # A-Z0-9, 36 chars
 *   code = "".join(secrets.choice(alphabet) for _ in range(16))
 *
 * i.e. 16 characters, uppercase letters + digits only, via Python's
 * `secrets.choice()` (CSPRNG). This class reproduces the same shape with
 * PHP's own CSPRNG (`random_int()`, never `mt_rand()` — CLAUDE.md's
 * non-negotiable rule) rather than converting/reusing the old tool's actual
 * output. This is a deliberately DIFFERENT format from the advanced
 * system's 64-character hex codeword
 * (App\Services\Certificates\VerificationCodewordService) — both are valid
 * and both are accepted by the shared public verification route; see
 * VerificationCodewordService::ACCEPTED_PATTERN, which already covers this
 * format without any change.
 */
class QrToolCodewordService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    private const LENGTH = 16;

    public function generate(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $codeword = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $codeword .= self::ALPHABET[random_int(0, $max)];
        }

        return $codeword;
    }

    public function isUnique(string $codeword): bool
    {
        return ! QrCertificate::query()->where('codeword', $codeword)->exists();
    }
}
