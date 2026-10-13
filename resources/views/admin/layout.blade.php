<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title') — Pengelola DESAKU</title>
    @vite(['resources/css/admin.css', 'resources/css/admin-workspace.css', 'resources/css/admin-services.css', 'resources/css/admin-content.css', 'resources/css/admin-health.css', 'resources/css/admin-pbb.css'])
    @stack('styles')
</head>
<body class="admin-body">
<aside class="admin-sidebar">
    <a class="admin-brand" href="{{ route('admin.dashboard') }}"><img src="{{ asset('images/desaku-logo.png') }}"><span><b>DESAKU</b><small>Ruang Pengelola</small></span></a>
    <nav>
        <small class="nav-label">RUANG KERJA</small>
        <a class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i>⌂</i><span>Beranda</span></a>
        <small class="nav-label">DATA DESA</small>
        <a class="{{ request()->routeIs('admin.residents.*') ? 'active' : '' }}" href="{{ route('admin.residents.index') }}"><i>♙</i><span>Data Warga</span></a>
        @if(\App\Support\AdminAccess::canViewDptStatistics(auth()->user()))
        <a href="{{ route('admin.dashboard') }}#dpt"><i>▥</i><span>DPT & Potensi SDM</span></a>
        @endif
        @if(\App\Support\AdminAccess::allows(auth()->user(), 'health.view') || \App\Support\AdminAccess::allows(auth()->user(), 'health.summary'))
        <a class="{{ request()->routeIs('admin.health.*') ? 'active' : '' }}" href="{{ \App\Support\AdminAccess::allows(auth()->user(), 'health.view') ? route('admin.health.tb.index') : route('admin.health.tb.summary') }}"><i>✚</i><span>Kampung TB</span></a>
        @endif
        @if(\App\Support\AdminAccess::allows(auth()->user(), 'tax.manage') || \App\Support\AdminAccess::allows(auth()->user(), 'reports.view'))
        <a class="{{ request()->routeIs('admin.tax.*') || request()->fullUrlIs('*bagian=pbb*') ? 'active' : '' }}" href="{{ route('admin.tax.index') }}"><i>Rp</i><span>PBB Desa</span></a>
        @endif
        <a class="{{ request()->fullUrlIs('*bagian=bumdes*') ? 'active' : '' }}" href="{{ route('admin.data',['bagian'=>'bumdes']) }}"><i>▣</i><span>Ekonomi Desa</span></a>
        <a class="{{ request()->fullUrlIs('*bagian=potensi*') ? 'active' : '' }}" href="{{ route('admin.data',['bagian'=>'potensi']) }}"><i>◇</i><span>Program & Potensi</span></a>
        <small class="nav-label">LAINNYA</small>
        <a class="{{ request()->routeIs('admin.services.*') ? 'active' : '' }}" href="{{ route('admin.services.index') }}"><i>↻</i><span>Pelayanan</span></a>
        <a class="{{ request()->routeIs('admin.content.*') ? 'active' : '' }}" href="{{ route('admin.content.index') }}"><i>▤</i><span>Publikasi</span></a>
        <a href="{{ route('home') }}" target="_blank"><i>↗</i><span>Portal publik</span></a>
    </nav>
    <form action="{{ route('admin.logout') }}" method="post">@csrf<button>Keluar</button></form>
</aside>
<main class="admin-main">
    <header>
        <div><small>DESA SIMARE · KECAMATAN BORBOR</small><h1>@yield('heading')</h1>@hasSection('subheading')<p>@yield('subheading')</p>@endif</div>
        <div class="admin-user"><span>{{ str(auth()->user()->name)->substr(0,1) }}</span><div><b>{{ auth()->user()->name }}</b><small>{{ str(auth()->user()->role)->replace('_',' ')->title() }}</small></div></div>
    </header>
    @if(session('success'))
        <div class="admin-success">✓ {{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="admin-errors"><b>Data belum dapat disimpan</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
