@php
  use App\Services\QrTool\Import\QrImportMappingTarget;
@endphp
<x-layouts.admin title="Map Columns" wide>
  <div class="admin-card">
    <h2 style="margin-top:0">Map Excel columns</h2>
    <p style="color:var(--muted);margin-top:-8px">
      Destination group: <strong>{{ $group->event_type }} / {{ $group->event_name }} / {{ $group->role }}</strong>.
      Event Type, Event Name and Role for every imported record come from this group, not the
      file — the file's own Conference/Role columns (if present) are only used to double-check the
      file actually belongs to this group. Suggested mappings are pre-selected where a column
      heading matched the old tool's own headings. "SL" and "QR File" are always ignored (see
      docs/CERTIFICATE_SYSTEM.md).
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

    <form method="POST" action="{{ route('admin.qr.import.preview', $group) }}">
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
                  <option value="{{ QrImportMappingTarget::IGNORE }}" @selected(($mapping[$index] ?? null) === null)>Ignore column</option>
                  @foreach ($category->fields->where('key', '!=', 'role') as $field)
                    <option value="{{ $field->key }}" @selected(($mapping[$index] ?? null) === $field->key)>
                      {{ $field->label }}@if($field->is_recipient_name) (Recipient Name, required)@elseif($field->required) (required)@endif
                    </option>
                  @endforeach
                  <option value="{{ QrImportMappingTarget::CODEWORD }}" @selected(($mapping[$index] ?? null) === QrImportMappingTarget::CODEWORD)>Existing Codeword</option>
                  <option value="{{ QrImportMappingTarget::CREATED_AT }}" @selected(($mapping[$index] ?? null) === QrImportMappingTarget::CREATED_AT)>Original Created Date</option>
                  <option value="{{ QrImportMappingTarget::CONFERENCE_VALIDATE }}" @selected(($mapping[$index] ?? null) === QrImportMappingTarget::CONFERENCE_VALIDATE)>Conference (validate against destination group)</option>
                  <option value="{{ QrImportMappingTarget::ROLE_VALIDATE }}" @selected(($mapping[$index] ?? null) === QrImportMappingTarget::ROLE_VALIDATE)>Role (validate against destination group)</option>
                </select>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>

      <div style="display:flex;gap:10px;margin-top:20px">
        <button type="submit" class="btn btn--primary">Preview import</button>
        <a href="{{ route('admin.qr.import.choose-group') }}" class="btn btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
</x-layouts.admin>
