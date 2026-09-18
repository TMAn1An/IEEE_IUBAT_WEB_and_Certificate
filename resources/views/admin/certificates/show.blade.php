@php use App\Enums\TemplateFieldType; @endphp
<x-layouts.admin title="Certificate {{ $certificate->certificate_number }}">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <a href="{{ route('admin.certificates.index') }}" class="btn btn--ghost btn--sm">&larr; All certificates</a>
    <a href="{{ route('admin.certificates.download', $certificate) }}" download="{{ $certificate->certificate_number }}.pdf" class="btn btn--primary">Download PDF</a>
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
        @foreach (($certificate->template_snapshot['fields'] ?? []) as $field)
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
    <h3 style="margin-top:0;font-size:1rem">Certificate PDF</h3>
    <iframe src="{{ route('admin.certificates.download', $certificate) }}" style="width:100%;height:600px;border:1px solid var(--border)"></iframe>
  </div>
</x-layouts.admin>
