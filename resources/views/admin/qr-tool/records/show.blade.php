@php
  $verificationUrl = app(\App\Services\Certificates\QrCodeService::class)->verificationUrlForCodeword($certificate->codeword);
@endphp
<x-layouts.admin title="QR Record — {{ $certificate->recipient_name }}">
  <div style="margin-bottom:16px">
    <a href="{{ route('admin.qr.records.index') }}" class="btn btn--ghost btn--sm">&larr; All records</a>
  </div>

  <div class="admin-card">
    <h2 style="margin-top:0">{{ $certificate->recipient_name }}</h2>

    <table class="admin-table">
      <tbody>
        <tr><th style="width:220px">Category</th><td>{{ $certificate->category->name }}</td></tr>
        @if ($certificate->event_name)
          <tr><th>Event/Conference</th><td>{{ $certificate->event_name }}</td></tr>
        @endif
        <tr><th>Status</th><td><span class="badge {{ $certificate->status->badgeClass() }}">{{ $certificate->status->label() }}</span></td></tr>
        <tr><th>Created</th><td>{{ $certificate->created_at->format('j M Y, g:i A') }}</td></tr>
        <tr><th>Created by</th><td>{{ $certificate->creator?->name }}</td></tr>
      </tbody>
    </table>
  </div>

  <div class="admin-card">
    <h3 style="margin-top:0;font-size:1rem">Field values</h3>
    <table class="admin-table">
      <tbody>
        @foreach ($certificate->category->fields as $field)
          <tr>
            <th style="width:220px">{{ $field->label }}</th>
            <td>{{ $certificate->data[$field->key] ?? '' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="admin-card">
    <h3 style="margin-top:0;font-size:1rem">Verification QR</h3>
    <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap">
      <img id="qr-image" src="{{ route('admin.qr.records.qr-image', $certificate) }}" alt="Verification QR code" width="220" height="220" style="border:1px solid var(--border);background:#fff">
      <div style="min-width:260px">
        <div class="field">
          <label>Verification link</label>
          <input type="text" id="verify-url" value="{{ $verificationUrl }}" readonly onclick="this.select()">
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
          <a href="{{ route('admin.qr.records.qr-image', $certificate) }}" download="{{ $certificate->codeword }}-qr.png" class="btn btn--primary btn--sm">Download QR PNG</a>
          <button type="button" class="btn btn--ghost btn--sm" id="copy-link-btn">Copy Verification Link</button>
          <a href="{{ $verificationUrl }}" target="_blank" rel="noopener" class="btn btn--ghost btn--sm">View Verification</a>
          <button type="button" class="btn btn--ghost btn--sm" id="copy-image-btn">Copy QR image</button>
          <a href="{{ route('admin.qr.generate.show') }}" class="btn btn--ghost btn--sm">Create another</a>
        </div>
        <p id="copy-status" style="color:var(--muted);font-size:.85rem;margin-top:8px"></p>
      </div>
    </div>
  </div>

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
