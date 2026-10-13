@extends('admin.layout')
@section('title','Data Warga')
@section('heading','Data Warga')
@section('subheading','Pusat data penduduk, keluarga, dan riwayat pelayanan Desa Simare.')
@section('content')
<section class="workspace-toolbar">
    <form class="resident-filter" method="get">
        <label class="search-field"><span>⌕</span><input name="q" value="{{ request('q') }}" placeholder="Cari nama, alamat, atau nomor HP"></label>
        <select name="hamlet"><option value="">Semua dusun</option>@foreach($hamlets as $hamlet)<option @selected(request('hamlet')===$hamlet)>{{ $hamlet }}</option>@endforeach</select>
        <select name="status"><option value="">Semua status</option>@foreach(['tetap'=>'Penduduk tetap','sementara'=>'Sementara','pindah'=>'Pindah','meninggal'=>'Meninggal'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select>
        <select name="gender"><option value="">Semua gender</option><option value="laki-laki" @selected(request('gender')==='laki-laki')>Laki-laki</option><option value="perempuan" @selected(request('gender')==='perempuan')>Perempuan</option></select>
        <button class="button secondary">Terapkan</button>
    </form>
    @if(\App\Support\AdminAccess::allows(auth()->user(),'residents.manage'))<a class="button primary" href="{{ route('admin.residents.create') }}">＋ Tambah warga</a>@endif
</section>

<section class="admin-panel resident-table-panel">
    <div class="panel-head"><div><small>REGISTRASI PENDUDUK</small><h2>{{ number_format($residents->total(),0,',','.') }} warga ditemukan</h2></div><span class="data-health">● Data aktif</span></div>
    <div class="data-table">
        <div class="data-tr data-th"><span>Warga</span><span>Wilayah</span><span>Data keluarga</span><span>Aktivitas</span><span>Status</span><span></span></div>
        @forelse($residents as $resident)
        <a class="data-tr" href="{{ route('admin.residents.show',$resident) }}">
            <span class="person-cell"><i>{{ str($resident->full_name)->substr(0,1) }}</i><span><b>{{ $resident->full_name }}</b><small>{{ $resident->gender ? str($resident->gender)->title() : 'Gender belum diisi' }}{{ $resident->birth_date ? ' · '.$resident->birth_date->age.' tahun' : '' }}</small></span></span>
            <span><b>{{ $resident->hamlet ?: 'Belum diatur' }}</b><small>RT {{ $resident->rt ?: '—' }} / RW {{ $resident->rw ?: '—' }}</small></span>
            <span><b>{{ $resident->familyCard ? 'Terhubung KK' : 'Belum ada KK' }}</b><small>{{ $resident->family_relationship ?: 'Hubungan belum diisi' }}</small></span>
            <span><b>{{ $resident->service_requests_count }} layanan</b><small>{{ $resident->tax_objects_count }} objek PBB</small></span>
            <span><em class="status-pill {{ $resident->resident_status }}">{{ str($resident->resident_status)->title() }}</em></span>
            <span class="row-arrow">›</span>
        </a>
        @empty<div class="empty-workspace"><b>Belum ada warga yang cocok</b><p>Ubah filter pencarian atau tambahkan data penduduk baru.</p></div>@endforelse
    </div>
    <div class="pagination-wrap">{{ $residents->links() }}</div>
</section>
@endsection
