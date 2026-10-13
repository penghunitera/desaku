@extends('admin.layout')
@section('title','Ringkasan Kampung TB')
@section('heading','Ringkasan Kampung TB')
@section('subheading','Angka agregat untuk pengambilan keputusan desa—tanpa nama, alamat, gejala, atau diagnosis warga.')
@section('content')
<section class="tb-alert">
    <div><span>▦</span><div><small>DASHBOARD AGREGAT</small><b>Pantau dukungan program tanpa membuka identitas pasien</b><p>Gunakan ringkasan ini untuk perencanaan kunjungan, edukasi, transportasi rujukan, dan dukungan gizi.</p></div></div>
</section>
<section class="tb-metrics">
    <article><small>WARGA MASUK REGISTER</small><b>{{ $metrics['screened'] }}</b><span>Data program desa</span></article>
    <article class="amber"><small>PERLU TINDAK LANJUT</small><b>{{ $metrics['follow_up'] }}</b><span>Tanpa identitas</span></article>
    <article><small>SEDANG BEROBAT</small><b>{{ $metrics['treatment'] }}</b><span>Perlu dukungan</span></article>
    <article class="blue"><small>SELESAI / SEMBUH</small><b>{{ $metrics['completed'] }}</b><span>Hasil program</span></article>
</section>
<section class="admin-panel">
    <div class="panel-head"><div><small>SEBARAN AGREGAT</small><h2>Ringkasan per dusun</h2></div><b>Identitas disembunyikan</b></div>
    <div class="admin-table">
        <div class="table-row table-head"><span>Wilayah</span><span>Masuk register</span><span>Sedang berobat</span><span>Selesai / sembuh</span></div>
        @forelse($byHamlet as $row)
        <div class="table-row"><b>{{ $row->hamlet }}</b><span>{{ $row->screened }}</span><span>{{ $row->treatment }}</span><span>{{ $row->completed }}</span></div>
        @empty
        <div class="table-empty">Belum ada data agregat Kampung TB.</div>
        @endforelse
    </div>
</section>
@endsection
