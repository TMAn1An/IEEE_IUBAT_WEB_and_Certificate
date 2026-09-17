<x-layouts.admin :title="$title">
  <div class="admin-card coming-soon">
    <h2 style="color:var(--ink)">{{ $title }}</h2>
    <p>{{ $description }}</p>
    <p style="font-size:.85rem">Planned for <strong>{{ $phase }}</strong> — not built yet.</p>
  </div>
</x-layouts.admin>
