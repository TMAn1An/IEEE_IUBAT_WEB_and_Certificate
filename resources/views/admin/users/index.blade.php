<x-layouts.admin title="Users">
  <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
    <a href="{{ route('admin.users.create') }}" class="btn btn--primary">New admin user</a>
  </div>

  <div class="admin-card" style="padding:0">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Status</th>
          <th>Joined</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @foreach ($users as $user)
          <tr>
            <td>{{ $user->name }}</td>
            <td>{{ $user->email }}</td>
            <td><span class="badge badge--role">{{ $user->role->label() }}</span></td>
            <td>
              @if ($user->is_active)
                <span class="badge badge--active">Active</span>
              @else
                <span class="badge badge--inactive">Inactive</span>
              @endif
            </td>
            <td>{{ $user->created_at->format('j M Y') }}</td>
            <td style="text-align:right;white-space:nowrap">
              <a href="{{ route('admin.users.edit', $user) }}" class="btn btn--ghost btn--sm">Edit</a>
              <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}" style="display:inline">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn--sm {{ $user->is_active ? 'btn--danger' : 'btn--ghost' }}">
                  {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                </button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</x-layouts.admin>
