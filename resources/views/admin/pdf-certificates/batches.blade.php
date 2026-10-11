<x-layouts.admin :title="'Batches — '.$template->name">
  <div style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center">
    <a href="{{ route('admin.pdf-certificates.index') }}" class="btn btn--ghost btn--sm">&larr; PDF Certificates</a>
    <a href="{{ route('admin.pdf-studio.prepare', $template) }}" class="btn btn--primary">Upload participants &rarr;</a>
  </div>

  <h1 style="font-size:1.1rem">Batches — {{ $template->name }}</h1>

  @if ($batches->isEmpty())
    <div class="admin-card" style="text-align:center;padding:40px 24px">
      <p style="color:var(--muted);margin-bottom:16px">No batches yet for this template.</p>
      <a href="{{ route('admin.pdf-studio.prepare', $template) }}" class="btn btn--primary">Upload participants &rarr;</a>
    </div>
  @else
    <div class="admin-card" style="padding:0">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Started</th>
            <th>Status</th>
            <th>Rows</th>
            <th>Finalized</th>
            <th>Failed</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($batches as $batch)
            <tr>
              <td>{{ $batch->created_at->format('j M Y, H:i') }}</td>
              <td><span class="badge">{{ $batch->status->label() }}</span></td>
              <td>{{ $batch->total_rows }}</td>
              <td>{{ $batch->successful_rows }}</td>
              <td>{{ $batch->failed_rows }}</td>
              <td style="text-align:right;white-space:nowrap">
                <a href="{{ route('admin.pdf-studio.show-batch', [$template, $batch]) }}" class="btn btn--primary btn--sm">Resume / Review</a>
                <a href="{{ route('admin.pdf-studio.api.batches.download', $batch) }}" class="btn btn--ghost btn--sm">Download ZIP</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</x-layouts.admin>
