<x-layouts.admin title="Dashboard">
  <div class="admin-stats">
    <div class="admin-stat">
      <div class="admin-stat__n">{{ $templateCount }}</div>
      <div class="admin-stat__l">Certificate templates</div>
    </div>
    <div class="admin-stat">
      <div class="admin-stat__n">{{ $certificateCount }}</div>
      <div class="admin-stat__l">Certificates issued</div>
    </div>
    <div class="admin-stat">
      <div class="admin-stat__n">{{ $batchCount }}</div>
      <div class="admin-stat__l">Bulk batches</div>
    </div>
    <div class="admin-stat">
      <div class="admin-stat__n">{{ $adminCount }}</div>
      <div class="admin-stat__l">Admin users</div>
    </div>
  </div>

  <div class="admin-card">
    <h2 style="margin-top:0">Welcome, {{ auth()->user()->name }}</h2>
    <p style="color:var(--muted)">
      You're signed in as <strong>{{ auth()->user()->role->label() }}</strong>.
      Template management, certificate generation and bulk uploads land in upcoming phases — see
      the nav for what's planned and when.
    </p>
  </div>
</x-layouts.admin>
