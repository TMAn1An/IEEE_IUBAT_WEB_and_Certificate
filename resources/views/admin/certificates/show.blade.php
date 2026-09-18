@php
  use App\Enums\TemplateFieldType;

  // QR-only certificates (Phase 6) have no template_snapshot (no PDF was
  // ever rendered, so nothing was snapshotted) -- fall back to the live
  // template's current fields for labeling `data` in that case. PDF-path
  // certificates (Phase 5) always have a snapshot and use it, so a later
  // template edit never changes how an already-issued certificate displays.
  $displayFields = $certificate->template_snapshot['fields']
    ?? $certificate->template->fields->map(fn ($f) => [
      'label' => $f->label, 'field_key' => $f->field_key, 'field_type' => $f->field_type->value,
    ])->all();
  $verificationUrl = app(\App\Services\Certificates\QrCodeService::class)->verificationUrlFor($certificate);
@endphp
<x-layouts.admin title="Certificate {{ $certificate->certificate_number }}">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <a href="{{ route('admin.certificates.index') }}" class="btn btn--ghost btn--sm">&larr; All certificates</a>
    @if ($certificate->pdf_path)
      <a href="{{ route('admin.certificates.download', $certificate) }}" download="{{ $certificate->certificate_number }}.pdf" class="btn btn--primary">Download PDF</a>
    @endif
  </div>

  <div class="admin-card">
    <h2 style="margin-top:0"><code>{{ $certificate->certificate_number }}</code></h2>

    <table class="admin-table">
      <tbody>
        <tr><th style="width:220px">Recipient</th><td>{{ $certificate->recipient_name }}</td></tr>
        <tr><th>Template</th><td>{{ $certificate->template->name }}</td></tr>
        <tr><th>Status</th><td><span class="badge {{ $certificate->status->badgeClass() }}">{{ $certificate->status->label() }}</span></td></tr>
        <tr><th>Issued</th><td>{{ $certificate->issued_at?->format('j M Y, g:i A') }}</td></tr>
        <tr><th>Issued by</th><td>{{ $certificate->creator?->name }}</td></tr>
      </tbody>
    </table>
  </div>

  <div class="admin-card">
    <h3 style="margin-top:0;font-size:1rem">Field values</h3>
    <table class="admin-table">
      <tbody>
        @foreach ($displayFields as $field)
          @continue(! TemplateFieldType::from($field['field_type'])->isAssignable())
          <tr>
            <th style="width:220px">{{ $field['label'] }}</th>
            <td>{{ $certificate->data[$field['field_key']] ?? '' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="admin-card">
    <h3 style="margin-top:0;font-size:1rem">Verification QR</h3>
    <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap">
      <img id="qr-image" src="{{ route('admin.certificates.qr.image', $certificate) }}" alt="Verification QR code" width="220" height="220" style="border:1px solid var(--border);background:#fff">
      <div style="min-width:260px">
        <div class="field">
          <label>Verification link</label>
          <input type="text" id="verify-url" value="{{ $verificationUrl }}" readonly onclick="this.select()">
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
          <a href="{{ route('admin.certificates.qr.image', $certificate) }}" download="{{ $certificate->certificate_number }}-qr.png" class="btn btn--primary btn--sm">Download QR PNG</a>
          <button type="button" class="btn btn--ghost btn--sm" id="copy-link-btn">Copy Verification Link</button>
          <a href="{{ $verificationUrl }}" target="_blank" rel="noopener" class="btn btn--ghost btn--sm">View Public Verification</a>
          <button type="button" class="btn btn--ghost btn--sm" id="copy-image-btn">Copy QR image</button>
          <a href="{{ route('admin.certificates.qr.create', $certificate->template) }}" class="btn btn--ghost btn--sm">Create another</a>
        </div>
        <p id="copy-status" style="color:var(--muted);font-size:.85rem;margin-top:8px"></p>
      </div>
    </div>
  </div>

  @if ($certificate->pdf_path)
    <div class="admin-card">
      <h3 style="margin-top:0;font-size:1rem">Certificate PDF</h3>
      <iframe src="{{ route('admin.certificates.download', $certificate) }}" style="width:100%;height:600px;border:1px solid var(--border)"></iframe>
    </div>
  @endif

  <script>
    (function () {
      var status = document.getElementById('copy-status');

      document.getElementById('copy-link-btn').addEventListener('click', function () {
        var url = document.getElementById('verify-url').value;
        navigator.clipboard.writeText(url).then(function () {
          status.textContent = 'Link copied.';
        }).catch(function () {
          status.textContent = 'Could not copy automatically -- select and copy the link field manually.';
        });
      });

      // Optional convenience (PNG download above is the guaranteed path).
      var copyImageBtn = document.getElementById('copy-image-btn');
      if (!navigator.clipboard || !window.ClipboardItem) {
        copyImageBtn.style.display = 'none';
      } else {
        copyImageBtn.addEventListener('click', function () {
          fetch(document.getElementById('qr-image').src)
            .then(function (r) { return r.blob(); })
            .then(function (blob) {
              return navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
            })
            .then(function () {
              status.textContent = 'QR image copied to clipboard.';
            })
            .catch(function () {
              status.textContent = 'Could not copy the image -- use Download QR PNG instead.';
            });
        });
      }
    })();
  </script>
</x-layouts.admin>
