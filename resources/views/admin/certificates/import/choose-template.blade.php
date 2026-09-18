<x-layouts.admin title="Import Excel">
  <p style="color:var(--muted);margin-top:-4px">
    Choose the category/template this Excel file's certificates belong to. Any status is allowed —
    historical data often belongs to an archived category.
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
        @forelse ($templates as $template)
          <tr>
            <td>{{ $template->name }}</td>
            <td><span class="badge {{ $template->status->badgeClass() }}">{{ $template->status->label() }}</span></td>
            <td>{{ $template->fields->count() }}</td>
            <td style="text-align:right">
              <a href="{{ route('admin.certificates.import.upload', $template) }}" class="btn btn--primary btn--sm">Import into this category</a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="4" style="color:var(--muted);text-align:center;padding:30px">
              No templates exist yet. Create one from the Templates page first.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</x-layouts.admin>
