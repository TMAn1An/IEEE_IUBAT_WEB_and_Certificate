@php
  use App\Enums\FormStatus;
  $badge = fn (FormStatus $s) => match ($s) {
      FormStatus::Active => 'badge--active',
      FormStatus::Draft => 'badge--draft',
      FormStatus::Inactive => 'badge--inactive',
      FormStatus::Archived => 'badge--archived',
  };
@endphp
<x-layouts.admin title="Forms" wide>
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px">
    <p style="color:var(--muted);margin:0">
      General-purpose forms built in the Form Builder. Published forms are public at <code>/forms/&lt;slug&gt;</code>.
      @if ($showArchived)
        <a href="{{ route('admin.forms.index') }}">Hide archived</a>
      @else
        <a href="{{ route('admin.forms.index', ['archived' => 1]) }}">Show archived</a>
      @endif
    </p>
    @can('create', \App\Models\Form::class)
      <a href="{{ route('admin.forms.create') }}" class="btn btn--primary">Create form</a>
    @endcan
  </div>

  <div class="admin-card" style="padding:0;overflow-x:auto">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Form</th>
          <th>Status</th>
          <th>Submissions</th>
          <th>Updated</th>
          <th style="text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($forms as $form)
          <tr>
            <td>
              <strong>{{ $form->name }}</strong><br>
              <code style="font-size:.78em;color:var(--muted)">/forms/{{ $form->slug }}</code>
            </td>
            <td><span class="badge {{ $badge($form->status) }}">{{ $form->status->label() }}</span></td>
            <td>{{ $form->submissions_count }}</td>
            <td style="white-space:nowrap">{{ $form->updated_at->format('j M Y, g:i A') }}</td>
            <td>
              <div class="fb-row-actions">
                @if ($form->status->isEditable())
                  <a href="{{ route('admin.forms.edit', $form) }}" class="btn btn--primary btn--sm">Edit</a>
                @else
                  <a href="{{ route('admin.forms.edit', $form) }}" class="btn btn--ghost btn--sm">View</a>
                @endif
                <a href="{{ route('admin.forms.preview', $form) }}" class="btn btn--ghost btn--sm" target="_blank" rel="noopener">Preview</a>
                <a href="{{ route('admin.forms.submissions.index', $form) }}" class="btn btn--ghost btn--sm">Submissions</a>
                <a href="{{ route('admin.forms.submissions.export', $form) }}" class="btn btn--ghost btn--sm">Export Excel</a>
                @can('duplicate', $form)
                  <form method="POST" action="{{ route('admin.forms.duplicate', $form) }}">
                    @csrf
                    <button type="submit" class="btn btn--ghost btn--sm">Duplicate</button>
                  </form>
                @endcan
                @if ($form->status === FormStatus::Active)
                  @can('deactivate', $form)
                    <form method="POST" action="{{ route('admin.forms.deactivate', $form) }}" onsubmit="return confirm('Deactivate this form? It will stop accepting submissions immediately.')">
                      @csrf
                      <button type="submit" class="btn btn--danger btn--sm">Deactivate</button>
                    </form>
                  @endcan
                @elseif ($form->status->isEditable())
                  @can('publish', $form)
                    <form method="POST" action="{{ route('admin.forms.publish', $form) }}">
                      @csrf
                      <button type="submit" class="btn btn--ghost btn--sm">Publish</button>
                    </form>
                  @endcan
                @endif
                @can('archive', $form)
                  <form method="POST" action="{{ route('admin.forms.archive', $form) }}" onsubmit="return confirm('Archive this form? It becomes read-only and stops accepting submissions. Submissions are kept.')">
                    @csrf
                    <button type="submit" class="btn btn--danger btn--sm">Archive</button>
                  </form>
                @endcan
                @can('restore', $form)
                  <form method="POST" action="{{ route('admin.forms.restore', $form) }}">
                    @csrf
                    <button type="submit" class="btn btn--ghost btn--sm">Restore</button>
                  </form>
                @endcan
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" style="color:var(--muted);text-align:center;padding:30px">
              No forms yet. <a href="{{ route('admin.forms.create') }}">Create the first one</a>.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:16px">{{ $forms->links() }}</div>

  <style>
    .fb-row-actions { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; }
    .fb-row-actions form { margin: 0; }
  </style>
</x-layouts.admin>
