<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Portal pelayanan terpadu Desa Simare, Kecamatan Borbor, Kabupaten Toba.">
    <link rel="icon" type="image/png" href="{{ asset('images/desaku-logo.png') }}">
    <title>SIMARE — Pelayanan Desa Terpadu</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="topbar"><span class="topbar-live"></span> Prototype layanan digital Desa Simare <span class="topbar-divider">·</span> Data yang tampil adalah data simulasi</div>
    <header class="site-header" id="beranda">
        <a class="brand" href="#beranda" aria-label="SIMARE beranda">
            <span class="brand-logo"><img src="{{ asset('images/desaku-logo.png') }}" alt="Logo DESAKU"></span>
            <span><strong>DESAKU <em>SIMARE</em></strong><small>Terhubung · Tumbuh · Mendunia</small></span>
        </a>
        <button class="menu-button" type="button" aria-label="Buka navigasi" aria-expanded="false">Menu</button>
        <nav class="nav" aria-label="Navigasi utama">
            <a href="#layanan">Layanan</a>
            <a href="{{ route('public.statistics') }}">Data Desa</a>
            <a href="{{ route('public.budget') }}">Transparansi</a>
            <a href="{{ route('public.marketplace') }}">Lapak</a>
            <a class="nav-login" href="{{ route('citizen.login') }}">Layanan Mandiri</a>
        </nav>
    </header>

    <main>
        <section class="hero" data-hero>
            <div class="hero-grid-pattern" aria-hidden="true"></div>
            <div class="aurora aurora-one" aria-hidden="true"></div>
            <div class="aurora aurora-two" aria-hidden="true"></div>
            <div class="hero-ring ring-one" aria-hidden="true"></div>
            <div class="hero-ring ring-two" aria-hidden="true"></div>
            <div class="hero-copy">
                <p class="eyebrow"><span></span> Desa Simare · Kecamatan Borbor</p>
                <h1>Urusan desa sampai kabupaten, <em>cukup dari sini.</em></h1>
                <p class="hero-lead">Ajukan dokumen, pantau kesehatan keluarga, cari program bantuan, cek PBB, dan dukung usaha BUMDes dalam satu layanan yang mudah.</p>
                <div class="hero-actions">
                    <a class="button primary" href="#layanan">Lihat semua layanan <span>→</span></a>
                    <a class="button ghost" href="#alur">Cara mengajukan</a>
                </div>
                <div class="trust-row" data-reveal>
                    <span><b>24/7</b> pengajuan daring</span>
                    <span><b>1</b> nomor pelacakan</span>
                    <span><b>PDP</b> privasi terjaga</span>
                </div>
            </div>
            <div class="hero-panel" id="portal" data-tilt>
                <span class="panel-glow" aria-hidden="true"></span>
                <div class="quick-head">
                    <div><span class="online-dot"></span> Pusat layanan aktif</div>
                    <span>19 Juli 2026</span>
                </div>
                <h2>Apa yang ingin Anda urus?</h2>
                <div class="quick-grid">
                    <a href="{{ route('services.index') }}"><span class="quick-icon teal">ID</span><b>KTP / KK</b><small>Ajukan dokumen</small></a>
                    <a href="{{ route('community-services.show', 'kesehatan') }}"><span class="quick-icon coral">SE</span><b>Kesehatan</b><small>Daftar program</small></a>
                    <a href="{{ route('property-tax.index') }}"><span class="quick-icon gold">PB</span><b>PBB / BPHTB</b><small>Cek tunggakan</small></a>
                    <a href="{{ route('community-services.show', 'bumdes') }}"><span class="quick-icon navy">BM</span><b>BUMDes</b><small>Belanja lokal</small></a>
                    <a href="{{ route('public.ppid') }}"><span class="quick-icon teal">PI</span><b>PPID</b><small>Informasi publik</small></a>
                    <a href="{{ route('public.regulations') }}"><span class="quick-icon gold">JD</span><b>JDIH</b><small>Peraturan desa</small></a>
                </div>
                <button class="track-button" type="button" data-track-toggle>
                    <span>⌕</span><span><small>Sudah mengajukan?</small><b>Lacak status permohonan</b></span><span>→</span>
                </button>
                <form class="track-form" data-track-form action="{{ route('services.track') }}" method="post" hidden>
                    @csrf
                    <label for="tracking">Nomor permohonan</label>
                    <div><input id="tracking" name="tracking" value="{{ old('tracking') }}" placeholder="Contoh: SMR-260719-ABCDE" required><button type="submit">Lacak</button></div>
                    <label for="tracking_phone">Nomor HP pemohon</label>
                    <input id="tracking_phone" name="tracking_phone" value="{{ old('tracking_phone') }}" placeholder="08xxxxxxxxxx" required>
                    @error('tracking')<p>{{ $message }}</p>@enderror
                </form>
            </div>
        </section>

        <section class="ticker" aria-label="Pengumuman">
            <b>PENGUMUMAN</b>
            <div class="ticker-viewport"><div class="ticker-track"><span>Pendaftaran skrining hipertensi dan TB gratis dibuka sampai 31 Juli 2026</span><i>✦</i><span>Layanan pengantar KTP, KK dan surat pindah kini tersedia dalam prototype</span><i>✦</i><span>BUMDes Simare membuka katalog ternak babi lokal</span><i>✦</i><span aria-hidden="true">Pendaftaran skrining hipertensi dan TB gratis dibuka sampai 31 Juli 2026</span></div></div>
            <a href="#kesehatan">Lihat program →</a>
        </section>

        <section class="section" id="layanan" data-reveal>
            <div class="section-heading">
                <div><p class="eyebrow"><span></span> Layanan terpadu</p><h2>Satu pintu untuk kebutuhan warga</h2></div>
                <p>Dirancang agar warga tidak perlu bolak-balik ke kota hanya untuk mengetahui syarat atau status berkas.</p>
            </div>
            <div class="service-grid">
                @foreach ($services as $service)
                    <article class="service-card" data-reveal>
                        <span class="service-icon">{{ $service['icon'] }}</span>
                        <h3>{{ $service['title'] }}</h3>
                        <p>{{ $service['description'] }}</p>
                        @php($serviceLinks = ['ID' => route('services.index'), 'SE' => route('community-services.show', 'kesehatan'), 'BS' => route('community-services.show', 'bantuan-sosial'), 'PB' => route('property-tax.index'), 'BM' => route('community-services.show', 'bumdes'), 'PP' => route('community-services.show', 'program-pemerintah')])
                        <div><small>{{ $service['meta'] }}</small><a href="{{ $serviceLinks[$service['icon']] }}" aria-label="Buka {{ $service['title'] }}">→</a></div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="health-section" id="kesehatan" data-reveal>
            <div class="health-intro">
                <p class="eyebrow light"><span></span> Desa sehat berbasis data</p>
                <h2>Data agregat untuk perhatian yang lebih tepat.</h2>
                <p>Petugas desa dan kesehatan dapat melihat pola risiko tanpa membuka identitas warga ke publik. Kabupaten mendapat gambaran kebutuhan program yang nyata.</p>
                <ul>
                    <li><span>✓</span> Persetujuan warga sebelum pencatatan</li>
                    <li><span>✓</span> Data individu hanya untuk petugas berwenang</li>
                    <li><span>✓</span> Dashboard publik selalu berupa agregat</li>
                </ul>
                <a href="{{ route('community-services.show', 'kesehatan') }}">Daftar program kesehatan <span>→</span></a>
            </div>
            <div class="health-dashboard" data-tilt>
                <div class="dash-head"><div><span>Ringkasan program</span><b>Juli 2026</b></div><span class="status-pill">● Data simulasi</span></div>
                <div class="health-list">
                    @foreach ($healthPrograms as $program)
                        <div class="health-item">
                            <span class="program-dot {{ $program['tone'] }}"></span>
                            <span><b>{{ $program['name'] }}</b><small>Program aktif</small></span>
                            <strong data-counter="{{ $program['count'] }}">0 <small>{{ $program['unit'] }}</small></strong>
                        </div>
                    @endforeach
                </div>
                <div class="attention-card"><span>!</span><div><b>Perlu perhatian kabupaten</b><p>14 warga dengan hasil skrining tekanan darah berisiko tinggi membutuhkan tindak lanjut.</p></div></div>
            </div>
        </section>

        <section class="section tax-section" id="pajak" data-reveal>
            <div class="tax-card">
                <div><p class="eyebrow"><span></span> Potensi PAD</p><h2>PBB & BPHTB lebih transparan</h2><p>Desa membantu validasi objek pajak dan status pembayaran. Nilai berikut hanya contoh tampilan dashboard.</p><a class="tax-check-link" href="{{ route('property-tax.index') }}">Cek tunggakan berdasarkan NIK →</a><br><a class="tax-check-link" href="{{ route('property-tax.potential') }}">Lihat potensi PBB per tahun →</a><br><a class="tax-check-link" href="{{ route('community-services.show', 'bphtb') }}">Ajukan validasi BPHTB →</a></div>
                <div class="tax-metrics">
                    <div><small>Objek pajak terdata</small><b data-counter="1284">0</b><span>bidang</span></div>
                    <div><small>Potensi PBB 2026</small><b>Rp186,4 jt</b><span>estimasi</span></div>
                    <div><small>Realisasi pembayaran</small><b>72,8%</b><span class="positive">↑ 8,4% tahun ini</span></div>
                </div>
            </div>
        </section>

        <section class="section" id="bumdes" data-reveal>
            <div class="section-heading marketplace-heading">
                <div><p class="eyebrow"><span></span> BUMDes Simare</p><h2>Dari kandang desa ke pembeli</h2></div>
                <p>Katalog ternak memuat umur, bobot, vaksinasi, dan asal kandang. Transaksi dicatat agar usaha desa tumbuh profesional.</p>
            </div>
            <div class="product-grid">
                @foreach ($products as $product)
                    <article class="product-card" data-reveal>
                        <div class="product-visual"><span>{{ $product['emoji'] }}</span><small>{{ $product['tag'] }}</small></div>
                        <div class="product-body"><span>BUMDes Simare</span><h3>{{ $product['name'] }}</h3><div><b>{{ $product['price'] }}</b><button type="button" data-cart-add>+ Keranjang</button></div></div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="leaders" id="pemerintah" data-reveal>
            <div class="leaders-copy"><p class="eyebrow light"><span></span> Pemerintahan bersinergi</p><h2>Dari desa, terhubung hingga kabupaten.</h2><p>Informasi pimpinan diverifikasi dari situs resmi pemerintah dan sumber berita yang tercantum.</p></div>
            <div class="leader-grid">
                <article class="leader-card" data-reveal>
                    <div class="leader-photo"><img src="https://inspektorat.tobakab.go.id/wp-content/uploads/sites/11/2025/03/WhatsApp-Image-2025-03-14-at-14.14.49-1-1024x683.jpeg" alt="Bupati dan Wakil Bupati Toba pada pelantikan 2025" loading="lazy"></div>
                    <small>BUPATI TOBA</small><h3>Effendi Sintong P. Napitupulu</h3><a href="https://inspektorat.tobakab.go.id/2025/03/16/bupati-dan-wakil-bupati-toba-2025-2030-resmi-dilantik-presiden-ri/" target="_blank" rel="noreferrer">Sumber: Pemkab Toba ↗</a>
                </article>
                <article class="leader-card" data-reveal>
                    <div class="leader-photo"><img src="https://kecborbor.tobakab.go.id/wp-content/uploads/sites/15/2023/05/camat.jpg" alt="Camat Borbor James Pasaribu" loading="lazy"></div>
                    <small>CAMAT BORBOR</small><h3>James Pasaribu, S.AP., M.M.</h3><a href="https://kecborbor.tobakab.go.id/camat/" target="_blank" rel="noreferrer">Sumber: Kecamatan Borbor ↗</a>
                </article>
                <article class="leader-card pending-photo" data-reveal>
                    <div class="leader-photo"><span>FH</span><em>Foto resmi diperlukan</em></div>
                    <small>KEPALA DESA SIMARE</small><h3>Firman Hutapea</h3><a href="https://www.kiprahkita.com/2023/10/87-kepala-desa-berhasil-dipilih.html?m=1" target="_blank" rel="noreferrer">Sumber hasil Pilkades ↗</a>
                </article>
            </div>
        </section>

        <section class="process section" id="alur" data-reveal>
            <div class="section-heading"><div><p class="eyebrow"><span></span> Proses sederhana</p><h2>Tiga langkah, status selalu jelas</h2></div></div>
            <ol><li><span>01</span><div><b>Pilih layanan</b><p>Baca syarat dan isi formulir dari ponsel atau dibantu operator desa.</p></div></li><li><span>02</span><div><b>Verifikasi desa</b><p>Petugas memeriksa kelengkapan sebelum berkas diteruskan.</p></div></li><li><span>03</span><div><b>Pantau hingga selesai</b><p>Warga menerima nomor pelacakan dan pemberitahuan setiap perubahan.</p></div></li></ol>
        </section>
    </main>

    <footer><div class="footer-brand"><span class="footer-logo"><img src="{{ asset('images/desaku-logo.png') }}" alt="DESAKU — Terhubung, Tumbuh, Mendunia"></span><div><b>DESAKU SIMARE</b><small>Desa Simare, Kecamatan Borbor<br>Kabupaten Toba, Sumatera Utara</small></div></div><div><b>Prototype 2026</b><p>Dirancang mengikuti prinsip UU PDP dan Pedoman Sistem Informasi Desa.</p></div><a href="#beranda">Kembali ke atas ↑</a></footer>
    <div class="toast" role="status" aria-live="polite" data-toast>Produk ditambahkan ke keranjang prototype.</div>
</body>
</html>
