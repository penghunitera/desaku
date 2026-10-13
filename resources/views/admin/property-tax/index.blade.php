@extends('admin.layout')
@section('title', 'PBB Desa')
@section('heading', 'PBB desa per tahun')
@section('subheading', 'Pencocokan NIK penduduk, hasil API, dan rekap SPPT dalam satu ruang kerja.')
@section('content')
@php
    $hasAnnualData = $summary['object_count'] > 0;
@endphp
<div class="pbb-admin-actions">
    <form method="get" class="resident-filter">
        <label>Tahun pajak <select name="tahun" onchange="this.form.submit()">@foreach($availableYears as $availableYear)<option value="{{ $availableYear }}" @selected($year === $availableYear)>{{ $availableYear }}</option>@endforeach</select></label>
        <noscript><button class="button secondary">Tampilkan</button></noscript>
    </form>
    <a class="button secondary" href="{{ route('admin.tax.export') }}">Unduh rekap CSV</a>
    @if($canManage)<a class="button primary" href="{{ route('admin.data', ['bagian' => 'pbb']) }}">+ Entry SPPT / ketetapan</a>@endif
</div>
<div class="form-notice pbb-admin-notice"><span>i</span><p><b>API NIK hanya memberikan total tunggakan lintas tahun.</b> Total ini tidak dijadikan ketetapan atau pembayaran tahun {{ $year }}. Rekap tahunan berasal dari SPPT yang dientri pengelola. PBB dipungut pemerintah kabupaten/kota, bukan otomatis pendapatan APBDes. Objek milik warga bisa berada di luar desa.</p></div>
<section class="admin-metrics pbb-admin-metrics" aria-label="Rekap tahunan">
    <article><div><small>POTENSI / KETETAPAN {{ $year }}</small><b>{{ $hasAnnualData ? 'Rp'.number_format($summary['potential'], 0, ',', '.') : 'Belum terdata' }}</b><p>{{ $summary['object_count'] }} objek · {{ $summary['verified_count'] }} SPPT terverifikasi</p></div></article>
    <article><div><small>PEMBAYARAN TERCATAT</small><b>{{ $hasAnnualData ? 'Rp'.number_format($summary['realized'], 0, ',', '.') : 'Belum terdata' }}</b><p>Sesuai data pembayaran yang dientri</p></div></article>
    <article><div><small>SISA KETETAPAN {{ $year }}</small><b>{{ $hasAnnualData ? 'Rp'.number_format($summary['outstanding'], 0, ',', '.') : 'Belum terdata' }}</b><p>Belum dibayar, termasuk estimasi</p></div></article>
    <article><div><small>TUNGGAKAN JATUH TEMPO {{ $year }}</small><b>{{ $hasAnnualData ? 'Rp'.number_format($summary['overdue'], 0, ',', '.') : 'Belum terdata' }}</b><p>Hanya SPPT terverifikasi dan lewat jatuh tempo</p></div></article>
</section>
<section class="pbb-sync-panel admin-panel">
    <div><small>HASIL API · SEMUA TAHUN</small><h2>{{ $coverage['checked'] ? 'Rp'.number_format($coverage['total'], 0, ',', '.') : 'Belum tersedia' }}</h2><p>Total hasil terakhir {{ $coverage['checked'] }} NIK. Bukan rekap seluruh objek geografis desa, dan bukan angka tahunan.</p><p>{{ $coverage['fresh'] }} hasil kurang dari 24 jam · {{ $coverage['queued'] }} antrean · {{ $coverage['failed'] }} gagal pada percobaan terakhir</p></div>
    <div class="pbb-sync-actions"><b>{{ $coverage['checked'] }} NIK dicek / {{ $coverage['eligible'] }} warga dengan NIK</b><small>Pengecekan terakhir: {{ $coverage['latest']?->format('d/m/Y H:i') ?? 'belum pernah' }}</small>
        @if($canManage)<form method="post" action="{{ route('admin.tax.sync-village') }}">@csrf<input type="hidden" name="hamlet" value="{{ request('hamlet') }}"><button class="button primary">Sinkronkan {{ request('hamlet') ?: 'warga aktif' }}</button></form><small>Melewati hasil sukses &lt;24 jam. Maksimal 500 warga per batch.</small>@endif
        <a href="{{ request()->fullUrl() }}" class="button secondary">Muat ulang hasil</a>
    </div>
