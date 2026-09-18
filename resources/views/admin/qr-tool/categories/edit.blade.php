@php use App\Enums\QrCategoryFieldType; @endphp
<x-layouts.admin :title="$category->name">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
    <div>
      <span class="badge {{ $category->is_active ? 'badge--active' : 'badge--archived' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span>
      <span style="color:var(--muted);font-size:.85rem;margin-left:8px">Slug: <code>{{ $category->slug }}</code></span>
    </div>
    <div style="display:flex;gap:8px">
      @if (! $category->is_active)
        <form method="POST" action="{{ route('admin.qr.categories.activate', $category) }}">
          @csrf
          <button type="submit" class="btn btn--primary">Activate</button>
        </form>
      @else
        <form method="POST" action="{{ route('admin.qr.categories.deactivate', $category) }}" onsubmit="return confirm('Deactivate this category? It will no longer be available for new QR generation.')">
          @csrf
          <button type="submit" class="btn btn--danger">Deactivate</button>
        </form>
      @endif
    </div>
  </div>

  <div class="admin-card" style="max-width:640px">
    <h2 style="margin-top:0;font-size:1rem">Category details</h2>
    <form method="POST" action="{{ route('admin.qr.categories.update', $category) }}">
      @csrf
      @method('PATCH')

      <div class="field">
        <label for="name">Category name</label>
        <input type="text" id="name" name="name" value="{{ old('name', $category->name) }}" required>
        @error('name')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="event_name">Conference/Event name</label>
        <input type="text" id="event_name" name="event_name" value="{{ old('event_name', $category->event_name) }}">
        @error('event_name')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="slug">Slug</label>
        <input type="text" id="slug" name="slug" value="{{ old('slug', $category->slug) }}" required>
        @error('slug')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3">{{ old('description', $category->description) }}</textarea>
        @error('description')<div class="error">{{ $message }}</div>@enderror
      </div>

      <button type="submit" class="btn btn--primary">Save details</button>
    </form>
  </div>

  <div class="admin-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <h2 style="margin:0;font-size:1rem">Fields</h2>
      <a href="{{ route('admin.qr.categories.fields.create', $category) }}" class="btn btn--primary btn--sm">Add field</a>
    </div>

    @if ($fields->isEmpty())
      <p style="color:var(--muted)">No fields yet. Add the fields this QR record needs — e.g. recipient name, role, session.</p>
    @else
      <table class="admin-table">
        <thead>
          <tr>
            <th>Label</th>
            <th>Key</th>
            <th>Type</th>
            <th>Badges</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($fields as $field)
            <tr>
              <td>{{ $field->label }}</td>
              <td><code>{{ $field->key }}</code></td>
              <td><span class="badge badge--type">{{ $field->type->label() }}</span></td>
              <td>
                @if ($field->is_recipient_name)<span class="badge badge--recipient">Recipient</span>@endif
                @if ($field->required)<span class="badge badge--required">Required</span>@endif
                @if ($field->show_on_verification)<span class="badge badge--verification">Public Verification</span>@endif
              </td>
              <td>
                <div class="field-actions">
                  <form method="POST" action="{{ route('admin.qr.categories.fields.move-up', [$category, $field]) }}">
                    @csrf
                    <button type="submit" class="link-btn" title="Move up" {{ $loop->first ? 'disabled' : '' }}>&uarr;</button>
                  </form>
                  <form method="POST" action="{{ route('admin.qr.categories.fields.move-down', [$category, $field]) }}">
                    @csrf
                    <button type="submit" class="link-btn" title="Move down" {{ $loop->last ? 'disabled' : '' }}>&darr;</button>
                  </form>
                  <a href="{{ route('admin.qr.categories.fields.edit', [$category, $field]) }}" class="btn btn--ghost btn--sm">Edit</a>
                  <form method="POST" action="{{ route('admin.qr.categories.fields.destroy', [$category, $field]) }}" onsubmit="return confirm('Remove this field?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn--danger btn--sm">Remove</button>
                  </form>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  <div class="admin-card">
    <h2 style="margin-top:0;font-size:1rem">Form preview</h2>
    <p style="color:var(--muted);font-size:.85rem;margin-top:-6px">
      What the Generate QR form will look like for this category. Nothing here is saved.
    </p>

    @if ($fields->isEmpty())
      <p style="color:var(--muted)">Add a field above to see the preview.</p>
    @else
      <div class="preview-form">
        @foreach ($fields as $field)
          <div class="field">
            <label>{{ $field->label }}@if($field->required)<span class="req"> *</span>@endif</label>
            @switch($field->type)
              @case(QrCategoryFieldType::LongText)
                <textarea rows="3" disabled placeholder="{{ $field->label }}"></textarea>
                @break
              @case(QrCategoryFieldType::Number)
                <input type="number" disabled placeholder="{{ $field->label }}">
                @break
              @case(QrCategoryFieldType::Date)
                <input type="date" disabled>
                @break
              @case(QrCategoryFieldType::Dropdown)
                <select disabled>
                  <option>Select {{ $field->label }}&hellip;</option>
                  @foreach ($field->options ?? [] as $option)
                    <option>{{ $option }}</option>
                  @endforeach
                </select>
                @break
              @default
                <input type="text" disabled placeholder="{{ $field->label }}">
            @endswitch
          </div>
        @endforeach
      </div>
    @endif
  </div>
</x-layouts.admin>
