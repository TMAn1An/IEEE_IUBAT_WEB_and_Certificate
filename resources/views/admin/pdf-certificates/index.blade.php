<x-layouts.admin title="PDF Certificates">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <p style="color:var(--muted);margin:0">Design a certificate once in the PDF editor, then generate a whole batch from a spreadsheet.</p>
    <a href="{{ route('admin.pdf-certificates.create') }}" class="btn btn--primary">New template</a>
  </div>

  @if ($templates->isEmpty())
    <div class="admin-card" style="text-align:center;padding:48px 24px">
      <h2 style="margin-top:0">No templates yet</h2>
      <p style="color:var(--muted);max-width:460px;margin:0 auto 20px">
        Upload a demo certificate PDF and give it a name — you'll land straight in the editor to
        place the recipient name, any other fields, and the QR code.
      </p>
      <a href="{{ route('admin.pdf-certificates.create') }}" class="btn btn--primary">New template</a>
    </div>
  @else
    <div class="admin-card" style="padding:0">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Status</th>
            <th>Batches</th>
            <th>Updated</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($templates as $template)
            <tr>
              <td>{{ $template->name }}</td>
              <td>
                @if ($template->hasEditorProject())
                  <span class="badge badge--active">Design saved</span>
                @else
                  <span class="badge badge--draft">Design not saved yet</span>
                @endif
              </td>
              <td>{{ $template->batches_count }}</td>
              <td>{{ $template->updated_at->format('j M Y') }}</td>
              <td style="text-align:right;white-space:nowrap">
                <a href="{{ route('admin.pdf-studio.show', $template) }}" class="btn btn--primary btn--sm">Open editor</a>
                <a href="{{ route('admin.pdf-certificates.batches', $template) }}" class="btn btn--ghost btn--sm">Batches</a>
                <a href="{{ route('admin.pdf-certificates.edit', $template) }}" class="btn btn--ghost btn--sm">Rename</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</x-layouts.admin>
