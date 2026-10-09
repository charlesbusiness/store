<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Sign in &middot; Stockly</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">
</head>
<body>
<div class="w" style="max-width:420px;padding-top:56px">
  <div class="card">
    <div class="logo" style="margin-bottom:18px">Stock<b>ly</b></div>

    @if (session('status'))
      <div class="ok-msg">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
      <div class="err">
        <ul>
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}">
      @csrf
      <div class="g" style="grid-template-columns:1fr">
        <label>
          Email
          <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </label>
        <label>
          Password
          <input type="password" name="password" required autocomplete="current-password">
        </label>
        <label class="check">
          <input type="checkbox" name="remember" value="1"> Remember me
        </label>
      </div>
      <div class="row">
        <button class="btn" type="submit">Sign in</button>
      </div>
    </form>
  </div>

  <p class="mut" style="text-align:center;font-size:12px">
    Accounts are created with <code>php artisan stockly:user</code>.
  </p>
</div>
</body>
</html>
