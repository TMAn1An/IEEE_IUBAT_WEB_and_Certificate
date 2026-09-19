<x-layouts.admin title="Deleted Certificates">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px;flex-wrap:wrap">
    <p style="color:var(--muted);margin:0">
      Read-only. Certificates here were soft-deleted via an approved deletion request — see the
      <a href="{{ route('admin.logbook.index') }}">Logbook</a> for the full history of each one.
    </p>
    <a href="{{ route('admin.certificates.index') }}" class="btn btn--ghost btn--sm">&larr; Active certificates</a>
  </div>

  <div class="admin-card" style="padding:0">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Certificate No.</th>
          <th>Recipient</th>
          <th>Template</th>
          <th>Deleted at</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($certificates as $certificate)
          <tr>
            <td><code style="font-size:.8em">{{ $certificate->certificate_number }}</code></td>
            <td>{{ $certificate->recipient_name }}</td>
            <td>{{ $certificate->template?->name }}</td>
            <td>{{ $certificate->deleted_at->format('j M Y, g:i A') }}</td>
            <td style="text-align:right"><a href="{{ route('admin.certificates.show', $certificate) }}" class="btn btn--ghost btn--sm">View</a></td>
          </tr>
        @empty
          <tr>
            <td colspan="5" style="color:var(--muted);text-align:center;padding:30px">No deleted certificates.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:16px">
    {{ $certificates->links() }}
  </div>
</x-layouts.admin>
