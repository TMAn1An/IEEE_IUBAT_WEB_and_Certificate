<x-layouts.admin :title="'Add field — '.$template->name">
  <div class="admin-card" style="max-width:560px">
    @php $field = null; @endphp
    @include('admin.templates.fields._form', [
        'action' => route('admin.templates.fields.store', $template),
        'method' => 'POST',
    ])
  </div>
</x-layouts.admin>
