<x-layouts.admin title="QR Categories">
  <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
    <a href="{{ route('admin.qr.categories.create') }}" class="btn btn--primary">New QR category</a>
  </div>

  <div class="admin-card" style="padding:0">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Event/Conference</th>
          <th>Status</th>
          <th>Fields</th>
          <th>Updated</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($categories as $category)
          <tr>
            <td>{{ $category->name }}</td>
            <td>{{ $category->event_name }}</td>
            <td><span class="badge {{ $category->is_active ? 'badge--active' : 'badge--archived' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></td>
            <td>{{ $category->fields_count }}</td>
            <td>{{ $category->updated_at->format('j M Y') }}</td>
            <td style="text-align:right">
              <a href="{{ route('admin.qr.categories.edit', $category) }}" class="btn btn--ghost btn--sm">Manage</a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" style="color:var(--muted);text-align:center;padding:30px">
              No QR categories yet. <a href="{{ route('admin.qr.categories.create') }}">Create the first one</a>.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</x-layouts.admin>
