@extends('services.layout')
@section('title', 'Status Permohonan')
@section('content')
<div class="status-shell">
    @if(session('submitted'))<div class="success-banner"><span>✓</span><div><b>Permohonan berhasil dikirim</b><p>Simpan nomor permohonan berikut untuk melacak proses.</p></div></div>@endif
    <section class="status-card"><div class="status-top"><div><small>NOMOR PERMOHONAN</small><h1>{{ $submission->tracking_code }}</h1><button type="button" data-copy="{{ $submission->tracking_code }}">Salin nomor</button></div><span class="request-status">Menunggu verifikasi</span></div><div class="status-summary"><div><small>Layanan</small><b>{{ $service['name'] }}</b></div><div><small>Pemohon</small><b>{{ $submission->resident->full_name }}</b></div><div><small>Diajukan</small><b>{{ $submission->submitted_at->translatedFormat('d M Y, H:i') }}</b></div><div><small>Dokumen</small><b>{{ $submission->documents->count() }} berkas</b></div></div></section>
    <section class="timeline-card"><h2>Perjalanan permohonan</h2><div class="status-timeline">@foreach($submission->histories as $history)<article class="complete"><span>✓</span><div><b>{{ $history->title }}</b><p>{{ $history->description }}</p><small>{{ $history->created_at->translatedFormat('d M Y, H:i') }}</small></div></article>@endforeach<article><span>2</span><div><b>Verifikasi operator desa</b><p>Petugas memeriksa kesesuaian data dan kelengkapan dokumen.</p></div></article><article><span>3</span><div><b>Diteruskan ke {{ $submission->destination_agency }}</b><p>Permohonan yang lengkap diteruskan kepada pengelola layanan terkait.</p></div></article><article><span>4</span><div><b>Selesai</b><p>Warga menerima pemberitahuan hasil pelayanan.</p></div></article></div></section>
    <div class="status-actions"><a href="{{ route('services.index') }}">Ajukan layanan lain</a><a href="{{ route('home') }}">Kembali ke beranda</a></div>
</div>
@endsection
