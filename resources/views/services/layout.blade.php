<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Layanan administrasi kependudukan Desa Simare.">
    <link rel="icon" type="image/png" href="{{ asset('images/desaku-logo.png') }}">
    <title>@yield('title') — DESAKU Simare</title>
    @vite(['resources/css/app.css', 'resources/css/pbb.css', 'resources/js/app.js'])
</head>
<body class="service-portal">
    <header class="portal-header">
        <a class="brand" href="{{ route('home') }}"><span class="brand-logo"><img src="{{ asset('images/desaku-logo.png') }}" alt="Logo DESAKU"></span><span><strong>DESAKU <em>SIMARE</em></strong><small>Pelayanan warga</small></span></a>
        <nav><a href="{{ route('services.index') }}">Daftar layanan</a><a href="{{ route('home') }}">Beranda</a></nav>
    </header>
    <main class="portal-main">@yield('content')</main>
    <footer class="portal-footer">DESAKU Simare · Data pribadi diproses untuk pelayanan yang Anda ajukan.</footer>
</body>
</html>
