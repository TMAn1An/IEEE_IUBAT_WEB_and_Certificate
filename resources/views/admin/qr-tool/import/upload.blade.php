<x-layouts.admin title="Import Excel — {{ $group->event_type }} / {{ $group->event_name }} / {{ $group->role }}">
  <div class="admin-card">
    <h2 style="margin-top:0">Destination group</h2>
    <table class="admin-table" style="margin-bottom:20px">
      <tbody>
        <tr><th style="width:160px">Event Type</th><td>{{ $group->event_type }}</td></tr>
        <tr><th>Event Name</th><td>{{ $group->event_name }}</td></tr>
        <tr><th>Role</th><td>{{ $group->role }}</td></tr>
      </tbody>
    </table>
    <p style="color:var(--muted);margin-top:-8px">
      Upload a historical .xlsx file from the old QR generator tool. Every imported row will
      belong to this group — Event Type, Event Name and Role come from the group above, not from
      the file, even if the file has its own Conference/Role columns.
    </p>

    <form method="POST" action="{{ route('admin.qr.import.upload.store', $group) }}" enctype="multipart/form-data">
      @csrf
      <div class="field">
        <label for="file">Excel file (.xlsx)</label>
        <input type="file" id="file" name="file" accept=".xlsx" required>
        @error('file')<div class="error">{{ $message }}</div>@enderror
      </div>
      <div style="display:flex;gap:10px;margin-top:20px">
        <button type="submit" class="btn btn--primary">Upload &amp; continue</button>
        <a href="{{ route('admin.qr.import.choose-group') }}" class="btn btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
</x-layouts.admin>
