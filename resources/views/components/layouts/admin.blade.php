@props(['title' => 'Admin', 'wide' => false])
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }} &mdash; IEEE IUBAT Admin</title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<link rel="stylesheet" href="/css/admin.css">
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="admin-sidebar__brand">
      IEEE IUBAT
      <small>Admin &amp; Certificates</small>
    </div>
    <ul class="admin-nav">
      <li><a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}">Dashboard</a></li>
      <li class="admin-nav__heading">Certificate QR Tool</li>
      <li><a href="{{ route('admin.qr.generate.show') }}" class="{{ request()->routeIs('admin.qr.generate.*') ? 'is-active' : '' }}">Generate QR</a></li>
      <li><a href="{{ route('admin.qr.records.index') }}" class="{{ request()->routeIs('admin.qr.records.*') ? 'is-active' : '' }}">Records</a></li>
      <li><a href="{{ route('admin.qr.groups.index') }}" class="{{ request()->routeIs('admin.qr.groups.*') ? 'is-active' : '' }}">Groups</a></li>
      <li><a href="{{ route('admin.qr.import.choose-group') }}" class="{{ request()->routeIs('admin.qr.import.*') ? 'is-active' : '' }}">Import Excel</a></li>
      <li><a href="{{ route('admin.qr.categories.index') }}" class="{{ request()->routeIs('admin.qr.categories.*') ? 'is-active' : '' }}">QR Categories</a></li>
      <li class="admin-nav__heading">Advanced / Future</li>
      <li><a href="{{ route('admin.templates.index') }}" class="{{ request()->routeIs('admin.templates.*') ? 'is-active' : '' }}">Certificate Templates</a></li>
      <li><a href="{{ route('admin.certificates.index') }}" class="{{ request()->routeIs('admin.certificates.*') ? 'is-active' : '' }}">Advanced Certificates</a></li>
      <li><a href="{{ route('admin.bulk-generation.index') }}" class="{{ request()->routeIs('admin.bulk-generation.*') ? 'is-active' : '' }}">Bulk Generation</a></li>
      <li><a href="{{ route('admin.batches.index') }}" class="{{ request()->routeIs('admin.batches.*') ? 'is-active' : '' }}">Batches</a></li>
      @can('viewAny', \App\Models\User::class)
        <li><a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}">Users</a></li>
      @endcan
    </ul>
    <div class="admin-sidebar__foot">
      {{ auth()->user()->name }}<br>
      <span style="opacity:.7">{{ auth()->user()->role->label() }}</span><br>
      <form method="POST" action="{{ route('admin.logout') }}" style="margin-top:8px">
        @csrf
        <button type="submit">Log out</button>
      </form>
    </div>
  </aside>

  <div class="admin-main">
    <header class="admin-topbar">
      <h1>{{ $title }}</h1>
    </header>
    <div class="admin-content{{ $wide ? ' admin-content--wide' : '' }}">
      @if (session('status'))
        <div class="alert alert--ok">{{ session('status') }}</div>
      @endif
      @if ($errors->any())
        <div class="alert alert--error">
          <ul style="margin:0;padding-left:18px">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      {{ $slot }}
    </div>
  </div>
</div>
</body>
</html>
