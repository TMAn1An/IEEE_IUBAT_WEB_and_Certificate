@php
  use App\Enums\FormStatus;
@endphp
<x-layouts.admin :title="'Form Builder — '.$form->name" wide>
  <x-slot:head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="/css/forms.css">
    <link rel="stylesheet" href="/css/form-builder.css">
  </x-slot:head>

  {{-- Everything inside #form-builder is rendered by public/js/admin/form-builder.js
       from the JSON below; the server re-validates every save. --}}
  <div class="fb" id="form-builder">
    <div class="fb-toolbar">
      <div class="fb-toolbar__left">
        <a href="{{ route('admin.forms.index') }}">&larr; Forms</a>
        <span class="badge" id="fb-status-badge"></span>
        <span class="fb-save-state" id="fb-save-state" role="status" aria-live="polite"></span>
      </div>
      <div class="fb-toolbar__right">
        <a href="{{ route('admin.forms.preview', $form) }}" class="btn btn--ghost btn--sm" target="_blank" rel="noopener">Preview</a>
        <a href="{{ route('admin.forms.submissions.index', $form) }}" class="btn btn--ghost btn--sm">Submissions</a>
        @can('duplicate', $form)
          <form method="POST" action="{{ route('admin.forms.duplicate', $form) }}" class="fb-inline-form">
            @csrf
            <button type="submit" class="btn btn--ghost btn--sm" data-fb-guard>Duplicate</button>
          </form>
        @endcan
        @if ($form->status === FormStatus::Active)
          @can('deactivate', $form)
            <form method="POST" action="{{ route('admin.forms.deactivate', $form) }}" class="fb-inline-form" onsubmit="return confirm('Deactivate this form? It stops accepting submissions immediately.')">
              @csrf
              <button type="submit" class="btn btn--danger btn--sm" data-fb-guard>Deactivate</button>
            </form>
          @endcan
        @endif
        <button type="button" class="btn btn--ghost btn--sm" id="fb-save" hidden>Save draft</button>
        <button type="button" class="btn btn--primary btn--sm" id="fb-publish" hidden>Publish</button>
      </div>
    </div>

    <div class="fb-errors" id="fb-errors" role="alert" hidden></div>

    <div class="fb-shell">
      <aside class="fb-palette" id="fb-palette" aria-label="Add a field"></aside>

      <section class="fb-stage" aria-label="Form preview">
        <div class="fb-stage__bar">
          <label class="fb-check"><input type="checkbox" id="fb-show-hidden" checked> Show fields hidden by conditions</label>
          <div class="fb-seg" role="group" aria-label="Preview width">
            <button type="button" class="is-active" data-fb-device="desktop">Desktop</button>
            <button type="button" data-fb-device="mobile">Mobile</button>
          </div>
        </div>
        <div class="fb-canvas" id="fb-canvas"></div>
        <div class="fb-archived" id="fb-archived"></div>
      </section>

      <aside class="fb-panel" aria-label="Settings">
        <div class="fb-tabs" role="tablist" id="fb-tabs"></div>
        <div class="fb-panel__body" id="fb-panel"></div>
      </aside>
    </div>
  </div>

  <script type="application/json" id="form-builder-data">@json($builder)</script>
  <script src="/js/forms/form-logic.js"></script>
  <script src="/js/admin/form-builder.js"></script>
</x-layouts.admin>
