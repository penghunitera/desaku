@extends('services.layout')
@section('title', 'Cek Tunggakan PBB')
@section('content')
<section class="tax-check-shell">
    <div class="tax-check-intro">
        <a href="{{ route('home') }}#pajak">← Kembali ke informasi PBB</a>
        <p class="eyebrow"><span></span> Layanan PBB</p>
        <h1>Cek tunggakan PBB berdasarkan NIK.</h1>
        <p>Masukkan NIK wajib pajak untuk melihat data yang tersedia pada layanan Pajak Bumi dan Bangunan.</p>
        <div class="privacy-note"><b>Privasi data</b><p>NIK hanya dikirim ke layanan PBB untuk pencarian ini dan tidak disimpan oleh DESAKU.</p></div>
        <a class="tax-potential-shortcut" href="{{ route('property-tax.potential') }}">Lihat potensi PBB tahunan desa →</a>
    </div>

    <div class="tax-check-panel">
        <form action="{{ route('property-tax.check') }}" method="post" class="tax-check-form">
            @csrf
            <label for="nik">Nomor Induk Kependudukan (NIK)</label>
            <div class="tax-input-row">
                <input id="nik" name="nik" value="{{ $searchedNik ?? old('nik') }}" inputmode="numeric" autocomplete="off" maxlength="16" pattern="[0-9]{16}" placeholder="Masukkan 16 angka NIK" required>
                <button type="submit">Cek tunggakan</button>
            </div>
            @error('nik')<p class="tax-form-error">{{ $message }}</p>@enderror
        </form>

        @isset($serviceError)
            <div class="tax-alert error"><b>Pencarian belum berhasil</b><p>{{ $serviceError }}</p></div>
        @endisset

        @isset($responseText)
            <section class="tax-result" aria-live="polite">
                <div class="tax-result-head"><div><small>HASIL PENCARIAN</small><h2>Data tunggakan PBB</h2></div><span>Terhubung ke layanan PBB</span></div>
                <p style="white-space: pre-wrap; overflow-wrap: anywhere">{{ $responseText }}</p>
                <p class="tax-result-note">Hasil sesuai respons layanan PBB. Nilai 0 saja tidak memastikan bahwa NIK memiliki objek pajak terdaftar.</p>
            </section>
        @endisset

        @isset($result)
            <section class="tax-result" aria-live="polite">
                <div class="tax-result-head"><div><small>HASIL PENCARIAN</small><h2>Data tunggakan PBB</h2></div><span>Terhubung ke layanan PBB</span></div>
                @php
                    $displayResult = isset($result['data']) && is_array($result['data']) ? $result['data'] : $result;
                @endphp
                @if(count($displayResult))
                    <dl>
                        @foreach($displayResult as $label => $value)
                            <div>
                                <dt>{{ str($label)->replace('_', ' ')->title() }}</dt>
                                <dd>
                                    @if(is_bool($value))
                                        {{ $value ? 'Ya' : 'Tidak' }}
                                    @elseif(is_array($value))
                                        <pre>{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    @else
                                        {{ filled($value) ? $value : '—' }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <div class="tax-empty"><b>Data tidak ditemukan</b><p>Tidak ada data PBB yang dikembalikan untuk NIK tersebut.</p></div>
                @endif
            </section>
        @endisset
    </div>
</section>
@endsection
