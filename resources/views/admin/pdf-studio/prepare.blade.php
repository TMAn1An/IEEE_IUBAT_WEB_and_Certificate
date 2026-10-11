<x-layouts.admin title="Upload Participants — {{ $template->name }}">
  <div class="admin-card" style="max-width:860px">
    <h2 style="margin-top:0">Upload participant data — {{ $template->name }}</h2>
    <p style="color:var(--muted)">
      One Excel upload, validated and previewed here, then generated automatically in the PDF
      Studio using this template's saved design — no intermediate files to download or re-upload.
    </p>

    <h3>Expected columns</h3>
    <table class="admin-table">
      <thead><tr><th>Column</th><th>Field</th><th>Required</th><th>Type</th></tr></thead>
      <tbody>
        @foreach ($template->editor_schema['fields'] as $field)
          <tr>
            <td><code>{{ $field['column'] }}</code></td>
            <td>{{ $field['label'] }}</td>
            <td>{{ $field['required'] ? 'Required' : 'Optional' }}</td>
            <td>{{ $field['type'] === 'image' ? 'Photo filename' : 'Text' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
    <p style="color:var(--muted)">
      Certificate numbers, verification codewords and QR images are generated automatically —
      do not add columns for them. For a photo field, put the image's filename in that column and
      upload the matching image file below.
    </p>
    <a href="{{ route('admin.pdf-studio.api.templates.sample', $template) }}" class="btn btn--ghost">Download example spreadsheet</a>

    <h3>Upload</h3>
    <div class="field">
      <label for="participants">Participant spreadsheet (.xlsx)</label>
      <input type="file" id="participants" accept=".xlsx">
    </div>
    <div class="field">
      <label for="photos">Participant photos (if this template has a photo field)</label>
      <input type="file" id="photos" multiple accept="image/jpeg,image/png">
    </div>
    <button type="button" class="btn btn--primary" id="preview-btn">Validate &amp; preview</button>

    <div id="preview-area" style="margin-top:20px;display:none">
      <h3>Preview</h3>
      <p id="preview-summary"></p>
      <ul id="preview-errors" style="color:var(--danger)"></ul>
      <table class="admin-table" id="preview-table"><thead></thead><tbody></tbody></table>
      <button type="button" class="btn btn--primary" id="confirm-btn" style="margin-top:12px">Confirm &amp; Generate</button>
    </div>
    <p id="notice" style="color:var(--danger)"></p>
  </div>

  <script>
    const templateId = {{ $template->id }};
    const apiBase = @json(url('/admin/api/pdf-studio'));
    const csrf = @json(csrf_token());
    const studioShowBatchUrl = @json(route('admin.pdf-studio.show-batch', [$template, '__BATCH__']));
    let prepareToken = null;

    function notice(msg) { document.getElementById('notice').textContent = msg || ''; }

    document.getElementById('preview-btn').addEventListener('click', async () => {
      notice('');
      const participants = document.getElementById('participants').files[0];
      if (!participants) { notice('Choose a spreadsheet first.'); return; }
      const form = new FormData();
      form.set('participants', participants);
      for (const f of document.getElementById('photos').files) form.append('photos[]', f);

      const res = await fetch(`${apiBase}/templates/${templateId}/batches`, {
        method: 'POST', body: form, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, credentials: 'same-origin',
      });
      const data = await res.json();
      if (!res.ok) { notice(data.message || 'Could not validate the spreadsheet.'); return; }

      prepareToken = data.token;
      document.getElementById('preview-area').style.display = 'block';
      document.getElementById('preview-summary').textContent = `${data.total_rows} row(s). ${data.errors.length} error(s).`;
      document.getElementById('preview-errors').innerHTML = data.errors.map((e) => `<li>${e}</li>`).join('');
      const cols = data.columns;
      document.querySelector('#preview-table thead').innerHTML = '<tr>' + cols.map((c) => `<th>${c}</th>`).join('') + '</tr>';
      document.querySelector('#preview-table tbody').innerHTML = data.rows.map((r) =>
        '<tr>' + cols.map((c) => `<td>${r.values[c] ?? ''}</td>`).join('') + '</tr>'
      ).join('');
      document.getElementById('confirm-btn').disabled = data.errors.length > 0;
    });

    document.getElementById('confirm-btn').addEventListener('click', async () => {
      if (!prepareToken) return;
      notice('');
      const idempotencyKey = (crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random());
      const res = await fetch(`${apiBase}/templates/${templateId}/batches/confirm`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ token: prepareToken, idempotency_key: idempotencyKey }),
      });
      const data = await res.json();
      if (!res.ok) { notice(data.message || 'Could not confirm the batch.'); return; }
      window.location.href = studioShowBatchUrl.replace('__BATCH__', data.batch_id);
    });
  </script>
</x-layouts.admin>
