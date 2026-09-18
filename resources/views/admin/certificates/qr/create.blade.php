@php use App\Enums\TemplateFieldType; @endphp
<x-layouts.admin title="Generate QR — {{ $template->name }}">
  <div class="admin-card">
    <h2 style="margin-top:0">{{ $template->name }}</h2>
    <p style="color:var(--muted);margin-top:-8px">
      Saves this certificate's data and generates a codeword + QR code. No PDF is generated — download the QR
      afterward and place it into the existing Canva design by hand.
    </p>

    @if ($fields->isEmpty())
      <p style="color:var(--muted)">This template has no fields configured.</p>
    @else
      <form method="POST" action="{{ route('admin.certificates.qr.store', $template) }}" id="qr-form">
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
          <button type="submit" class="btn btn--primary" id="qr-submit">Save &amp; generate QR</button>
          <a href="{{ route('admin.certificates.qr.choose-template') }}" class="btn btn--ghost">Cancel</a>
        </div>
      </form>

      <script>
        document.getElementById('qr-form').addEventListener('submit', function () {
          var button = document.getElementById('qr-submit');
          button.disabled = true;
          button.textContent = 'Generating…';
        });
      </script>
    @endif
  </div>
</x-layouts.admin>
