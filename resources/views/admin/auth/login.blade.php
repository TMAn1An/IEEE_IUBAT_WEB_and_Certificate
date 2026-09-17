<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login &mdash; IEEE IUBAT</title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<link rel="stylesheet" href="/css/admin.css">
</head>
<body>
<div class="login-shell">
  <div class="login-card">
    <h1>IEEE IUBAT Admin</h1>
    <p class="sub">Certificate system &amp; site administration</p>

    @if ($errors->any())
      <div class="alert alert--error">
        <ul style="margin:0;padding-left:18px">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('admin.login.attempt') }}">
      @csrf
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <div class="field">
        <label style="display:flex;align-items:center;gap:8px;font-weight:400">
          <input type="checkbox" name="remember" value="1" style="width:auto">
          Remember me
        </label>
      </div>
      <button type="submit" class="btn btn--primary" style="width:100%">Log in</button>
    </form>
  </div>
</div>
</body>
</html>
