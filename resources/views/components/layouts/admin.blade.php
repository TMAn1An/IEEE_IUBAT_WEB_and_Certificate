@props(['title' => 'Admin'])
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
      <li><a href="{{ route('admin.templates.index') }}" class="{{ request()->routeIs('admin.templates.*') ? 'is-active' : '' }}">Templates</a></li>
      <li><a href="{{ route('admin.certificates.index') }}" class="{{ request()->routeIs('admin.certificates.*') ? 'is-active' : '' }}">Certificates</a></li>
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
    <div class="admin-content">
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
