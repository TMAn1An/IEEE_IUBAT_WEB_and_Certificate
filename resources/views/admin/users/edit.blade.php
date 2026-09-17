<x-layouts.admin title="Edit admin user">
  <div class="admin-card" style="max-width:480px">
    @if ($isLastActiveSuperAdmin)
      <div class="alert alert--ok">This is the last active Super Admin — the role can't be changed away from Super Admin until another Super Admin exists.</div>
    @endif

    <form method="POST" action="{{ route('admin.users.update', $targetUser) }}">
      @csrf
      @method('PUT')

      <div class="field">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="{{ old('name', $targetUser->name) }}" required>
        @error('name')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email', $targetUser->email) }}" required autocomplete="off">
        @error('email')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="role">Role</label>
        <select id="role" name="role" required {{ $isLastActiveSuperAdmin ? 'disabled' : '' }}>
          @foreach ($roles as $role)
            <option value="{{ $role->value }}" @selected(old('role', $targetUser->role->value) === $role->value)>{{ $role->label() }}</option>
          @endforeach
        </select>
        @if ($isLastActiveSuperAdmin)
          <input type="hidden" name="role" value="{{ $targetUser->role->value }}">
        @endif
        @error('role')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="password">New password</label>
        <input type="password" id="password" name="password" autocomplete="new-password">
        <div class="help">Leave blank to keep the current password.</div>
        @error('password')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="password_confirmation">Confirm new password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
      </div>

      <div style="display:flex;gap:10px;margin-top:20px">
        <button type="submit" class="btn btn--primary">Save changes</button>
        <a href="{{ route('admin.users.index') }}" class="btn btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
</x-layouts.admin>
