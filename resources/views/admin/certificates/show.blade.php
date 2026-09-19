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
    @if ($certificate->pdf_path && ! $certificate->trashed())
      <a href="{{ route('admin.certificates.download', $certificate) }}" download="{{ $certificate->certificate_number }}.pdf" class="btn btn--primary">Download PDF</a>
    @endif
  </div>

  @if ($certificate->trashed())
    <div class="admin-card" style="border-left:4px solid var(--danger)">
      <h2 style="margin-top:0">Record Deleted</h2>
      <p style="color:var(--muted)">
        This certificate was soft-deleted following an approved deletion request. It no longer
        verifies publicly and no longer appears in normal certificate lists — the row is retained
        here only for recovery/audit purposes.
      </p>
      @if ($deletionRequest?->completed_at)
        <p><strong>Completed at:</strong> {{ $deletionRequest->completed_at->format('j M Y, g:i A') }}</p>
      @endif
    </div>
  @endif

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

  @unless ($certificate->trashed())
    <div class="admin-card">
      <h3 style="margin-top:0;font-size:1rem">Public verification</h3>
      <div class="field">
        <label>Verification link</label>
        <input type="text" id="verify-url" value="{{ $verificationUrl }}" readonly onclick="this.select()">
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
        <button type="button" class="btn btn--ghost btn--sm" id="copy-link-btn">Copy Verification Link</button>
        <a href="{{ $verificationUrl }}" target="_blank" rel="noopener" class="btn btn--ghost btn--sm">View Public Verification</a>
      </div>
      <p id="copy-status" style="color:var(--muted);font-size:.85rem;margin-top:8px"></p>
    </div>

    @if ($certificate->pdf_path)
      <div class="admin-card">
        <h3 style="margin-top:0;font-size:1rem">Certificate PDF</h3>
        <iframe src="{{ route('admin.certificates.download', $certificate) }}" style="width:100%;height:600px;border:1px solid var(--border)"></iframe>
      </div>
    @endif

    <x-admin.deletion-request-panel
      :record="$certificate"
      :deletion-request="$deletionRequest"
      :action="route('admin.certificates.request-deletion', $certificate)"
    />
  @endunless

  <script>
    var copyLinkBtn = document.getElementById('copy-link-btn');
    if (copyLinkBtn) {
      copyLinkBtn.addEventListener('click', function () {
        var url = document.getElementById('verify-url').value;
        var status = document.getElementById('copy-status');
        navigator.clipboard.writeText(url).then(function () {
          status.textContent = 'Link copied.';
        }).catch(function () {
          status.textContent = 'Could not copy automatically -- select and copy the link field manually.';
        });
      });
    }
  </script>
</x-layouts.admin>
