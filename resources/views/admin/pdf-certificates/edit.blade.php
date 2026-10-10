<x-layouts.admin :title="$template->name">
  <div style="margin-bottom:16px">
    <a href="{{ route('admin.pdf-certificates.index') }}" class="btn btn--ghost btn--sm">&larr; PDF Certificates</a>
  </div>

  <div class="admin-card" style="max-width:560px">
    <h2 style="margin-top:0">Template details</h2>
    <form method="POST" action="{{ route('admin.pdf-certificates.update', $template) }}">
      @csrf
      @method('PATCH')

      <div class="field">
        <label for="name">Template name</label>
        <input type="text" id="name" name="name" value="{{ old('name', $template->name) }}" required>
        @error('name')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="slug">Slug</label>
        <input type="text" id="slug" name="slug" value="{{ old('slug', $template->slug) }}" required>
        @error('slug')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3">{{ old('description', $template->description) }}</textarea>
        @error('description')<div class="error">{{ $message }}</div>@enderror
      </div>

      <button type="submit" class="btn btn--primary">Save details</button>
    </form>
  </div>

  <div class="admin-card" style="max-width:560px">
    <h2 style="margin-top:0;font-size:1rem">Design &amp; generation</h2>
    <div style="display:flex;gap:10px">
      <a href="{{ route('admin.pdf-studio.show', $template) }}" class="btn btn--primary">Open editor</a>
      <a href="{{ route('admin.pdf-certificates.batches', $template) }}" class="btn btn--ghost">Batch history</a>
    </div>
  </div>

  <div class="admin-card" style="max-width:560px">
    <h2 style="margin-top:0;font-size:1rem">Archive</h2>
    <p style="color:var(--muted)">
      Hides this template from the PDF Certificates list. Its saved design, batches and issued
      certificates are kept and remain fully usable — archiving only declutters the list.
    </p>
    <form method="POST" action="{{ route('admin.pdf-certificates.archive', $template) }}" onsubmit="return confirm('Archive this template?')">
      @csrf
      <button type="submit" class="btn btn--danger">Archive template</button>
    </form>
  </div>
</x-layouts.admin>
