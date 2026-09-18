<x-layouts.admin :title="'Edit field — '.$category->name">
  <div class="admin-card" style="max-width:560px">
    @include('admin.qr-tool.categories.fields._form', [
        'action' => route('admin.qr.categories.fields.update', [$category, $field]),
        'method' => 'PATCH',
    ])
  </div>
</x-layouts.admin>
