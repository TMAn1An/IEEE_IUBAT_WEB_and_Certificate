<x-layouts.admin title="QR Groups">
  <p style="color:var(--muted);margin-top:-4px">
    Groups are created automatically from Event Type + Event Name + Role whenever a QR is
    generated or a historical file is imported — the database equivalent of the old tool's
    per-combination Excel files. Nothing needs to be created here before generating a QR.
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
            <td style="text-align:right;white-space:nowrap">
              <a href="{{ route('admin.qr.groups.show', $group) }}" class="btn btn--ghost btn--sm">View Records</a>
              <a href="{{ route('admin.qr.import.upload', $group) }}" class="btn btn--ghost btn--sm">Import Excel</a>
              <a href="{{ route('admin.qr.groups.export', $group) }}" class="btn btn--ghost btn--sm">Download Excel</a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" style="color:var(--muted);text-align:center;padding:30px">
              No groups yet. <a href="{{ route('admin.qr.generate.show') }}">Generate the first QR</a> to create one automatically.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</x-layouts.admin>
