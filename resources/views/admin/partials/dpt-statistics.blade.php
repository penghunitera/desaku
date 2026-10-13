@push('styles')
    @vite('resources/css/admin-dpt.css')
@endpush
<section id="dpt" class="dpt-dashboard" aria-labelledby="dpt-heading">
    <div class="dpt-heading">
        <div><small>DEMOGRAFI DESA</small><h2 id="dpt-heading">Potensi SDM dari DPT</h2><p>Lihat komposisi usia dan jenis kelamin pada desa pilihan.</p></div>
        <span class="dpt-source-badge">Data sumber DPT</span>
    </div>
    @if(! $dpt['available'])
        <div class="admin-panel empty-workspace"><h3>Data DPT belum tersedia</h3><p>Ringkasan akan tampil setelah data DPT diimpor ke database desa.</p></div>
    @else
        <form class="dpt-filter" action="{{ route('admin.dashboard') }}#dpt" method="get" data-dpt-villages="{{ $dpt['villages']->toJson() }}">
            <label for="dpt-kecamatan">Kecamatan
                <select id="dpt-kecamatan" name="dpt_kecamatan">
                    <option value="">Semua kecamatan</option>
                    @foreach($dpt['districts'] as $district)
                        <option value="{{ $district }}" @selected($dpt['selectedDistrict'] === $district)>{{ str($district)->title() }}</option>
                    @endforeach
                </select>
            </label>
            <label for="dpt-desa">Desa / kelurahan
                <select id="dpt-desa" name="dpt_desa">
                    <option value="">Semua desa / kelurahan</option>
                    @foreach($dpt['villages'] as $village)
                        <option value="{{ $village['key'] }}" @selected($dpt['selectedVillage'] === $village['key'])>{{ str($village['name'])->title() }} · {{ str($village['district'])->title() }}</option>
                    @endforeach
                </select>
            </label>
            <button class="button primary" type="submit">Tampilkan statistik</button>
        </form>
        <div class="dpt-scope"><h3>{{ $dpt['label'] }}</h3><span>{{ number_format($dpt['summary']['villages'], 0, ',', '.') }} desa / kelurahan dalam filter</span></div>
        <div class="dpt-metrics">
            <article><small>JUMLAH DPT</small><b data-dpt-stat="total">{{ number_format($dpt['summary']['total'], 0, ',', '.') }}</b><span>catatan pemilih pada sumber</span></article>
            <article class="dpt-metric-highlight"><small>USIA 15–64 TAHUN</small><b data-dpt-stat="working-age">{{ number_format($dpt['summary']['working_age'], 0, ',', '.') }}</b><span>{{ number_format($dpt['summary']['working_age_percent'], 1, ',', '.') }}% dari DPT terpilih</span></article>
            <article><small>USIA 65 TAHUN KE ATAS</small><b>{{ number_format($dpt['summary']['senior'], 0, ',', '.') }}</b><span>{{ number_format($dpt['summary']['senior_percent'], 1, ',', '.') }}% dari DPT terpilih</span></article>
            <article><small>TPS TERDATA</small><b>{{ number_format($dpt['summary']['tps'], 0, ',', '.') }}</b><span>dihitung pada tiap desa</span></article>
        </div>
        <div class="dpt-chart-grid">
            <article class="admin-panel dpt-gender-panel">
                <div class="panel-head"><div><small>KOMPOSISI DPT</small><h3>Jenis kelamin</h3></div></div>
                @php
                    $maleAngle = $dpt['summary']['total'] ? $dpt['summary']['male'] / $dpt['summary']['total'] * 360 : 0;
                    $femaleAngle = $dpt['summary']['total'] ? ($dpt['summary']['male'] + $dpt['summary']['female']) / $dpt['summary']['total'] * 360 : 0;
                @endphp
                <div class="dpt-donut" style="--male-angle:{{ $maleAngle }}deg;--female-angle:{{ $femaleAngle }}deg" aria-hidden="true"><div><b>{{ number_format($dpt['summary']['total'], 0, ',', '.') }}</b><span>jumlah DPT</span></div></div>
                <ul class="dpt-gender-legend">
                    @foreach($dpt['genders'] as $gender)
                        @if($gender['key'] !== 'unknown_gender' || $gender['total'] > 0)
                            <li><span><i class="dpt-dot {{ $gender['key'] }}"></i>{{ $gender['label'] }}</span><div><b>{{ number_format($gender['total'], 0, ',', '.') }}</b><small>{{ number_format($gender['percent'], 1, ',', '.') }}%</small></div></li>
                        @endif
                    @endforeach
                </ul>
            </article>
            <article class="admin-panel dpt-age-panel">
                <div class="panel-head"><div><small>USIA PADA SUMBER DATA</small><h3>Sebaran kelompok usia</h3></div><span class="dpt-chart-unit">Jumlah DPT</span></div>
                <div class="dpt-age-bars">
                    @foreach($dpt['ages'] as $age)
                        <div class="dpt-age-row"><span>{{ $age['label'] }}</span><div class="dpt-age-track" aria-hidden="true"><i style="width:{{ $dpt['ages']->max('total') ? $age['total'] / $dpt['ages']->max('total') * 100 : 0 }}%" class="{{ $age['key'] === 'unknown' ? 'unknown' : '' }}"></i></div><b>{{ number_format($age['total'], 0, ',', '.') }}</b><small>{{ number_format($age['percent'], 1, ',', '.') }}%</small></div>
                    @endforeach
                </div>
                <p class="dpt-chart-note">Persentase dihitung terhadap seluruh DPT pada filter ini, termasuk usia yang belum valid.</p>
            </article>
        </div>
        <article class="dpt-insight"><span>SDM</span><div><h3>{{ number_format($dpt['summary']['young_adults'], 0, ',', '.') }} DPT berada pada usia 15–34 tahun</h3><p>Kelompok ini mencakup {{ number_format($dpt['summary']['young_adults_percent'], 1, ',', '.') }}% DPT terpilih. Rentang usia membantu menyusun sasaran program desa; keterampilan dan status bekerja masih memerlukan pendataan.</p></div></article>
        <article class="admin-panel dpt-detail-panel">
            <div class="panel-head"><div><small>RINCIAN DEMOGRAFI</small><h3>Kelompok usia menurut jenis kelamin</h3></div><span>{{ number_format($dpt['summary']['unknown_age'], 0, ',', '.') }} usia belum valid</span></div>
            <div class="dpt-table-scroll"><table class="dpt-table"><caption class="dpt-sr-only">Rincian DPT {{ $dpt['label'] }} menurut usia dan jenis kelamin</caption><thead><tr><th scope="col">Kelompok usia</th><th scope="col">Laki-laki</th><th scope="col">Perempuan</th><th scope="col">Gender belum valid</th><th scope="col">Total</th><th scope="col">Persentase</th></tr></thead><tbody>
                @foreach($dpt['ages'] as $age)
                    <tr><th scope="row">{{ $age['label'] }}</th><td>{{ number_format($age['male'], 0, ',', '.') }}</td><td>{{ number_format($age['female'], 0, ',', '.') }}</td><td>{{ number_format($age['unknown'], 0, ',', '.') }}</td><td><b>{{ number_format($age['total'], 0, ',', '.') }}</b></td><td>{{ number_format($age['percent'], 1, ',', '.') }}%</td></tr>
                @endforeach
            </tbody><tfoot><tr><th scope="row">Total DPT</th><td>{{ number_format($dpt['summary']['male'], 0, ',', '.') }}</td><td>{{ number_format($dpt['summary']['female'], 0, ',', '.') }}</td><td>{{ number_format($dpt['summary']['unknown_gender'], 0, ',', '.') }}</td><td><b>{{ number_format($dpt['summary']['total'], 0, ',', '.') }}</b></td><td>{{ $dpt['summary']['total'] ? '100,0' : '0,0' }}%</td></tr></tfoot></table></div>
        </article>
        <div class="dpt-data-note"><b>Tentang data ini</b><p>DPT mencakup catatan pemilih, belum mewakili seluruh penduduk desa. Usia mengikuti data sumber dan tanggal acuan usia belum tersedia. Baris judul dikecualikan; usia tidak valid tetap masuk jumlah DPT, tanpa dimasukkan ke rentang usia. Potensi duplikat belum diverifikasi.</p>
            @if($dpt['source']?->source_generated_at)<small>Sumber: DPT Toba · Ekspor {{ \Carbon\Carbon::parse($dpt['source']->source_generated_at)->locale('id')->translatedFormat('d F Y') }}.</small>@endif
        </div>
        @include('admin.partials.dpt-records')
    @endif
</section>
@if($dpt['available'])
    @push('scripts')
        @vite('resources/js/admin-dpt.js')
    @endpush
@endif
