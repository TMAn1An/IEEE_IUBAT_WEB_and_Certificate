<x-layouts.admin :title="'Add field — '.$category->name">
  <div class="admin-card" style="max-width:560px">
    @php $field = null; @endphp
    @include('admin.qr-tool.categories.fields._form', [
        'action' => route('admin.qr.categories.fields.store', $category),
        'method' => 'POST',
    ])
  </div>
</x-layouts.admin>
