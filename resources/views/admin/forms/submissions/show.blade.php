<x-layouts.admin :title="'Submission #'.$submission->id.' — '.$form->name">
  <p><a href="{{ route('admin.forms.submissions.index', $form) }}">&larr; All submissions for {{ $form->name }}</a></p>

  <div class="admin-card">
    <dl class="fs-meta">
      <dt>Submitted at</dt><dd>{{ $submission->submitted_at->format('j M Y, g:i:s A') }}</dd>
      <dt>Submitted by</dt><dd>{{ $submission->submitter?->name ?? 'Public visitor' }}</dd>
      <dt>Form version</dt><dd>{{ $submission->form_version ?? '—' }}</dd>
      @if (! empty($submission->metadata['ip']))
        <dt>IP address</dt><dd>{{ $submission->metadata['ip'] }}</dd>
      @endif
    </dl>
  </div>

  <div class="admin-card" style="padding:0">
    <table class="admin-table">
      <thead>
        <tr><th style="width:34%">Field (as submitted)</th><th>Value</th></tr>
      </thead>
      <tbody>
        @forelse ($submission->values as $value)
          @php $current = $value->form_field_id ? $currentFields->get($value->form_field_id) : null; @endphp
          <tr>
            <td>
              <strong>{{ $value->field_label_snapshot }}</strong>
              <br><code style="font-size:.75em;color:var(--muted)">{{ $value->field_key }}</code>
              @if ($current && $current->label !== $value->field_label_snapshot)
                <br><span style="font-size:.78em;color:var(--muted)">Now labelled &ldquo;{{ $current->label }}&rdquo;</span>
              @endif
              @if (! $current)
                <br><span class="badge badge--archived">field removed</span>
              @elseif (! $current->is_active)
                <br><span class="badge badge--archived">field archived</span>
              @endif
            </td>
            <td style="white-space:pre-wrap;word-break:break-word">{{ $value->display_value ?? '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="2" style="color:var(--muted);text-align:center;padding:30px">This submission has no stored values.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <style>
    .fs-meta { display: grid; grid-template-columns: max-content 1fr; gap: 6px 18px; margin: 0; }
    .fs-meta dt { color: var(--muted); font-size: .85rem; }
    .fs-meta dd { margin: 0; }
  </style>
</x-layouts.admin>
