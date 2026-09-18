<?php

namespace App\Services\Certificates;

use Illuminate\Support\Facades\DB;

/**
 * Generates human-readable, sequential-looking certificate numbers, e.g.
 * IEEE-IUBAT-2026-000123 (format configurable — see config/certificates.php).
 * Race-condition safe: must be called from inside an already-open DB
 * transaction (CertificateIssuanceService does this) so the row lock below
 * actually serializes concurrent callers instead of just racing on the
 * eventual INSERT.
 *
 * A dedicated `certificate_number_counters` table (one row per year) with
 * `SELECT ... FOR UPDATE` is used rather than `COUNT(certificates WHERE
 * year=?) + 1` — counting rows gives no lock to serialize against, so two
 * concurrent requests could read the same count and mint the same number
 * (only caught, if at all, by the unique constraint after both already did
 * the expensive PDF render). The counter row lock makes collisions
 * structurally impossible instead of merely detected-and-retried.
 */
class CertificateNumberService
{
    /** Must run inside an open DB transaction. */
    public function next(): string
    {
        $year = (int) now()->format('Y');

        $counter = DB::table('certificate_number_counters')
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if ($counter === null) {
            DB::table('certificate_number_counters')->insert([
                'year' => $year,
                'next_sequence' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $sequence = 1;
        } else {
            $sequence = $counter->next_sequence;
        }

        DB::table('certificate_number_counters')
            ->where('year', $year)
            ->update(['next_sequence' => $sequence + 1, 'updated_at' => now()]);

        $digits = (int) config('certificates.number_sequence_digits');
        $padded = str_pad((string) $sequence, $digits, '0', STR_PAD_LEFT);

        return sprintf('%s-%d-%s', config('certificates.number_prefix'), $year, $padded);
    }
}
