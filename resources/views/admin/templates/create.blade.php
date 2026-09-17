<x-layouts.admin title="New template">
  <div class="admin-card" style="max-width:560px">
    <form method="POST" action="{{ route('admin.templates.store') }}">
      @csrf

      <div class="field">
        <label for="name">Template name</label>
        <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus placeholder="e.g. BECITHCON 2026 Speaker">
        @error('name')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="slug">Slug (optional)</label>
        <input type="text" id="slug" name="slug" value="{{ old('slug') }}" placeholder="auto-generated from the name if left blank">
        <div class="help">Used in admin URLs only — not shown publicly. Lowercase letters, numbers and hyphens.</div>
        @error('slug')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="description">Description (optional)</label>
        <textarea id="description" name="description" rows="3">{{ old('description') }}</textarea>
        @error('description')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="help" style="margin-bottom:16px">The template starts as a <strong>Draft</strong>. You'll add fields on the next screen before activating it.</div>

      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn--primary">Create template</button>
        <a href="{{ route('admin.templates.index') }}" class="btn btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
</x-layouts.admin>
