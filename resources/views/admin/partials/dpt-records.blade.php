<article id="dpt-list" class="admin-panel dpt-record-panel" aria-labelledby="dpt-list-heading">
    <div class="panel-head"><div><small>DATA PEMILIH</small><h3 id="dpt-list-heading">Daftar DPT · {{ $dpt['label'] }}</h3></div>
        @if($dpt['records'])<span>{{ number_format($dpt['records']->total(), 0, ',', '.') }} hasil dari {{ number_format($dpt['summary']['total'], 0, ',', '.') }} DPT desa</span>@endif
    </div>
    @if(! $dpt['canViewRecords'])
        <p class="dpt-list-notice">Daftar nama DPT tersedia untuk pengelola yang memiliki akses Data Warga.</p>
    @elseif($dpt['selectedVillage'] === '')
        <p class="dpt-list-notice">Pilih satu desa / kelurahan pada filter statistik untuk menampilkan daftar DPT.</p>
    @else
        <form class="dpt-record-filter" action="{{ route('admin.dashboard') }}#dpt-list" method="get">
            <input type="hidden" name="dpt_kecamatan" value="{{ $dpt['selectedDistrict'] }}">
            <input type="hidden" name="dpt_desa" value="{{ $dpt['selectedVillage'] }}">
            <div class="dpt-list-fields">
                <label for="dpt-name-search">Pencarian nama<input id="dpt-name-search" name="dpt_nama" type="search" maxlength="120" placeholder="Ketik nama atau sebagian nama" value="{{ $dpt['recordFilters']['dpt_nama'] ?? '' }}"></label>
                <label for="dpt-age-min">Usia minimal<input id="dpt-age-min" name="dpt_usia_min" type="number" min="0" max="120" step="1" placeholder="Contoh: 17" value="{{ $dpt['recordFilters']['dpt_usia_min'] ?? '' }}"></label>
                <label for="dpt-age-max">Usia maksimal<input id="dpt-age-max" name="dpt_usia_max" type="number" min="0" max="120" step="1" placeholder="Contoh: 64" value="{{ $dpt['recordFilters']['dpt_usia_max'] ?? '' }}"></label>
                <label for="dpt-list-gender">Jenis kelamin<select id="dpt-list-gender" name="dpt_gender">
                    <option value="">Semua jenis kelamin</option>
                    <option value="L" @selected(($dpt['recordFilters']['dpt_gender'] ?? '') === 'L')>Laki-laki</option>
                    <option value="P" @selected(($dpt['recordFilters']['dpt_gender'] ?? '') === 'P')>Perempuan</option>
                    <option value="unknown" @selected(($dpt['recordFilters']['dpt_gender'] ?? '') === 'unknown')>Belum valid</option>
                </select></label>
            </div>
            <div class="dpt-list-filter-actions"><p>Filter ini menyaring daftar DPT. Statistik di atas tetap merangkum desa terpilih.</p><div><a class="button secondary" href="{{ route('admin.dashboard', ['dpt_kecamatan' => $dpt['selectedDistrict'], 'dpt_desa' => $dpt['selectedVillage']]) }}#dpt-list">Reset pencarian</a><button class="button primary" type="submit">Cari DPT</button></div></div>
        </form>
        <div class="dpt-table-scroll"><table class="dpt-table dpt-record-table"><caption class="dpt-sr-only">Daftar nama DPT {{ $dpt['label'] }}</caption><thead><tr><th scope="col">No.</th><th scope="col">Nama</th><th scope="col">Usia</th><th scope="col">Jenis kelamin</th><th scope="col">TPS</th></tr></thead><tbody>
            @forelse($dpt['records'] as $record)
                <tr><td>{{ number_format($dpt['records']->firstItem() + $loop->index, 0, ',', '.') }}</td><th scope="row">{{ $record->nama ?: 'Nama belum tersedia' }}</th><td>{{ $record->usia_tercatat === null ? 'Belum valid' : $record->usia_tercatat.' tahun' }}</td><td>{{ match($record->jenis_kelamin) { 'L' => 'Laki-laki', 'P' => 'Perempuan', default => 'Belum valid' } }}</td><td>{{ $record->tps ?? 'Belum tersedia' }}</td></tr>
            @empty
                <tr><td colspan="5" class="dpt-list-empty">Tidak ada DPT pada halaman ini. Coba ubah filter atau kembali ke halaman pertama.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="dpt-list-footer"><p>Menampilkan {{ number_format($dpt['records']->firstItem() ?? 0, 0, ',', '.') }}–{{ number_format($dpt['records']->lastItem() ?? 0, 0, ',', '.') }} dari {{ number_format($dpt['records']->total(), 0, ',', '.') }} hasil · 25 baris per halaman</p>
            @if($dpt['records']->hasPages())<nav class="dpt-pagination" aria-label="Halaman daftar DPT">
                @if($dpt['records']->onFirstPage())<span aria-disabled="true">← Sebelumnya</span>@else<a href="{{ $dpt['records']->previousPageUrl() }}" rel="prev">← Sebelumnya</a>@endif
                <span>Halaman {{ $dpt['records']->currentPage() }} / {{ $dpt['records']->lastPage() }}</span>
                @if($dpt['records']->hasMorePages())<a href="{{ $dpt['records']->nextPageUrl() }}" rel="next">Berikutnya →</a>@else<span aria-disabled="true">Berikutnya →</span>@endif
            </nav>@endif
        </div>
    @endif
</article>
