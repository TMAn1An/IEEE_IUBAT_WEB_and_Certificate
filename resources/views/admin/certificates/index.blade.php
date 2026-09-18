<x-layouts.admin title="Certificates">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px">
    <form method="GET" action="{{ route('admin.certificates.index') }}" style="display:flex;gap:8px">
      <input type="search" name="search" value="{{ $search }}" placeholder="Search by certificate number or recipient name">
      <button type="submit" class="btn btn--ghost">Search</button>
      @if ($search !== '')
        <a href="{{ route('admin.certificates.index') }}" class="btn btn--ghost">Clear</a>
      @endif
    </form>
    <a href="{{ route('admin.certificates.choose-template') }}" class="btn btn--primary">Issue certificate</a>
  </div>

  <div class="admin-card" style="padding:0">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Certificate number</th>
          <th>Recipient</th>
          <th>Template</th>
          <th>Issued</th>
          <th>Issued by</th>
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
            <td>{{ $certificate->issued_at?->format('j M Y') }}</td>
            <td>{{ $certificate->creator?->name }}</td>
            <td><span class="badge {{ $certificate->status->badgeClass() }}">{{ $certificate->status->label() }}</span></td>
            <td style="text-align:right">
              <a href="{{ route('admin.certificates.show', $certificate) }}" class="btn btn--ghost btn--sm">View</a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" style="color:var(--muted);text-align:center;padding:30px">
              @if ($search !== '')
                No certificates match "{{ $search }}".
              @else
                No certificates issued yet. <a href="{{ route('admin.certificates.choose-template') }}">Issue the first one</a>.
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
