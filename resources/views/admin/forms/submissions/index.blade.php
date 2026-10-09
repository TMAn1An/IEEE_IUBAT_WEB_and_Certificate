<x-layouts.admin :title="'Submissions — '.$form->name" wide>
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px">
    <a href="{{ route('admin.forms.index') }}">&larr; All forms</a>
    <div style="display:flex;gap:8px">
      <a href="{{ route('admin.forms.edit', $form) }}" class="btn btn--ghost btn--sm">Open builder</a>
      <a href="{{ route('admin.forms.submissions.export', $form) }}" class="btn btn--primary btn--sm">Export Excel</a>
    </div>
  </div>

  <p style="color:var(--muted);margin-top:0">
    {{ $submissions->total() }} {{ \Illuminate\Support\Str::plural('submission', $submissions->total()) }}.
    The table shows a few summary fields
    (tick &ldquo;Show in submissions list&rdquo; on a field in the builder to choose them); open a submission for every value.
  </p>

  <div class="admin-card" style="padding:0;overflow-x:auto">
    <table class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Submitted at</th>
          @foreach ($summaryFields as $field)
            <th>{{ $field->label }}</th>
          @endforeach
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($rows as $row)
          <tr>
            <td>#{{ $row['submission']->id }}</td>
            <td style="white-space:nowrap">{{ $row['submission']->submitted_at->format('j M Y, g:i A') }}</td>
            @foreach ($row['cells'] as $cell)
              <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis">{{ \Illuminate\Support\Str::limit((string) $cell, 80) ?: '—' }}</td>
            @endforeach
            <td style="text-align:right">
              <a href="{{ route('admin.forms.submissions.show', [$form, $row['submission']]) }}" class="btn btn--ghost btn--sm">View</a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="{{ 3 + $summaryFields->count() }}" style="color:var(--muted);text-align:center;padding:30px">No submissions yet.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:16px">{{ $submissions->links() }}</div>
</x-layouts.admin>
