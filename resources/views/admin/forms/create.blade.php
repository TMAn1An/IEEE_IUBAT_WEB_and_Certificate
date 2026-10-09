<x-layouts.admin title="Create Form">
  <div class="admin-card" style="max-width:640px">
    <form method="POST" action="{{ route('admin.forms.store') }}">
      @csrf
      <div class="field">
        <label for="name">Form name</label>
        <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="150" autofocus placeholder="e.g. Volunteer Registration 2026">
        <p class="help">Used in the admin and to generate the public URL. You can set a different public title and the URL in the builder.</p>
      </div>
      <div class="field">
        <label for="description">Description <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
        <textarea id="description" name="description" rows="3" maxlength="2000">{{ old('description') }}</textarea>
        <p class="help">Shown under the title on the public form.</p>
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn--primary">Create and open builder</button>
        <a href="{{ route('admin.forms.index') }}" class="btn btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
</x-layouts.admin>