</section>
@if($canManage)<p class="pbb-worker-hint">Sinkronisasi berjalan di latar belakang. Jalankan worker: <code>php artisan queue:work --timeout=30</code>. NIK dikirim hanya ke API PBB yang dikonfigurasi; layanan saat ini menggunakan HTTP tanpa enkripsi transport.</p>@endif
<section class="admin-panel pbb-admin-list">
    <div class="panel-head"><div><small>PENDUDUK AKTIF · {{ $village->name }}</small><h2>PBB per warga</h2></div><b>{{ $residents->total() }} warga pada filter</b></div>
    <form class="resident-filter pbb-table-filters" method="get"><input type="hidden" name="tahun" value="{{ $year }}"><input name="q" value="{{ request('q') }}" placeholder="Cari nama warga" aria-label="Nama warga"><select name="hamlet" aria-label="Dusun"><option value="">Semua dusun</option>@foreach($hamlets as $hamlet)<option @selected(request('hamlet') === $hamlet)>{{ $hamlet }}</option>@endforeach</select><select name="sync" aria-label="Status sinkronisasi"><option value="">Semua status API</option>@foreach(['unchecked' => 'Belum dicek / NIK berubah', 'queued' => 'Dalam antrean', 'success' => 'Berhasil', 'failed' => 'Gagal'] as $value => $label)<option value="{{ $value }}" @selected(request('sync') === $value)>{{ $label }}</option>@endforeach</select><button class="button secondary">Terapkan</button></form>
    <div class="pbb-table-scroll"><table class="pbb-admin-table"><thead><tr><th>Warga / dusun</th><th>Total API semua tahun</th><th>Ketetapan {{ $year }}</th><th>Sisa {{ $year }}</th><th>Sinkronisasi</th>@if($canManage)<th>Aksi</th>@endif</tr></thead><tbody>
    @forelse($residents as $resident)
        @php
            $snapshot = $resident->propertyTaxSnapshot;
            $matchingIdentity = $snapshot && $snapshot->nik_hash === $resident->nik_hash;
            $assessments = $resident->taxObjects->flatMap->assessments;
            $remaining = $assessments->sum(fn ($a) => max(0, (float) $a->assessed_amount - (float) $a->paid_amount));
            $state = $matchingIdentity ? $snapshot->sync_status : 'unchecked';
        @endphp
        <tr><td><a href="{{ route('admin.residents.show', $resident) }}#pbb">{{ $resident->full_name }}</a><small>{{ $resident->hamlet ?: 'Dusun belum diisi' }} · NIK dilindungi</small></td><td>@if($matchingIdentity && $snapshot->checked_at)Rp{{ number_format($snapshot->total_outstanding, 0, ',', '.') }}<small>{{ $snapshot->checked_at->format('d/m/Y H:i') }}{{ $snapshot->checked_at->lt(now()->subDay()) || $state !== 'success' ? ' · hasil sebelumnya' : '' }}</small>@else<span class="pbb-muted">Belum tersedia</span>@endif</td><td>@if($assessments->count())Rp{{ number_format($assessments->sum('assessed_amount'), 0, ',', '.') }}<small>{{ $assessments->count() }} objek · {{ $assessments->where('status', 'verified')->count() }} terverifikasi</small>@else<span class="pbb-muted">Belum terdata</span>@endif</td><td>@if($assessments->count())Rp{{ number_format($remaining, 0, ',', '.') }}@else<span class="pbb-muted">—</span>@endif</td><td><span class="pbb-sync-state {{ $state }}">{{ match($state) {'success' => 'Berhasil', 'failed' => 'Gagal', 'queued' => 'Antrean', default => 'Belum dicek'} }}</span>@if($matchingIdentity && $snapshot->error_code)<small>{{ match($snapshot->error_code) {'api_unavailable' => 'API belum dapat dihubungi', 'invalid_identity' => 'Periksa NIK warga', 'resident_inactive' => 'Warga tidak aktif', 'worker_failed' => 'Worker gagal / antrean kedaluwarsa', default => 'Format API belum didukung'} }}</small>@endif</td>@if($canManage)<td>@if($resident->nik_encrypted && $resident->nik_hash)<form method="post" action="{{ route('admin.tax.sync-resident', $resident) }}">@csrf<button class="button secondary" @disabled($state === 'queued' && $snapshot->queued_at?->gte(now()->subHours(2)))>{{ $state === 'failed' ? 'Coba lagi' : 'Cek API' }}</button></form>@else<small>Lengkapi NIK</small>@endif</td>@endif</tr>
    @empty<tr><td colspan="{{ $canManage ? 6 : 5 }}" class="table-empty">Belum ada warga yang cocok. Tambahkan penduduk atau ubah filter.</td></tr>@endforelse
    </tbody></table></div><div class="pagination-wrap">{{ $residents->links() }}</div>
    <p class="pbb-admin-footnote">Total API Rp0 tidak memastikan kepemilikan objek pajak. Data yang belum tersedia tidak dianggap lunas. Hasil gagal tidak menghapus nilai sukses sebelumnya.</p>
</section>
<section class="admin-panel pbb-annual-history"><div class="panel-head"><div><small>REKAP SPPT / ESTIMASI</small><h2>Riwayat lintas tahun</h2></div></div><div class="pbb-table-scroll"><table class="pbb-admin-table"><thead><tr><th>Tahun / objek</th><th>Potensi ketetapan</th><th>Pembayaran</th><th>Sisa ketetapan</th><th>Jatuh tempo terverifikasi</th></tr></thead><tbody>@forelse($yearly as $row)<tr><td><a href="{{ route('admin.tax.index', ['tahun' => $row['year']]) }}">{{ $row['year'] }}</a><small>{{ $row['object_count'] }} objek · {{ $row['verified_count'] }} terverifikasi</small></td><td>Rp{{ number_format($row['potential'], 0, ',', '.') }}</td><td>Rp{{ number_format($row['realized'], 0, ',', '.') }}</td><td>Rp{{ number_format($row['outstanding'], 0, ',', '.') }}</td><td>Rp{{ number_format($row['overdue'], 0, ',', '.') }}</td></tr>@empty<tr><td colspan="5" class="table-empty">Belum ada SPPT per tahun. Total dari API NIK tidak dipecah ke tahun tanpa rincian resmi.</td></tr>@endforelse</tbody></table></div></section>
@endsection
