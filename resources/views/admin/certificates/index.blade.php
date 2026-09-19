<x-layouts.admin title="Certificates">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px;flex-wrap:wrap">
    <form method="GET" action="{{ route('admin.certificates.index') }}" style="display:flex;gap:8px">
      <input type="search" name="search" value="{{ $search }}" placeholder="Search by certificate number, recipient name, or codeword">
      <button type="submit" class="btn btn--ghost">Search</button>
      @if ($search !== '')
        <a href="{{ route('admin.certificates.index') }}" class="btn btn--ghost">Clear</a>
      @endif
    </form>
    <div style="display:flex;gap:8px">
      @can('viewDeleted', \App\Models\Certificate::class)
        <a href="{{ route('admin.certificates.deleted') }}" class="btn btn--ghost">Deleted Certificates</a>
      @endcan
      <a href="{{ route('admin.certificates.choose-template') }}" class="btn btn--primary">Issue certificate</a>
    </div>
  </div>

  <div class="admin-card" style="padding:0">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Certificate number</th>
          <th>Recipient</th>
          <th>Category</th>
          <th>Codeword</th>
          <th>Created</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($certificates as $certificate)
          <tr>
            <td><code>{{ $certificate->certificate_number }}</code></td>
            <td>{{ $certificate->recipient_name }}</td>
            <td>{{ $certificate->template->name }}</td>
            <td><code style="font-size:.8em">{{ \Illuminate\Support\Str::limit($certificate->codeword, 12, '…') }}</code></td>
            <td>{{ $certificate->issued_at?->format('j M Y') }}</td>
            <td><span class="badge {{ $certificate->status->badgeClass() }}">{{ $certificate->status->label() }}</span></td>
            <td style="text-align:right;white-space:nowrap">
              <a href="{{ route('admin.certificates.show', $certificate) }}" class="btn btn--ghost btn--sm">View</a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" style="color:var(--muted);text-align:center;padding:30px">
              @if ($search !== '')
                No certificates match "{{ $search }}".
              @else
                No certificates yet. <a href="{{ route('admin.certificates.choose-template') }}">Issue the first one</a>.
              @endif
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:16px">
    {{ $certificates->links() }}
  </div>
</x-layouts.admin>
