@extends('services.layout')
@section('title', 'Potensi PBB Tahunan')
@section('content')
<section class="pbb-potential-hero">
    <div><p class="eyebrow"><span></span> Data pajak wilayah desa</p><h1>Potensi PBB per tahun</h1><p>Ringkasan objek pajak, estimasi ketetapan, realisasi, dan sisa potensi PBB Desa Simare.</p></div>
    <form method="get"><label for="tahun">Tahun pajak</label><select id="tahun" name="tahun" onchange="this.form.submit()">@forelse($availableYears as $availableYear)<option value="{{ $availableYear }}" @selected($year === $availableYear)>{{ $availableYear }}</option>@empty<option>{{ $year }}</option>@endforelse</select></form>
</section>
<section class="pbb-dashboard">
    <div class="pbb-metrics">
        <article><small>OBJEK PAJAK</small><b>{{ number_format($summary['object_count'], 0, ',', '.') }}</b><span>objek terdata</span></article>
        <article><small>POTENSI {{ $year }}</small><b>Rp{{ number_format($summary['potential'], 0, ',', '.') }}</b><span>estimasi ketetapan</span></article>
        <article><small>REALISASI</small><b>Rp{{ number_format($summary['realized'], 0, ',', '.') }}</b><span>{{ $summary['potential'] > 0 ? number_format(($summary['realized'] / $summary['potential']) * 100, 1, ',', '.') : 0 }}% dari potensi</span></article>
        <article><small>BELUM TEREALISASI</small><b>Rp{{ number_format($summary['outstanding'], 0, ',', '.') }}</b><span>potensi tersisa</span></article>
    </div>
    <div class="pbb-grid">
        <section class="pbb-history"><div class="pbb-section-head"><div><small>HISTORI TAHUNAN</small><h2>Potensi dan realisasi</h2></div><span>Data agregat</span></div>
            @forelse($yearly as $row)
                @php($percentage = $row['potential'] > 0 ? min(100, ($row['realized'] / $row['potential']) * 100) : 0)
                <div class="pbb-year-row"><div><b>{{ $row['year'] }}</b><small>{{ $row['object_count'] }} objek</small></div><div class="pbb-bar"><i style="width:{{ $percentage }}%"></i></div><div><b>Rp{{ number_format($row['potential'], 0, ',', '.') }}</b><small>{{ number_format($percentage, 1, ',', '.') }}% terealisasi</small></div></div>
            @empty<div class="tax-empty"><b>Belum ada data tahunan</b><p>Data tampil setelah objek pajak dan estimasi dimasukkan operator.</p></div>@endforelse
        </section>
        <aside class="pbb-resident-check"><small>ESTIMASI MILIK WARGA</small><h2>Cek potensi PBB Anda</h2><p>NIK hanya digunakan untuk pencocokan data dan tidak ditampilkan di dashboard publik.</p>
            <form action="{{ route('property-tax.potential.resident') }}" method="post">@csrf<label>NIK<input name="nik" value="{{ old('nik') }}" inputmode="numeric" maxlength="16" required></label><label>Tahun pajak<input type="number" name="tax_year" value="{{ old('tax_year', $year) }}" min="2000" max="{{ now()->year + 1 }}" required></label><button>Lihat estimasi</button></form>
            @if($errors->any())<div class="tax-alert error">{{ $errors->first() }}</div>@endif
            @isset($residentSearched)
                @if(!$residentFound || $residentAssessment['object_count'] === 0)<div class="pbb-personal-result empty"><b>Data belum ditemukan</b><p>Belum ada estimasi PBB tahun {{ $residentYear }} untuk NIK tersebut.</p></div>
                @else<div class="pbb-personal-result"><small>TOTAL ESTIMASI {{ $residentYear }}</small><b>Rp{{ number_format($residentAssessment['potential'], 0, ',', '.') }}</b><span>{{ $residentAssessment['object_count'] }} objek · Dibayar Rp{{ number_format($residentAssessment['paid'], 0, ',', '.') }}</span></div>@endif
            @endisset
        </aside>
    </div>
    <p class="pbb-disclaimer">Angka berasal dari SPPT atau estimasi yang dientri pengelola dan belum tentu mencakup seluruh objek di desa. Ketetapan resmi tetap mengacu pada Bapenda. PBB bukan otomatis pendapatan APBDes. API pengecekan NIK hanya memberikan total lintas tahun dan tidak dipakai untuk mengisi angka tahunan tanpa rincian resmi.</p>
</section>
@endsection
