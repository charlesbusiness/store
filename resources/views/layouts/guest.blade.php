<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>@yield('title', 'Sign in') &middot; Stockly</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/viz.css') }}">
</head>
<body>
<div class="w" style="max-width:420px;padding-top:48px">
  <div class="card">
    <div class="logo" style="margin-bottom:16px">Stock<b>ly</b></div>

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

    @yield('content')
  </div>
</div>
</body>
</html>
