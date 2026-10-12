<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Stockly') &middot; Sales & Inventory</title>
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
  <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.svg') }}">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/viz.css') }}">
  <script>
    // Applied before first paint so a dark-mode reload never flashes white.
    (function () {
      var saved = localStorage.getItem('stockly_theme');
      if (saved) document.documentElement.setAttribute('data-theme', saved);
    })();
  </script>
  {{-- Alpine is started after the ES modules below have exposed their globals. --}}
  <script>
    const requiredModules = ['packs', 'record-form', 'record-display'];
    let startAlpine = null;

    function startAlpineWhenReady() {
      if (requiredModules.length === 0 && startAlpine) {
        const start = startAlpine;
        startAlpine = null;
        start();
      }
    }

    window.deferLoadingAlpine = function(callback) {
      startAlpine = callback;
      startAlpineWhenReady();
    };

    window.markModuleReady = function(name) {
      const idx = requiredModules.indexOf(name);
      if (idx === -1) return;
      requiredModules.splice(idx, 1);
      startAlpineWhenReady();
    };
  </script>
  {{-- Load Alpine before the modules so the deferred start hook is registered. --}}
  <script src="{{ asset('js/alpine.min.js') }}"></script>
  <script src="{{ asset('js/stockly.js') }}"></script>
  {{-- Load ES modules; each calls markModuleReady(name) after its exports. --}}
  <script type="module" src="{{ asset('js/packs.js?v=' . time()) }}"></script>
  <script type="module" src="{{ asset('js/record-form.js?v=' . time()) }}"></script>
  <script type="module" src="{{ asset('js/searchable-select.js?v=' . time()) }}"></script>
  <script type="module" src="{{ asset('js/record-display.js?v=' . time()) }}"></script>
  @stack('head')
</head>
<body>
<div class="w">
  <header>
    <div class="hd">
      <div class="logo">Stock<b>ly</b></div>
      <button type="button" class="icon-btn" onclick="Stockly.toggleTheme(this)"
              aria-label="Switch between light and dark">Theme</button>
    </div>
    @auth
      <nav>
        @foreach (\App\Enums\NavTab::cases() as $tab)
          <a href="{{ route($tab->routeName()) }}" class="{{ request()->routeIs($tab->routeName()) ? 'on' : '' }}">{{ $tab->label() }}</a>
        @endforeach
      </nav>
    @endauth  </header>

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

<div id="t" role="status" aria-live="polite"></div>

@include('partials.confirm-dialog')

<footer class="foot">
  <span>
    <a href="{{ route('items.index') }}">Items</a> &middot;
    <a href="{{ route('units.index') }}">Units</a>
  </span>
  @auth
    <span>{{ auth()->user()?->name }} &middot; <form method="POST" action="{{ route('logout') }}" style="display:inline">@csrf<button class="x" style="color:var(--mut);font-size:12px" type="submit">Sign out</button></form></span>
  @endauth
</footer>

@stack('scripts')
</body>
</html>
