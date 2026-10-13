@extends('services.layout')
@section('title', 'Layanan Kependudukan')
@section('content')
<section class="portal-hero">
    <div><p class="eyebrow"><span></span> Pelayanan Dukcapil prototype</p><h1>Mulai urusan kependudukan dari Desa Simare.</h1><p>Pilih layanan, siapkan persyaratan, lalu pantau prosesnya menggunakan nomor permohonan.</p></div>
    <aside><b>Yang perlu disiapkan</b><span>✓ NIK 16 angka</span><span>✓ Nomor HP aktif</span><span>✓ Dokumen PDF/JPG/PNG</span></aside>
</section>
<section class="portal-service-grid">
@foreach($services as $key => $service)
    <article><span>{{ $service['short'] }}</span><small>Administrasi kependudukan</small><h2>{{ $service['name'] }}</h2><p>{{ $service['description'] }}</p><ul>@foreach($service['requirements'] as $requirement)<li>{{ $requirement }}</li>@endforeach</ul><div><em>Estimasi {{ $service['duration'] }}</em><a href="{{ route('services.create', ['jenis' => $key]) }}">Ajukan sekarang →</a></div></article>
@endforeach
</section>
@endsection
