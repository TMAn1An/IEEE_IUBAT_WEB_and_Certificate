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

    /**
     * The accepted codeword shape, shared by three call sites so it can
     * never drift between them: the public verification route's `where()`
     * constraint, the Excel importer's format check
     * (App\Services\Certificates\Import\CertificateImportValidator), and
     * this class's own `generate()` output. Deliberately broader than "64
     * hex characters" (what `generate()` produces) — historical codewords
     * preserved from the old local tool via Excel import
     * (docs/CERTIFICATE_SYSTEM.md §Excel import) may be shorter/different,
     * and the route must accept those too. The route bounds the *maximum*
     * length so a malicious giant URL can't cause expensive processing; the
     * minimum rules out empty/single-character junk.
     */
    public const ACCEPTED_PATTERN = '[A-Za-z0-9_-]{4,128}';

    public function generate(): string
    {
        return bin2hex(random_bytes(self::LENGTH_BYTES));
    }

    public function isUnique(string $codeword): bool
    {
        return ! Certificate::query()->where('codeword', $codeword)->exists();
    }
}
