<x-layouts.admin title="Deleted QR Records">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px;flex-wrap:wrap">
    <p style="color:var(--muted);margin:0">
      Read-only. Records here were soft-deleted via an approved deletion request — see the
      <a href="{{ route('admin.logbook.index') }}">Logbook</a> for the full history of each one.
    </p>
    <a href="{{ route('admin.qr.records.index') }}" class="btn btn--ghost btn--sm">&larr; Active records</a>
  </div>

  <div class="admin-card" style="padding:0">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Recipient</th>
          <th>Category</th>
          <th>Codeword</th>
          <th>Deleted at</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($records as $record)
          <tr>
            <td>{{ $record->recipient_name }}</td>
            <td>{{ $record->category?->name }}</td>
            <td><code style="font-size:.8em">{{ $record->codeword }}</code></td>
            <td>{{ $record->deleted_at->format('j M Y, g:i A') }}</td>
            <td style="text-align:right"><a href="{{ route('admin.qr.records.show', $record) }}" class="btn btn--ghost btn--sm">View</a></td>
          </tr>
        @empty
          <tr>
            <td colspan="5" style="color:var(--muted);text-align:center;padding:30px">No deleted records.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:16px">
    {{ $records->links() }}
  </div>
</x-layouts.admin>
