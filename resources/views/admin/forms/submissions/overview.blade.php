<x-layouts.admin title="Form Submissions">
  <div class="admin-card" style="padding:0;overflow-x:auto">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Form</th>
          <th>Status</th>
          <th>Submissions</th>
          <th>Latest submission</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($forms as $form)
          <tr>
            <td><strong>{{ $form->name }}</strong></td>
            <td>{{ $form->status->label() }}</td>
            <td>{{ $form->submissions_count }}</td>
            <td>{{ $form->submissions_max_submitted_at ? \Illuminate\Support\Carbon::parse($form->submissions_max_submitted_at)->format('j M Y, g:i A') : '—' }}</td>
            <td style="text-align:right;white-space:nowrap">
              <a href="{{ route('admin.forms.submissions.index', $form) }}" class="btn btn--ghost btn--sm">View</a>
              <a href="{{ route('admin.forms.submissions.export', $form) }}" class="btn btn--ghost btn--sm">Export Excel</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" style="color:var(--muted);text-align:center;padding:30px">No forms yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div style="margin-top:16px">{{ $forms->links() }}</div>
</x-layouts.admin>
