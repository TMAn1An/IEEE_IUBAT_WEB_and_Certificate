<x-layouts.admin title="Import Excel — {{ $template->name }}">
  <div class="admin-card">
    <h2 style="margin-top:0">{{ $template->name }}</h2>
    <p style="color:var(--muted);margin-top:-8px">
      Upload a historical .xlsx file. On the next screen you'll map its columns to this category's
      fields — column headings don't need to match exactly.
    </p>

    <form method="POST" action="{{ route('admin.certificates.import.upload.store', $template) }}" enctype="multipart/form-data">
      @csrf
      <div class="field">
        <label for="file">Excel file (.xlsx)</label>
        <input type="file" id="file" name="file" accept=".xlsx" required>
        @error('file')<div class="error">{{ $message }}</div>@enderror
      </div>
      <div style="display:flex;gap:10px;margin-top:20px">
        <button type="submit" class="btn btn--primary">Upload &amp; continue</button>
        <a href="{{ route('admin.certificates.import.choose-template') }}" class="btn btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
</x-layouts.admin>
