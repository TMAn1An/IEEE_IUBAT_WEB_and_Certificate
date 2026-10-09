<x-layouts.admin title="Bulk Generation">
  <div class="admin-card" style="max-width:640px">
    <h2>Reserve a batch (PDF Editor Bridge)</h2>
    <p style="color:var(--muted)">
      Pick an active certificate template and upload a recipients spreadsheet. This mints a
      certificate number, verification codeword and QR code for every valid row — then hands you
      back an augmented spreadsheet and a ZIP of QR images to load into the
      <strong>PDF Template Studio</strong> editor (a separate tool). No PDF is generated here.
    </p>

    @if ($errors->any())
      <div class="alert alert--error">
        <ul style="margin:0;padding-left:18px">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('admin.bulk-generation.store') }}" enctype="multipart/form-data">
      @csrf

      <div class="field">
        <label for="certificate_template_id">Certificate template</label>
        <select name="certificate_template_id" id="certificate_template_id" required>
          <option value="">Choose a template…</option>
          @foreach ($templates as $template)
            <option value="{{ $template->id }}" @selected((string) old('certificate_template_id') === (string) $template->id)>
              {{ $template->name }}
            </option>
          @endforeach
        </select>
        @if ($templates->isEmpty())
          <p style="color:var(--muted)">No active templates yet — create and activate one first.</p>
        @endif
      </div>

      <div class="field">
        <label for="recipients">Recipients spreadsheet (.xlsx)</label>
        <input type="file" name="recipients" id="recipients" accept=".xlsx" required>
        <p style="color:var(--muted)">
          Column headers must exactly match this template's field keys (e.g. <code>name</code>,
          <code>paper_title</code>). One row per certificate.
        </p>
      </div>

      <button type="submit" class="btn btn--primary">Reserve batch</button>
    </form>
  </div>
</x-layouts.admin>
