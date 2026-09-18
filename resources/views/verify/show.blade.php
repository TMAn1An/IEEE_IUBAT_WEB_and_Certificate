@php
  use App\Services\Certificates\Verification\VerificationOutcome;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Certificate Verification &mdash; {{ config('site.site.name') }}</title>
{{-- Never indexed -- these URLs are per-certificate secrets, not public pages. --}}
<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="{{ config('site.site.favicon') }}" type="image/png">
<link rel="stylesheet" href="/assets/css/style.css">
<style>
  /* Minimal, self-contained -- reuses the public site's CSS variables/fonts
     (assets/css/style.css) for brand consistency without pulling in the
     full header/nav/footer chrome, which would be noise on a page almost
     everyone reaches by scanning a QR code on a phone. No JS required. */
  body { margin: 0; background: #f4f6f8; font-family: 'Open Sans', system-ui, sans-serif; color: #1a1a1a; }
  .verify-header { background: var(--ieee-navy, #002855); color: #fff; padding: 18px 20px; text-align: center; }
  .verify-header img { height: 36px; vertical-align: middle; margin-right: 10px; }
  .verify-header span { font-weight: 700; font-size: 1.05rem; vertical-align: middle; }
  .verify-wrap { max-width: 560px; margin: 24px auto; padding: 0 16px 40px; }
  .verify-card { background: #fff; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08); overflow: hidden; }
  .verify-status { padding: 24px 20px; text-align: center; color: #fff; }
  .verify-status--verified { background: var(--ieee-green, #00843D); }
  .verify-status--revoked { background: #92620a; }
  .verify-status--not-found { background: #a12525; }
  .verify-status h1 { margin: 0; font-size: 1.4rem; }
  .verify-status p { margin: 8px 0 0; opacity: .95; font-size: .95rem; }
  .verify-details { padding: 4px 20px 20px; }
  .verify-row { padding: 12px 0; border-bottom: 1px solid #eee; }
  .verify-row:last-child { border-bottom: none; }
  .verify-row dt { font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; color: #666; margin: 0; }
  .verify-row dd { margin: 4px 0 0; font-size: 1.05rem; font-weight: 600; }
  .verify-footnote { text-align: center; color: #888; font-size: .82rem; margin-top: 18px; }
</style>
</head>
<body>
  <div class="verify-header">
    <img src="{{ config('site.site.logo') }}" alt="">
    <span>{{ config('site.site.name') }}</span>
  </div>

  <div class="verify-wrap">
    <div class="verify-card">
      @switch($result->outcome)
        @case(VerificationOutcome::Verified)
          <div class="verify-status verify-status--verified">
            <h1>&#10003; Certificate Verified</h1>
          </div>
          <div class="verify-details">
            <dl>
              @if ($result->certificateNumber)
                <div class="verify-row">
                  <dt>Certificate Number</dt>
                  <dd>{{ $result->certificateNumber }}</dd>
                </div>
              @endif
              <div class="verify-row">
                <dt>Recipient</dt>
                <dd>{{ $result->recipientName }}</dd>
              </div>
              @if ($result->templateName)
                <div class="verify-row">
                  <dt>Certificate</dt>
                  <dd>{{ $result->templateName }}</dd>
                </div>
              @endif
              @if ($result->eventType)
                <div class="verify-row">
                  <dt>Event Type</dt>
                  <dd>{{ $result->eventType }}</dd>
                </div>
              @endif
              @if ($result->eventName)
                <div class="verify-row">
                  <dt>Conference/Event</dt>
                  <dd>{{ $result->eventName }}</dd>
                </div>
              @endif
              @foreach ($result->publicFields as $field)
                <div class="verify-row">
                  <dt>{{ $field->label }}</dt>
                  <dd>{{ $field->value }}</dd>
                </div>
              @endforeach
              <div class="verify-row">
                <dt>Issued</dt>
                <dd>{{ $result->issuedAt }}</dd>
              </div>
              <div class="verify-row">
                <dt>Issued by</dt>
                <dd>{{ config('site.site.name') }}</dd>
              </div>
            </dl>
          </div>
          @break

        @case(VerificationOutcome::Revoked)
          <div class="verify-status verify-status--revoked">
            <h1>&#9888; Certificate Revoked</h1>
          </div>
          <div class="verify-details">
            @if ($result->certificateNumber)
              <dl>
                <div class="verify-row">
                  <dt>Certificate Number</dt>
                  <dd>{{ $result->certificateNumber }}</dd>
                </div>
              </dl>
            @endif
            <p>This certificate was previously issued but is no longer considered valid by {{ config('site.site.name') }}.</p>
          </div>
          @break

        @default
          <div class="verify-status verify-status--not-found">
            <h1>&times; Certificate Not Verified</h1>
          </div>
          <div class="verify-details">
            <p>We could not verify a certificate using this verification code.</p>
            <p>Please make sure the QR code came from the original certificate or contact {{ config('site.site.name') }}.</p>
          </div>
      @endswitch
    </div>

    <p class="verify-footnote">Official certificate verification record &middot; {{ config('site.site.name') }}</p>
  </div>
</body>
</html>
