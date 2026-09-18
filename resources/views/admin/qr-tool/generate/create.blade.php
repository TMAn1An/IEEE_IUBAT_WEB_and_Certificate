@php use App\Enums\QrCategoryFieldType; @endphp
<x-layouts.admin title="Generate QR — {{ $category->name }}">
  <div class="admin-card">
    <h2 style="margin-top:0">{{ $category->name }}</h2>
    @if ($category->event_name)
      <p style="color:var(--muted);margin-top:-8px">{{ $category->event_name }}</p>
    @endif
    <p style="color:var(--muted)">
      Saves this record's data and generates a codeword + QR code. Download the QR afterward and place it into
      the existing Canva design by hand.
    </p>

    @if ($fields->isEmpty())
      <p style="color:var(--muted)">This category has no fields configured.</p>
    @else
      <form method="POST" action="{{ route('admin.qr.generate.store', $category) }}" id="qr-form">
        @csrf

        @error('fields')<div class="error" style="margin-bottom:12px">{{ $message }}</div>@enderror
        @error('category')<div class="error" style="margin-bottom:12px">{{ $message }}</div>@enderror

        @foreach ($fields as $field)
          @php $inputName = "fields[{$field->key}]"; @endphp
          <div class="field">
            <label for="{{ $field->key }}">
              {{ $field->label }}@if($field->required)<span class="req"> *</span>@endif
              @if($field->is_recipient_name)<span class="badge badge--recipient">Recipient name</span>@endif
            </label>

            @switch($field->type)
              @case(QrCategoryFieldType::LongText)
                <textarea id="{{ $field->key }}" name="{{ $inputName }}" rows="4" @required($field->required)>{{ old("fields.{$field->key}") }}</textarea>
                @break
              @case(QrCategoryFieldType::Number)
                <input type="number" step="any" id="{{ $field->key }}" name="{{ $inputName }}" value="{{ old("fields.{$field->key}") }}" @required($field->required)>
                @break
              @case(QrCategoryFieldType::Date)
                <input type="date" id="{{ $field->key }}" name="{{ $inputName }}" value="{{ old("fields.{$field->key}") }}" @required($field->required)>
                @break
              @case(QrCategoryFieldType::Dropdown)
                <select id="{{ $field->key }}" name="{{ $inputName }}" @required($field->required)>
                  <option value="">Select {{ $field->label }}&hellip;</option>
                  @foreach ($field->options ?? [] as $option)
                    <option value="{{ $option }}" @selected(old("fields.{$field->key}") === $option)>{{ $option }}</option>
                  @endforeach
                </select>
                @break
              @default
                <input type="text" id="{{ $field->key }}" name="{{ $inputName }}" value="{{ old("fields.{$field->key}") }}" @required($field->required)>
            @endswitch

            @error("fields.{$field->key}")<div class="error">{{ $message }}</div>@enderror
          </div>
        @endforeach

        <div style="display:flex;gap:10px;margin-top:20px">
          <button type="submit" class="btn btn--primary" id="qr-submit">Save &amp; generate QR</button>
          <a href="{{ route('admin.qr.generate.choose-category') }}" class="btn btn--ghost">Cancel</a>
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
