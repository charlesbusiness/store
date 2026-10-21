<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f6f7f9" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0e1116" media="(prefers-color-scheme: dark)">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <title>@yield('title', 'Stockly') &middot; Sales &amp; Inventory</title>
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @stack('head')
</head>
<body>
    <div id="app"></div>
</body>
</html>
