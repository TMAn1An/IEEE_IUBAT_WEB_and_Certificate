<?php

namespace App\Services\Certificates;

use App\Models\Certificate;

/**
 * Generates `certificates.codeword` — the random verification secret the
 * QR/future `/verify/{codeword}` route uses (CLAUDE.md: "codeword" and
 * "certificate_number" are two different columns with two different
 * purposes, never one for the other). CSPRNG per CLAUDE.md's non-negotiable
 * rule — never `mt_rand()`, never derived from the row ID. Uniqueness is
 * enforced by the DB unique constraint; CertificateIssuanceService retries
 * generation on the rare collision, inside its transaction.
 */
class VerificationCodewordService
{
    private const LENGTH_BYTES = 32; // -> 64 hex characters

    public function generate(): string
    {
        return bin2hex(random_bytes(self::LENGTH_BYTES));
    }

    public function isUnique(string $codeword): bool
    {
        return ! Certificate::query()->where('codeword', $codeword)->exists();
    }
}
