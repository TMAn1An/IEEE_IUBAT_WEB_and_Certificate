<x-layouts.admin title="QR Records">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px;flex-wrap:wrap">
    <form method="GET" action="{{ route('admin.qr.records.index') }}" style="display:flex;gap:8px">
      <input type="search" name="search" value="{{ $search }}" placeholder="Search by recipient, role, session, or codeword">
      <button type="submit" class="btn btn--ghost">Search</button>
      @if ($search !== '')
        <a href="{{ route('admin.qr.records.index') }}" class="btn btn--ghost">Clear</a>
      @endif
    </form>
    <div style="display:flex;gap:8px">
      <a href="{{ route('admin.qr.import.choose-category') }}" class="btn btn--ghost">Import Excel</a>
      <a href="{{ route('admin.qr.generate.choose-category') }}" class="btn btn--primary">Generate QR</a>
    </div>
  </div>

  <div class="admin-card" style="padding:0">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Recipient</th>
          <th>Category</th>
          <th>Event/Conference</th>
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
            <td>{{ $record->category->name }}</td>
            <td>{{ $record->event_name }}</td>
            <td><code style="font-size:.8em">{{ $record->codeword }}</code></td>
            <td>{{ $record->created_at->format('j M Y') }}</td>
            <td><span class="badge {{ $record->status->badgeClass() }}">{{ $record->status->label() }}</span></td>
            <td style="text-align:right;white-space:nowrap">
              <a href="{{ route('admin.qr.records.show', $record) }}" class="btn btn--ghost btn--sm">View</a>
              <a href="{{ route('admin.qr.records.qr-image', $record) }}" download="{{ $record->codeword }}-qr.png" class="btn btn--ghost btn--sm">Download QR</a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" style="color:var(--muted);text-align:center;padding:30px">
              @if ($search !== '')
                No records match "{{ $search }}".
              @else
                No records yet. <a href="{{ route('admin.qr.generate.choose-category') }}">Generate the first one</a>.
              @endif
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:16px">
    {{ $records->links() }}
  </div>
</x-layouts.admin>
