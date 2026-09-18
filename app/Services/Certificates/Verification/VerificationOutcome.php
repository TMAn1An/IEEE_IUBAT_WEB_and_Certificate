<?php

namespace App\Services\Certificates\Verification;

/**
 * What the public verification page shows. See
 * docs/CERTIFICATE_SYSTEM.md §Status rules for which `CertificateStatus`
 * values map to which outcome, and why anything other than Active/Revoked
 * (e.g. Reissued — superseded, no longer the authoritative record) is
 * treated as NotFound rather than inventing a fourth public state.
 */
enum VerificationOutcome
{
    case Verified;
    case Revoked;
    case NotFound;
}
