@extends('services.layout')
@section('title', $page['title'])
@section('content')
<section class="portal-hero community-hero">
    <div><p class="eyebrow"><span></span> {{ $page['eyebrow'] }}</p><h1>{{ $page['title'] }}</h1><p>{{ $page['description'] }}</p></div>
    <aside><b>Sebelum mengajukan</b><span>✓ Siapkan NIK dan nomor HP aktif</span><span>✓ Jelaskan kebutuhan secara lengkap</span><span>✓ Unggah dokumen bila dipersyaratkan</span></aside>
</section>
<section class="community-catalog">
    <div class="community-note"><span>i</span><p>{{ $page['note'] }}</p></div>
    <div class="portal-service-grid community-grid">
        @foreach($services as $key => $service)
            <article><span>{{ $service['short'] }}</span><small>{{ $service['category_name'] }}</small><h2>{{ $service['name'] }}</h2><p>{{ $service['description'] }}</p><ul>@foreach($service['requirements'] as $requirement)<li>{{ $requirement }}</li>@endforeach</ul><div><em>Estimasi {{ $service['duration'] }}</em><a href="{{ route('services.create', ['jenis' => $key]) }}">Ajukan sekarang →</a></div></article>
        @endforeach
    </div>
</section>
@endsection
