<x-layouts.admin title="Import Excel — {{ $category->name }}">
  <div class="admin-card">
    <h2 style="margin-top:0">{{ $category->name }}</h2>
    <p style="color:var(--muted);margin-top:-8px">
      Upload a historical .xlsx file from the old QR generator tool. On the next screen you'll map
      its columns to this category's fields — column headings don't need to match exactly.
    </p>

    <form method="POST" action="{{ route('admin.qr.import.upload.store', $category) }}" enctype="multipart/form-data">
      @csrf
      <div class="field">
        <label for="file">Excel file (.xlsx)</label>
        <input type="file" id="file" name="file" accept=".xlsx" required>
        @error('file')<div class="error">{{ $message }}</div>@enderror
      </div>
      <div style="display:flex;gap:10px;margin-top:20px">
        <button type="submit" class="btn btn--primary">Upload &amp; continue</button>
        <a href="{{ route('admin.qr.import.choose-category') }}" class="btn btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
</x-layouts.admin>
