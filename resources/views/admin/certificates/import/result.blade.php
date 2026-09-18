<x-layouts.admin title="Import Complete — {{ $template->name }}">
  <div class="admin-card">
    <h2 style="margin-top:0">Import complete</h2>

    <table class="admin-table">
      <tbody>
        <tr><th style="width:260px">Imported</th><td>{{ $summary->imported }}</td></tr>
        <tr><th>Skipped</th><td>{{ $skippedTotal }}</td></tr>
        <tr><th>Certificates created</th><td>{{ $summary->imported }}</td></tr>
        <tr><th>Codewords preserved</th><td>{{ $summary->codewordsPreserved }}</td></tr>
        <tr><th>New codewords generated</th><td>{{ $summary->newCodewordsGenerated }}</td></tr>
        <tr><th>Certificate numbers preserved</th><td>{{ $summary->certificateNumbersPreserved }}</td></tr>
        <tr><th>New certificate numbers generated</th><td>{{ $summary->newCertificateNumbersGenerated }}</td></tr>
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
      <a href="{{ route('admin.certificates.index') }}" class="btn btn--primary">View all certificates</a>
      <a href="{{ route('admin.certificates.import.choose-template') }}" class="btn btn--ghost">Import another file</a>
    </div>
  </div>
</x-layouts.admin>
