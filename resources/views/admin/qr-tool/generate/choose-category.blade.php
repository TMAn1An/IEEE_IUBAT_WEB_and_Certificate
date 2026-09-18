<x-layouts.admin title="Generate QR">
  <p style="color:var(--muted);margin-top:-4px">Choose a category to generate a QR record for. No advanced certificate template is required.</p>

  <div class="admin-card" style="padding:0">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Event/Conference</th>
          <th>Fields</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($categories as $category)
          <tr>
            <td>{{ $category->name }}</td>
            <td>{{ $category->event_name }}</td>
            <td>{{ $category->fields->count() }}</td>
            <td style="text-align:right">
              <a href="{{ route('admin.qr.generate.create', $category) }}" class="btn btn--primary btn--sm">Generate QR</a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="4" style="color:var(--muted);text-align:center;padding:30px">
              No active QR categories. <a href="{{ route('admin.qr.categories.create') }}">Create one</a> first.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</x-layouts.admin>
