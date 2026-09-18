<x-layouts.admin :title="'Designer — '.$template->name" :wide="true">
  <div class="designer-toolbar">
    <a href="{{ route('admin.templates.edit', $template) }}">&larr; Back to {{ $template->name }}</a>
    @unless ($canEdit)
      <span class="badge badge--archived">Read-only &mdash; template is archived</span>
    @endunless
  </div>

  @unless ($template->hasBackground())
    <div class="admin-card">
      <p>No certificate background uploaded yet. <a href="{{ route('admin.templates.edit', $template) }}">Upload one</a> before using the designer.</p>
    </div>
  @else
    <form id="designer-form" method="POST" action="{{ route('admin.templates.designer.update', $template) }}">
      @csrf
      <input type="hidden" name="layout_json" id="layout_json">

      <div class="designer-shell">
        <div class="designer-elements admin-card">
          <h3 style="margin-top:0">Elements</h3>
          <p class="help" style="margin-top:0">Drag onto the certificate below.</p>
          <ul class="designer-elements__list" id="element-list">
            @foreach ($fields as $field)
              <li class="designer-elements__item" data-kind="field" data-id="{{ $field->id }}"
                  data-label="{{ $field->label }}" data-key="{{ $field->field_key }}" data-type="{{ $field->field_type->value }}">
                <span>{{ $field->label }}</span>
                <span class="badge badge--type">{{ $field->field_type->label() }}</span>
              </li>
            @endforeach
            <li class="designer-elements__item" data-kind="system" data-id="certificate_number" data-label="Certificate Number">
              <span>Certificate Number</span>
              <span class="badge badge--role">System</span>
            </li>
            <li class="designer-elements__item" data-kind="system" data-id="qr_code" data-label="QR Code">
              <span>QR Code</span>
              <span class="badge badge--role">System</span>
            </li>
          </ul>
        </div>

        <div class="designer-canvas-wrap">
          <div class="designer-canvas" id="designer-canvas">
            <canvas id="pdf-canvas"></canvas>
          </div>
        </div>

        <div class="designer-settings admin-card" id="designer-settings">
          <h3 style="margin-top:0">Settings</h3>
          <p class="designer-settings__empty" id="settings-empty">Select an element on the certificate to edit it, or drag a new one from the left.</p>
          <div id="settings-form" hidden></div>
        </div>
      </div>

      <div style="margin-top:16px;display:flex;gap:10px;align-items:center">
        <button type="submit" class="btn btn--primary" id="save-layout-btn" @disabled(!$canEdit)>Save Layout</button>
        <span id="save-hint" class="help" style="margin:0"></span>
      </div>
    </form>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js" integrity="sha512-q+4liFwdPC/bNdhUpZx6aXDx/h77yEQtn4I1slHydcbZK34nLaR3cAeYSJshoxIOq3mjEf7xJE8YWIUHMn+oCQ==" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.0.3/qrcode.min.js" integrity="sha384-U1R6Pw+ZRz1BHheYIgXTC9z9BTBa+Ml3zcLyYB0whlTfF/yJu56l8+xTwLClWtmC" crossorigin="anonymous"></script>
    @php
      $fieldsForJs = $fields->map(fn ($f) => [
          'id' => $f->id,
          'label' => $f->label,
          'field_key' => $f->field_key,
          'field_type' => $f->field_type->value,
          'options' => $f->options,
          'position' => $f->position,
          'style' => $f->style,
      ])->values();
    @endphp
    <script>
      window.DESIGNER_CONFIG = {
        pdfUrl: @json(route('admin.templates.background.show', $template)),
        pdfWorkerSrc: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js',
        canEdit: @json($canEdit),
        storedPageWidth: @json($template->page_width ? (float) $template->page_width : null),
        storedPageHeight: @json($template->page_height ? (float) $template->page_height : null),
        fields: @json($fieldsForJs),
        certificateNumber: @json($template->certificate_number_layout),
        qrCode: @json($template->qr_code_layout),
        sampleCertificateNumber: 'IEEE-IUBAT-2026-0001',
      };
    </script>
    <script src="/js/admin/template-designer.js"></script>
  @endunless
</x-layouts.admin>
