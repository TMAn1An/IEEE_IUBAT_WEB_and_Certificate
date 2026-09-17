<x-layouts.admin title="Certificate Templates">
  <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
    <a href="{{ route('admin.templates.create') }}" class="btn btn--primary">New template</a>
  </div>

  <div class="admin-card" style="padding:0">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Slug</th>
          <th>Status</th>
          <th>Fields</th>
          <th>Updated</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($templates as $template)
          <tr>
            <td>{{ $template->name }}</td>
            <td><code>{{ $template->slug }}</code></td>
            <td><span class="badge {{ $template->status->badgeClass() }}">{{ $template->status->label() }}</span></td>
            <td>{{ $template->fields_count }}</td>
            <td>{{ $template->updated_at->format('j M Y') }}</td>
            <td style="text-align:right">
              <a href="{{ route('admin.templates.edit', $template) }}" class="btn btn--ghost btn--sm">Manage</a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" style="color:var(--muted);text-align:center;padding:30px">
              No templates yet. <a href="{{ route('admin.templates.create') }}">Create the first one</a>.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</x-layouts.admin>
