<x-layouts.admin title="My Profile">
  <div class="admin-card" style="max-width:520px;margin-bottom:24px">
    <h2 style="margin-top:0">Account details</h2>

    <div style="margin-bottom:12px">
      <strong>Name</strong><br>
      {{ $user->name }}
    </div>

    <div style="margin-bottom:12px">
      <strong>Email</strong><br>
      {{ $user->email }}
    </div>

    <div style="margin-bottom:12px">
      <strong>Role</strong><br>
      <span class="badge badge--role">{{ $user->role->label() }}</span>
    </div>

    <div style="margin-bottom:12px">
      <strong>Status</strong><br>
      @if ($user->is_active)
        <span class="badge badge--active">Active</span>
      @else
        <span class="badge badge--inactive">Inactive</span>
      @endif
    </div>

    <div>
      <strong>Member since</strong><br>
      {{ $user->created_at->format('j M Y') }}
    </div>
  </div>

  <div class="admin-card" style="max-width:520px">
    <h2 style="margin-top:0">Change password</h2>

    <form method="POST" action="{{ route('admin.profile.password.update') }}">
      @csrf
      @method('PUT')

      <div class="field">
        <label for="current_password">Current password</label>
        <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
        @error('current_password')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="password">New password</label>
        <input type="password" id="password" name="password" required autocomplete="new-password">
        <div class="help">At least 12 characters, with upper and lower case letters, a number and a symbol.</div>
        @error('password')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="password_confirmation">Confirm new password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
      </div>

      <button type="submit" class="btn btn--primary">Change password</button>
    </form>
  </div>
</x-layouts.admin>
