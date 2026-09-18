<x-layouts.admin title="Import Complete">
  <div class="admin-card">
    <h2 style="margin-top:0">Import complete</h2>
    <p style="color:var(--muted);margin-top:-8px">Group: {{ $group->event_type }} / {{ $group->event_name }} / {{ $group->role }}</p>

    <table class="admin-table">
      <tbody>
        <tr><th style="width:260px">Imported</th><td>{{ $summary->imported }}</td></tr>
        <tr><th>Skipped</th><td>{{ $skippedTotal }}</td></tr>
        <tr><th>Records created</th><td>{{ $summary->imported }}</td></tr>
        <tr><th>Codewords preserved</th><td>{{ $summary->codewordsPreserved }}</td></tr>
        <tr><th>New codewords generated</th><td>{{ $summary->newCodewordsGenerated }}</td></tr>
      </tbody>
    </table>

    @if ($summary->failedRowMessages !== [])
      <h3 style="font-size:1rem">Rows that failed during import</h3>
      <ul>
        @foreach ($summary->failedRowMessages as $message)
          <li>{{ $message }}</li>
        @endforeach
      </ul>
    @endif

    <div style="display:flex;gap:10px;margin-top:20px">
      <a href="{{ route('admin.qr.groups.show', $group) }}" class="btn btn--primary">View this group</a>
      <a href="{{ route('admin.qr.import.choose-group') }}" class="btn btn--ghost">Import another file</a>
    </div>
  </div>
</x-layouts.admin>
