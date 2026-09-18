{{-- Shared by fields/create.blade.php and fields/edit.blade.php. Expects:
     $category, $fieldTypes, $action, $method ('POST'|'PATCH'), and optionally $field (null on create). --}}
@php
    $current = fn (string $key, $default = null) => old($key, $field->{$key} ?? $default);
    $currentOptions = old('options', $field->options ?? ['']);
    if (empty($currentOptions)) { $currentOptions = ['']; }
@endphp
<form method="POST" action="{{ $action }}">
  @csrf
  @if ($method === 'PATCH')
    @method('PATCH')
  @endif

  <div class="field">
    <label for="label">Label</label>
    <input type="text" id="label" name="label" value="{{ $current('label') }}" required placeholder="e.g. Role">
    @error('label')<div class="error">{{ $message }}</div>@enderror
  </div>

  <div class="field">
    <label for="key">Field key</label>
    <input type="text" id="key" name="key" value="{{ $current('key') }}" required placeholder="e.g. role" pattern="[a-z][a-z0-9_]*">
    <div class="help">Internal, stable identifier — lowercase letters, numbers and underscores, starting with a letter.</div>
    @error('key')<div class="error">{{ $message }}</div>@enderror
  </div>

  <div class="field">
    <label for="type">Field type</label>
    <select id="type" name="type" required>
      @foreach ($fieldTypes as $type)
        <option value="{{ $type->value }}" @selected($current('type', 'text') === $type->value)>{{ $type->label() }}</option>
      @endforeach
    </select>
    @error('type')<div class="error">{{ $message }}</div>@enderror
  </div>

  <div class="field">
    <label>Dropdown options</label>
    <div class="help" style="margin-top:0;margin-bottom:8px">Only used when Field Type is Dropdown. Add each choice on its own line.</div>
    <div id="options-list" class="options-list">
      @foreach ($currentOptions as $option)
        <div class="options-list__row">
          <input type="text" name="options[]" value="{{ $option }}" placeholder="Option value">
          <button type="button" class="btn btn--ghost btn--sm" onclick="this.parentElement.remove()">Remove</button>
        </div>
      @endforeach
    </div>
    <button type="button" class="btn btn--ghost btn--sm" onclick="addOptionRow()">+ Add option</button>
    @error('options')<div class="error">{{ $message }}</div>@enderror
    @error('options.*')<div class="error">{{ $message }}</div>@enderror
  </div>

  <div class="field field--checkbox">
    <label><input type="checkbox" name="required" value="1" @checked($current('required', true))> Required on the Generate QR form</label>
  </div>

  <div class="field field--checkbox">
    <label><input type="checkbox" name="show_on_verification" value="1" @checked($current('show_on_verification', true))> Show on the public verification page</label>
  </div>

  <div class="field field--checkbox">
    <label><input type="checkbox" name="is_recipient_name" value="1" @checked($current('is_recipient_name', false))> This is the recipient name field</label>
    <div class="help">Only allowed on a Text field. Setting this clears the flag from any other field on this category.</div>
    @error('is_recipient_name')<div class="error">{{ $message }}</div>@enderror
  </div>

  <div style="display:flex;gap:10px;margin-top:20px">
    <button type="submit" class="btn btn--primary">{{ $field ? 'Save field' : 'Add field' }}</button>
    <a href="{{ route('admin.qr.categories.edit', $category) }}" class="btn btn--ghost">Cancel</a>
  </div>
</form>

<script>
  function addOptionRow() {
    var list = document.getElementById('options-list');
    var row = document.createElement('div');
    row.className = 'options-list__row';
    row.innerHTML = '<input type="text" name="options[]" placeholder="Option value">' +
      '<button type="button" class="btn btn--ghost btn--sm" onclick="this.parentElement.remove()">Remove</button>';
    list.appendChild(row);
    row.querySelector('input').focus();
  }
</script>
