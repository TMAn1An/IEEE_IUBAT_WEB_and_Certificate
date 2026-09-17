@php use App\Enums\TemplateFieldType; @endphp
<x-layouts.admin :title="$template->name">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
    <div>
      <span class="badge {{ $template->status->badgeClass() }}">{{ $template->status->label() }}</span>
      <span style="color:var(--muted);font-size:.85rem;margin-left:8px">Slug: <code>{{ $template->slug }}</code></span>
    </div>
    <div style="display:flex;gap:8px">
      @if ($template->status->value !== 'active')
        <form method="POST" action="{{ route('admin.templates.activate', $template) }}">
          @csrf
          <button type="submit" class="btn btn--primary">Activate</button>
        </form>
      @endif
      @if ($template->status->value !== 'archived')
        <form method="POST" action="{{ route('admin.templates.archive', $template) }}" onsubmit="return confirm('Archive this template? It will no longer be available for new certificate generation.')">
          @csrf
          <button type="submit" class="btn btn--danger">Archive</button>
        </form>
      @endif
    </div>
  </div>

  @if ($template->status->value !== 'active' && $activationErrors->isNotEmpty())
    <div class="alert alert--error">
      <strong>Not ready to activate yet:</strong>
      <ul style="margin:6px 0 0;padding-left:18px">
        @foreach ($activationErrors as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="admin-card" style="max-width:640px">
    <h2 style="margin-top:0;font-size:1rem">Template details</h2>
    <form method="POST" action="{{ route('admin.templates.update', $template) }}">
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

  <div class="admin-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <h2 style="margin:0;font-size:1rem">Dynamic fields</h2>
      <a href="{{ route('admin.templates.fields.create', $template) }}" class="btn btn--primary btn--sm">Add field</a>
    </div>

    @if ($fields->isEmpty())
      <p style="color:var(--muted)">No fields yet. Add the fields this certificate needs — e.g. recipient name, role, institution.</p>
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
              <td><code>{{ $field->field_key }}</code></td>
              <td><span class="badge badge--type">{{ $field->field_type->label() }}</span></td>
              <td>
                @if ($field->is_recipient_name)<span class="badge badge--recipient">Recipient</span>@endif
                @if ($field->is_required)<span class="badge badge--required">Required</span>@endif
                @if ($field->show_on_verification)<span class="badge badge--verification">Public Verification</span>@endif
              </td>
              <td>
                <div class="field-actions">
                  <form method="POST" action="{{ route('admin.templates.fields.move-up', [$template, $field]) }}">
                    @csrf
                    <button type="submit" class="link-btn" title="Move up" {{ $loop->first ? 'disabled' : '' }}>&uarr;</button>
                  </form>
                  <form method="POST" action="{{ route('admin.templates.fields.move-down', [$template, $field]) }}">
                    @csrf
                    <button type="submit" class="link-btn" title="Move down" {{ $loop->last ? 'disabled' : '' }}>&darr;</button>
                  </form>
                  <a href="{{ route('admin.templates.fields.edit', [$template, $field]) }}" class="btn btn--ghost btn--sm">Edit</a>
                  <form method="POST" action="{{ route('admin.templates.fields.destroy', [$template, $field]) }}" onsubmit="return confirm('Remove this field?')">
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
      What the future single-certificate form will look like for this template. Nothing here is saved.
    </p>

    @if ($fields->isEmpty())
      <p style="color:var(--muted)">Add a field above to see the preview.</p>
    @else
      <div class="preview-form">
        @foreach ($fields as $field)
          <div class="field">
            <label>{{ $field->label }}@if($field->is_required)<span class="req"> *</span>@endif</label>
            @switch($field->field_type)
              @case(TemplateFieldType::LongText)
                <textarea rows="3" disabled placeholder="{{ $field->label }}"></textarea>
                @break
              @case(TemplateFieldType::Number)
                <input type="number" disabled placeholder="{{ $field->label }}">
                @break
              @case(TemplateFieldType::Date)
                <input type="date" disabled>
                @break
              @case(TemplateFieldType::Dropdown)
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
