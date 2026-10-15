<x-layouts.admin title="Upload Participants — {{ $template->name }}">
  <x-pdf-studio-stepper :template="$template" current="participants" />
  <div class="admin-card" style="max-width:860px">
    <h2 style="margin-top:0">Upload participant data — {{ $template->name }}</h2>
    <p style="color:var(--muted)">
      One Excel upload, validated and previewed here, then generated automatically in the PDF
      Studio using this template's saved design — no intermediate files to download or re-upload.
    </p>

    @if (empty($template->editor_schema['fields']))
      <div class="admin-card" style="background:var(--danger-bg, #fdecea); border-color:var(--danger, #c0392b)">
        <strong>This template has no participant columns configured yet.</strong>
        <p style="color:var(--muted)">
          Every field on this template is either unmapped or fixed (no Excel column assigned), so
          there is nothing for participants to fill in and no sample spreadsheet to generate.
          Open this template in PDF Studio and, when saving, assign an Excel column name to each
          field that should come from the participant spreadsheet (name, ID, etc.) — leave a field
          blank there only if it's meant to stay fixed on every certificate.
        </p>
        <a href="{{ route('admin.pdf-studio.show', $template) }}" class="btn btn--primary">Open in PDF Studio</a>
      </div>
    @else
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
      <span id="preview-spinner" style="display:none;color:var(--muted);margin-left:8px">Validating…</span>

      <div id="notice-area"></div>

      <div id="preview-area" style="margin-top:20px;display:none">
        <h3>Preview</h3>
        <div id="preview-summary"></div>
        <table class="admin-table" id="preview-table"><thead></thead><tbody></tbody></table>
        <button type="button" class="btn btn--primary" id="confirm-btn" style="margin-top:12px">Confirm &amp; Generate</button>
        <span id="confirm-spinner" style="display:none;color:var(--muted);margin-left:8px">Creating batch…</span>
      </div>
    @endif
  </div>

  @unless (empty($template->editor_schema['fields']))
  <script>
    const templateId = {{ $template->id }};
    const apiBase = @json(url('/admin/api/pdf-studio'));
    const csrf = @json(csrf_token());
    const studioShowUrl = @json(route('admin.pdf-studio.show', $template));
    const studioShowBatchUrl = @json(route('admin.pdf-studio.show-batch', [$template, '__BATCH__']));
    let prepareToken = null;

    // A whole-file rejection (duplicate/unknown/missing columns) means the
    // uploaded spreadsheet doesn't match this template's saved field
    // mapping -- the fix is in PDF Studio's Configure data step, not in the
    // spreadsheet alone, so point there directly instead of leaving the
    // admin to guess. Per-row problems (a blank required cell, a missing
    // photo) are the spreadsheet's own content and don't need that link.
    const MAPPING_ERROR_RE = /unknown column|missing required column|duplicate column/i;

    function notice(msg, { isError = true, linkToMapping = false } = {}) {
      const area = document.getElementById('notice-area');
      if (!msg) { area.innerHTML = ''; return; }
      const cls = isError ? 'alert alert--error' : 'alert alert--ok';
      const link = linkToMapping
        ? ` <a href="${studioShowUrl}">Open this template's field mapping in PDF Studio &rarr;</a>`
        : '';
      area.innerHTML = `<div class="${cls}" role="alert">${msg}${link}</div>`;
    }

    function setBusy(button, spinnerId, busy) {
      button.disabled = busy;
      document.getElementById(spinnerId).style.display = busy ? 'inline' : 'none';
    }

    document.getElementById('preview-btn').addEventListener('click', async () => {
      notice('');
      const participants = document.getElementById('participants').files[0];
      if (!participants) { notice('Choose a spreadsheet first.'); return; }
      const previewBtn = document.getElementById('preview-btn');
      setBusy(previewBtn, 'preview-spinner', true);
      document.getElementById('preview-area').style.display = 'none';
      try {
        const form = new FormData();
        form.set('participants', participants);
        for (const f of document.getElementById('photos').files) form.append('photos[]', f);

        const res = await fetch(`${apiBase}/templates/${templateId}/batches`, {
          method: 'POST', body: form, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, credentials: 'same-origin',
        });
        const data = await res.json();
        if (!res.ok) {
          notice(data.message || 'Could not validate the spreadsheet.', { linkToMapping: MAPPING_ERROR_RE.test(data.message || '') });
          return;
        }

        prepareToken = data.token;
        document.getElementById('preview-area').style.display = 'block';
        const okRows = data.total_rows - data.errors.length;
        const summaryCls = data.errors.length > 0 ? 'alert alert--error' : 'alert alert--ok';
        document.getElementById('preview-summary').innerHTML =
          `<div class="${summaryCls}" role="status">`
          + `${data.total_rows} row${data.total_rows === 1 ? '' : 's'} read — `
          + `${okRows} ready to generate` + (data.errors.length > 0 ? `, ${data.errors.length} need fixing before you can continue.` : '.')
          + `</div>`;

        const rowErrorsByNumber = new Map(data.rows.filter((r) => r.errors && r.errors.length > 0).map((r) => [r.row, r.errors]));
        const cols = data.columns;
        document.querySelector('#preview-table thead').innerHTML =
          '<tr><th>Row</th>' + cols.map((c) => `<th>${c}</th>`).join('') + '<th>Issues</th></tr>';
        document.querySelector('#preview-table tbody').innerHTML = data.rows.map((r) => {
          const rowErrors = rowErrorsByNumber.get(r.row) ?? [];
          const rowStyle = rowErrors.length > 0 ? ' style="background:var(--danger-bg)"' : '';
          const issues = rowErrors.length > 0
            ? `<span class="badge badge--required">${rowErrors.join('; ')}</span>`
            : '<span class="badge badge--active">OK</span>';
          return `<tr${rowStyle}><td>${r.row}</td>` + cols.map((c) => `<td>${r.values[c] ?? ''}</td>`).join('') + `<td>${issues}</td></tr>`;
        }).join('');
        document.getElementById('confirm-btn').disabled = data.errors.length > 0;
      } finally {
        setBusy(previewBtn, 'preview-spinner', false);
      }
    });

    document.getElementById('confirm-btn').addEventListener('click', async () => {
      if (!prepareToken) return;
      notice('');
      const confirmBtn = document.getElementById('confirm-btn');
      // Disabled immediately, for the whole request -- the server-side
      // idempotency key also protects against a double-submit (e.g. a
      // second tab, a retried request), but disabling here is what stops
      // an admin from creating two batches by clicking twice before the
      // first response lands.
      setBusy(confirmBtn, 'confirm-spinner', true);
      try {
        const idempotencyKey = (crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random());
        const res = await fetch(`${apiBase}/templates/${templateId}/batches/confirm`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ token: prepareToken, idempotency_key: idempotencyKey }),
        });
        const data = await res.json();
        if (!res.ok) { notice(data.message || 'Could not confirm the batch.'); setBusy(confirmBtn, 'confirm-spinner', false); return; }
        window.location.href = studioShowBatchUrl.replace('__BATCH__', data.batch_id);
        // Deliberately left disabled/busy through the redirect -- there is
        // nothing useful to click here once a batch exists server-side.
      } catch (err) {
        notice('Could not reach the server. Check your connection and try again.');
        setBusy(confirmBtn, 'confirm-spinner', false);
      }
    });
  </script>
  @endunless
</x-layouts.admin>
