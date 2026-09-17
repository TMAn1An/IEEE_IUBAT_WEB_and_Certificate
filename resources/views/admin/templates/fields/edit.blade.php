<x-layouts.admin :title="'Edit field — '.$template->name">
  <div class="admin-card" style="max-width:560px">
    @include('admin.templates.fields._form', [
        'action' => route('admin.templates.fields.update', [$template, $field]),
        'method' => 'PATCH',
    ])
  </div>
</x-layouts.admin>
