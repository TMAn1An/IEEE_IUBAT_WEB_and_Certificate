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

    /*
    |--------------------------------------------------------------------------
    | Verification URL
    |--------------------------------------------------------------------------
    | The public verification page itself is Phase 6 work (not built yet).
    | This is the URL shape the Phase 5 QR code encodes so Phase 6 only has
    | to add the matching route + controller, not touch already-issued
    | certificates. {token} is replaced with the certificate's codeword.
    | See docs/CERTIFICATE_SYSTEM.md §QR contents.
    */
    'verification_url_path' => env('CERTIFICATE_VERIFICATION_URL_PATH', '/certificate/verify/{token}'),

];
