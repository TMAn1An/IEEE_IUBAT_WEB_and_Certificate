<x-layouts.admin title="Batches">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <h2 style="margin:0">Bulk-generation batches</h2>
    <a href="{{ route('admin.bulk-generation.index') }}" class="btn btn--primary">Reserve a batch</a>
  </div>

  <div class="admin-card" style="padding:0">
    <table class="admin-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Template</th>
          <th>Rows</th>
          <th>Status</th>
          <th>Created</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($batches as $batch)
          <tr>
            <td>{{ $batch->id }}</td>
            <td>{{ $batch->template->name }}</td>
            <td>{{ $batch->successful_rows }} / {{ $batch->total_rows }} reserved, {{ $batch->failed_rows }} failed</td>
            <td><span class="badge">{{ $batch->status->label() }}</span></td>
            <td>{{ $batch->created_at->format('j M Y, H:i') }}</td>
            <td style="text-align:right">
              <a href="{{ route('admin.batches.show', $batch) }}" class="btn btn--ghost">View</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" style="color:var(--muted);text-align:center;padding:24px">No batches yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{ $batches->links() }}
</x-layouts.admin>
