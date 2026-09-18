@php use App\Enums\TemplateFieldType; @endphp
<x-layouts.admin title="Issue Certificate — {{ $template->name }}">
  <div class="admin-card">
    <h2 style="margin-top:0">{{ $template->name }}</h2>

    @if ($fields->isEmpty())
      <p style="color:var(--muted)">This template has no fields configured.</p>
    @else
      <form method="POST" action="{{ route('admin.certificates.store', $template) }}" id="issue-form">
        @csrf

        @error('fields')<div class="error" style="margin-bottom:12px">{{ $message }}</div>@enderror
        @error('template')<div class="error" style="margin-bottom:12px">{{ $message }}</div>@enderror

        @foreach ($fields as $field)
          @php $inputName = "fields[{$field->field_key}]"; @endphp
          <div class="field">
            <label for="{{ $field->field_key }}">
              {{ $field->label }}@if($field->is_required)<span class="req"> *</span>@endif
              @if($field->is_recipient_name)<span class="badge badge--recipient">Recipient name</span>@endif
            </label>

            @switch($field->field_type)
              @case(TemplateFieldType::LongText)
                <textarea id="{{ $field->field_key }}" name="{{ $inputName }}" rows="4" @required($field->is_required)>{{ old("fields.{$field->field_key}") }}</textarea>
                @break
              @case(TemplateFieldType::Number)
                <input type="number" step="any" id="{{ $field->field_key }}" name="{{ $inputName }}" value="{{ old("fields.{$field->field_key}") }}" @required($field->is_required)>
                @break
              @case(TemplateFieldType::Date)
                <input type="date" id="{{ $field->field_key }}" name="{{ $inputName }}" value="{{ old("fields.{$field->field_key}") }}" @required($field->is_required)>
                @break
              @case(TemplateFieldType::Dropdown)
                <select id="{{ $field->field_key }}" name="{{ $inputName }}" @required($field->is_required)>
                  <option value="">Select {{ $field->label }}&hellip;</option>
                  @foreach ($field->options ?? [] as $option)
                    <option value="{{ $option }}" @selected(old("fields.{$field->field_key}") === $option)>{{ $option }}</option>
                  @endforeach
                </select>
                @break
              @default
                <input type="text" id="{{ $field->field_key }}" name="{{ $inputName }}" value="{{ old("fields.{$field->field_key}") }}" @required($field->is_required)>
            @endswitch

            @error("fields.{$field->field_key}")<div class="error">{{ $message }}</div>@enderror
          </div>
        @endforeach

        <div style="display:flex;gap:10px;margin-top:20px">
          <button type="submit" class="btn btn--primary" id="issue-submit">Issue certificate</button>
          <a href="{{ route('admin.certificates.choose-template') }}" class="btn btn--ghost">Cancel</a>
        </div>
      </form>

      <script>
        // Duplicate-submission protection: disable the button the instant the
        // form submits (a reload/back after success still redirects via
        // PRG, so this only guards the accidental double-click case).
        document.getElementById('issue-form').addEventListener('submit', function () {
          var button = document.getElementById('issue-submit');
          button.disabled = true;
          button.textContent = 'Issuing…';
        });
      </script>
    @endif
  </div>
</x-layouts.admin>
