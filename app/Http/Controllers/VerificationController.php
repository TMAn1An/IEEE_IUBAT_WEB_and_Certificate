<?php

namespace App\Http\Controllers;

use App\Services\Certificates\Verification\CertificateVerificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * Public certificate verification — no auth, no admin layout. See
 * docs/CERTIFICATE_SYSTEM.md §Public verification.
 */
class VerificationController extends Controller
{
    public function show(string $codeword, CertificateVerificationService $verification): Response
    {
        $result = $verification->verify($codeword);

        /** @var View $view */
        $view = view('verify.show', ['result' => $result]);

        return response($view)
            // Never let this be indexed (docs/CERTIFICATE_SYSTEM.md
            // §noindex/cache/rate-limit protections) and never cached --
            // a status shown here (e.g. Verified) can change later
            // (revocation), so a stale cached response must never be served.
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store');
    }
}
