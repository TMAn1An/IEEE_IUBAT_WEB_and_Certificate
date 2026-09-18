<x-layouts.admin title="Issue Certificate">
  <p style="color:var(--muted);margin-top:-4px">Choose an active template to issue a certificate from.</p>

  <div class="admin-card" style="padding:0">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Fields</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($templates as $template)
          <tr>
            <td>{{ $template->name }}</td>
            <td>{{ $template->fields->count() }}</td>
            <td style="text-align:right">
              <a href="{{ route('admin.certificates.create', $template) }}" class="btn btn--primary btn--sm">Issue from this template</a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="3" style="color:var(--muted);text-align:center;padding:30px">
              No active templates. Activate a template first from the Templates page.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</x-layouts.admin>
