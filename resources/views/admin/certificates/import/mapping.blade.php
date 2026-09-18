@php
  use App\Services\Certificates\Import\ImportMappingTarget;
  $assignableFields = $template->fields->filter(fn ($f) => $f->field_type->isAssignable());
@endphp
<x-layouts.admin title="Map Columns — {{ $template->name }}" wide>
  <div class="admin-card">
    <h2 style="margin-top:0">Map Excel columns to {{ $template->name }} fields</h2>
    <p style="color:var(--muted);margin-top:-8px">
      Suggested mappings are pre-selected where a column heading matched — review and adjust every
      row before continuing. The Recipient Name column and every required field must be mapped.
    </p>

    @isset($mappingErrors)
      <div class="alert alert--error">
        <ul style="margin:0;padding-left:18px">
          @foreach ($mappingErrors as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endisset

    <form method="POST" action="{{ route('admin.certificates.import.preview', $template) }}">
      @csrf
      <input type="hidden" name="stored_file" value="{{ $storedFile }}">

      <table class="admin-table">
        <thead>
          <tr>
            <th>Excel column</th>
            <th>Sample value</th>
            <th>Maps to</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($headers as $index => $header)
            <tr>
              <td><strong>{{ $header ?: '(column '.($index + 1).')' }}</strong></td>
              <td style="color:var(--muted)">{{ $sampleRow[$index] ?? '' }}</td>
              <td>
                <select name="mapping[{{ $index }}]">
                  <option value="{{ ImportMappingTarget::IGNORE }}" @selected(($mapping[$index] ?? null) === null)>Ignore column</option>
                  @foreach ($assignableFields as $field)
                    <option value="{{ $field->field_key }}" @selected(($mapping[$index] ?? null) === $field->field_key)>
                      {{ $field->label }}@if($field->is_recipient_name) (Recipient Name, required)@elseif($field->is_required) (required)@endif
                    </option>
                  @endforeach
                  <option value="{{ ImportMappingTarget::CODEWORD }}" @selected(($mapping[$index] ?? null) === ImportMappingTarget::CODEWORD)>Existing Codeword</option>
                  <option value="{{ ImportMappingTarget::CERTIFICATE_NUMBER }}" @selected(($mapping[$index] ?? null) === ImportMappingTarget::CERTIFICATE_NUMBER)>Existing Certificate Number</option>
                </select>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>

      <div style="display:flex;gap:10px;margin-top:20px">
        <button type="submit" class="btn btn--primary">Preview import</button>
        <a href="{{ route('admin.certificates.import.choose-template') }}" class="btn btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
</x-layouts.admin>
