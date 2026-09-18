<x-layouts.admin title="Import Excel — Choose QR Group">
  <p style="color:var(--muted);margin-top:-4px">
    Choose which QR group this Excel file's records belong to. A group is an automatically
    created Event Type + Event Name + Role combination — the same grouping "Generate QR" creates
    automatically. If the group you need doesn't exist yet, create it below.
  </p>

  <div class="admin-card" style="padding:0">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Event Type</th>
          <th>Event Name</th>
          <th>Role</th>
          <th>Records</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($groups as $group)
          <tr>
            <td>{{ $group->event_type }}</td>
            <td>{{ $group->event_name }}</td>
            <td>{{ $group->role }}</td>
            <td>{{ $group->certificates_count }}</td>
            <td style="text-align:right">
              <a href="{{ route('admin.qr.import.upload', $group) }}" class="btn btn--primary btn--sm">Import into this group</a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" style="color:var(--muted);text-align:center;padding:30px">
              No QR groups exist yet. Generate a QR from the <a href="{{ route('admin.qr.generate.show') }}">Generate QR</a>
              page first, or create one below.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="admin-card" style="margin-top:20px">
    <h2 style="margin-top:0">Create a new group</h2>
    <p style="color:var(--muted);margin-top:-8px">
      Only use this if the group you need doesn't already exist above — it will be reused
      automatically instead of duplicated if it does.
    </p>
    <form method="POST" action="{{ route('admin.qr.import.create-group') }}">
      @csrf
      <div class="field">
        <label for="event_type">Event Type</label>
        <input id="event_type" name="event_type" type="text" placeholder="Conference" value="{{ old('event_type') }}">
      </div>
      <div class="field">
        <label for="event_name">Event Name</label>
        <input id="event_name" name="event_name" type="text" placeholder="IEEE BECITHCON 2026" value="{{ old('event_name') }}">
      </div>
      <div class="field">
        <label for="role">Role</label>
        <input id="role" name="role" type="text" required placeholder="Session Chair" value="{{ old('role') }}">
        @error('role')<div class="error">{{ $message }}</div>@enderror
      </div>
      <button type="submit" class="btn btn--primary" style="margin-top:12px">Create &amp; continue</button>
    </form>
  </div>
</x-layouts.admin>
