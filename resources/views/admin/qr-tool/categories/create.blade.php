<x-layouts.admin title="New QR Category">
  <div class="admin-card" style="max-width:560px">
    <form method="POST" action="{{ route('admin.qr.categories.store') }}">
      @csrf

      <div class="field">
        <label for="name">Category name</label>
        <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus placeholder="e.g. BECITHCON 2026 Speaker/Volunteer">
        @error('name')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="event_name">Conference/Event name (optional)</label>
        <input type="text" id="event_name" name="event_name" value="{{ old('event_name') }}" placeholder="e.g. IEEE BECITHCON 2026">
        <div class="help">Shown on generated QR results and the public verification page for every record in this category.</div>
        @error('event_name')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="slug">Slug (optional)</label>
        <input type="text" id="slug" name="slug" value="{{ old('slug') }}" placeholder="auto-generated from the name if left blank">
        @error('slug')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="description">Description (optional)</label>
        <textarea id="description" name="description" rows="3">{{ old('description') }}</textarea>
        @error('description')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="help" style="margin-bottom:16px">The category starts active. You'll add fields on the next screen.</div>

      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn--primary">Create category</button>
        <a href="{{ route('admin.qr.categories.index') }}" class="btn btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
</x-layouts.admin>
