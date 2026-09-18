<x-layouts.admin title="Group — {{ $group->event_type }} / {{ $group->event_name }} / {{ $group->role }}">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px;flex-wrap:wrap">
    <div>
      <h2 style="margin:0">{{ $group->event_type }} / {{ $group->event_name }} / {{ $group->role }}</h2>
      <p style="color:var(--muted);margin:4px 0 0">{{ $records->total() }} record(s) in this group.</p>
    </div>
    <div style="display:flex;gap:8px">
      <a href="{{ route('admin.qr.import.upload', $group) }}" class="btn btn--ghost">Import Excel</a>
      <a href="{{ route('admin.qr.groups.export', $group) }}" class="btn btn--primary">Download Excel</a>
    </div>
  </div>

  <div class="admin-card" style="padding:0">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Recipient</th>
          <th>Session</th>
          <th>Codeword</th>
          <th>Created</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($records as $record)
          <tr>
            <td>{{ $record->recipient_name }}</td>
            <td>{{ $record->data['session'] ?? '' }}</td>
            <td><code style="font-size:.8em">{{ $record->codeword }}</code></td>
            <td>{{ $record->created_at->format('j M Y') }}</td>
            <td><span class="badge {{ $record->status->badgeClass() }}">{{ $record->status->label() }}</span></td>
            <td style="text-align:right"><a href="{{ route('admin.qr.records.show', $record) }}" class="btn btn--ghost btn--sm">View</a></td>
          </tr>
        @empty
          <tr>
            <td colspan="6" style="color:var(--muted);text-align:center;padding:30px">No records in this group yet.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:16px">
    {{ $records->links() }}
  </div>
</x-layouts.admin>
