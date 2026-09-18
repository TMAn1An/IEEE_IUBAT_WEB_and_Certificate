<x-layouts.admin title="Import Excel">
  <p style="color:var(--muted);margin-top:-4px">
    Choose the QR category this Excel file's records belong to. Any status is allowed — historical
    data often belongs to an inactive category.
  </p>

  <div class="admin-card" style="padding:0">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Status</th>
          <th>Fields</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($categories as $category)
          <tr>
            <td>{{ $category->name }}</td>
            <td><span class="badge {{ $category->is_active ? 'badge--active' : 'badge--archived' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></td>
            <td>{{ $category->fields->count() }}</td>
            <td style="text-align:right">
              <a href="{{ route('admin.qr.import.upload', $category) }}" class="btn btn--primary btn--sm">Import into this category</a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="4" style="color:var(--muted);text-align:center;padding:30px">
              No QR categories exist yet. <a href="{{ route('admin.qr.categories.create') }}">Create one</a> first.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</x-layouts.admin>
