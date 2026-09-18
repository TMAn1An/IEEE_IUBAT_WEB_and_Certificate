<x-layouts.admin title="Generate QR">
  <p style="color:var(--muted);margin-top:-4px">Choose a category/template to generate a certificate record, codeword, and QR code for.</p>

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
              <a href="{{ route('admin.certificates.qr.create', $template) }}" class="btn btn--primary btn--sm">Generate QR</a>
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
