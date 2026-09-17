<x-layouts.admin title="New admin user">
  <div class="admin-card" style="max-width:480px">
    <form method="POST" action="{{ route('admin.users.store') }}">
      @csrf

      <div class="field">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="{{ old('name') }}" required>
        @error('name')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="off">
        @error('email')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="role">Role</label>
        <select id="role" name="role" required>
          <option value="">Select a role&hellip;</option>
          @foreach ($roles as $role)
            <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
          @endforeach
        </select>
        @error('role')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="new-password">
        @error('password')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="password_confirmation">Confirm password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
      </div>

      <div style="display:flex;gap:10px;margin-top:20px">
        <button type="submit" class="btn btn--primary">Create user</button>
        <a href="{{ route('admin.users.index') }}" class="btn btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
</x-layouts.admin>
