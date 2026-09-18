<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Certificate number
    |--------------------------------------------------------------------------
    | Format: "{prefix}-{year}-{sequence, zero-padded}", e.g. IEEE-IUBAT-2026-000123.
    | The sequence resets every calendar year and is generated atomically by
    | App\Services\Certificates\CertificateNumberService — see
    | docs/CERTIFICATE_SYSTEM.md §Certificate number generation.
    */
    'number_prefix' => env('CERTIFICATE_NUMBER_PREFIX', 'IEEE-IUBAT'),
    'number_sequence_digits' => 6,

];
