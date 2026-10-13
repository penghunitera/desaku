# Blueprint Fitur DESAKU Berdasarkan Bedah OpenSID

Dokumen ini adalah acuan produk dan implementasi dashboard pengelola DESAKU. Analisis dilakukan terhadap dokumentasi administrasi OpenSID dan kode sumber resmi cabang `umum` pada 19 Juli 2026. Tujuannya bukan menyalin OpenSID, tetapi mengambil kelengkapan domainnya lalu membangun pengalaman kerja yang lebih ringkas untuk perangkat desa.

## 1. Prinsip produk

1. **Satu data induk penduduk.** NIK menjadi penghubung keluarga, rumah tangga, surat, bantuan, kesehatan, PBB, kepemilikan tanah, dan aktivitas layanan.
2. **Profil 360°, bukan banyak daftar terpisah.** Operator dapat membuka satu profil warga dan melihat semua data terkait dalam tab yang dibatasi hak akses.
3. **Form bertahap.** Isian panjang dibagi menjadi bagian pendek, dapat disimpan sebagai draf, dan memiliki indikator kelengkapan.
4. **Semua perubahan dapat ditelusuri.** Perubahan data sensitif, persetujuan, ekspor, dan penghapusan masuk audit log.
5. **Data pribadi tidak menjadi statistik publik.** Dashboard publik hanya menampilkan agregat; NIK, KK, kesehatan, bantuan, dan kontak hanya tersedia sesuai peran.
6. **Modul mengikuti pekerjaan operator.** Navigasi menggunakan istilah kerja: Data Warga, Pelayanan, PBB, Program Desa, Ekonomi Desa, Aset & Pertanahan, Publikasi, dan Pengaturan.

## 2. Struktur dashboard yang disarankan

| Grup navigasi | Halaman utama | Pengguna utama |
|---|---|---|
| Beranda | ringkasan pekerjaan, antrean, data belum lengkap, agenda, target PBB | semua pengelola |
| Data Warga | penduduk, keluarga/KK, rumah tangga, kelompok, peristiwa, calon pemilih | operator kependudukan |
| Pelayanan | permohonan, verifikasi, pembuatan surat, persetujuan, penyerahan, arsip | petugas pelayanan, sekretaris, kepala desa |
| PBB | objek pajak, SPPT tahunan, pembayaran/tunggakan, potensi, rekonsiliasi | kolektor/operator PBB |
| Program Desa | bantuan, kesehatan, pembangunan, pengaduan | kasi terkait |
| Ekonomi Desa | produk/UMKM, pelaku usaha, potensi desa, pesanan | operator BUMDes/ekonomi |
| Aset & Pertanahan | inventaris, persil, C-Desa, mutasi aset/tanah | kaur umum |
| Statistik & Peta | statistik kependudukan, laporan bulanan, kelompok rentan, peta tematik | pimpinan/operator |
| Publikasi | artikel, agenda, galeri, informasi publik/PPID, menu situs | admin web |
| Pengaturan | identitas desa, wilayah, perangkat, pengguna, peran, referensi, template surat | administrator |

## 3. Modul dan rancangan form

### A. Identitas desa, wilayah, dan perangkat

**Layar:** profil desa, struktur Dusun/RW/RT, perangkat desa, lembaga desa, status/indikator desa.

**Form Identitas Desa**

- Nama dan kode wilayah desa, kecamatan, kabupaten, provinsi
- Alamat kantor, kode pos, telepon, email, situs
- Nama kepala desa, sekretaris, masa jabatan
- Logo, foto kantor, lokasi/poligon peta
- Format nomor surat dan zona waktu

**Form Wilayah**

- Jenis wilayah (Dusun/RW/RT), nama, wilayah induk
- NIK/nama kepala wilayah, pejabat sebelumnya
- Lokasi/poligon peta, status aktif

**Form Perangkat/Lembaga**

- Data warga yang menjabat atau data eksternal
- Jabatan, unit/lembaga, nomor SK, tanggal mulai/selesai
- Status aktif, urutan struktur, foto, tanda tangan

### B. Penduduk

OpenSID menyimpan data penduduk sangat lengkap. Di DESAKU, pecah menjadi wizard enam langkah dan tampilkan kolom lanjutan hanya bila relevan.

**1. Identitas**

- NIK, nama lengkap, nomor KK sebelumnya
- Hubungan dalam keluarga, jenis kelamin, agama
- Status penduduk (tetap, pindah, sementara), kepemilikan KTP
- Tempat/tanggal penerbitan KTP

**2. Kelahiran dan orang tua**

- Tempat, tanggal, waktu, lokasi, dan jenis kelahiran
- Nomor akta lahir, anak ke-, penolong kelahiran
- Berat dan panjang lahir
- NIK/nama ayah dan ibu

**3. Alamat dan kontak**

- Dusun, RW, RT, alamat rinci, alamat sebelumnya
- Telepon, email, Telegram, kanal kontak pilihan
- Tujuan tinggal untuk penduduk non-permanen

**4. Pendidikan dan pekerjaan**

- Pendidikan dalam KK, pendidikan sedang ditempuh
- Pekerjaan, status pekerja migran
- Kemampuan baca/tulis

**5. Status sipil dan kewarganegaraan**

- Status perkawinan, nomor/tanggal buku nikah
- Nomor/tanggal akta cerai
- Kewarganegaraan, negara asal
- Paspor dan masa berlaku, KITAS/KITAP
- Suku, marga, wilayah adat

**6. Kesehatan dan catatan** — akses terbatas

- Golongan darah, disabilitas, penyakit kronis
- Kehamilan, kontrasepsi
- Jaminan kesehatan, nomor/status peserta, BPJS Ketenagakerjaan
- Catatan internal

**Fitur daftar penduduk**

- Pencarian NIK/nama/KK/alamat dan filter tersimpan
- Filter umur, tanggal lahir, wilayah, gender, agama, pendidikan, pekerjaan, perkawinan, status penduduk
- Impor Excel dengan pratinjau dan laporan baris gagal
- Ekspor sesuai izin, cetak biodata, label data belum lengkap
- Gabung duplikat, arsipkan, pindah wilayah, catat kematian

### C. Keluarga (KK), rumah tangga, dan kelompok

**Form Keluarga**

- Nomor KK, kepala keluarga, tanggal cetak KK
- Alamat, Dusun/RW/RT, klasifikasi sosial
- Anggota dan hubungan keluarga

**Aksi:** tambah anggota, pindahkan anggota, pecah KK, gabung keluarga, pindah satu keluarga, ganti kepala keluarga, cetak KK internal, lihat bantuan dan riwayat.

**Form Rumah Tangga**

- Nomor rumah tangga, kepala rumah tangga
- Anggota dan hubungan rumah tangga
- Status terdaftar DTKS/DTSEN dan nomor referensi
- Kondisi sosial-ekonomi ringkas

**Form Kelompok**

- Jenis/nama kelompok, pengurus, anggota
- Alamat, periode, SK pembentukan, keterangan

Kelompok mencakup PKK, Karang Taruna, kelompok tani, UMKM, kelompok rentan, dan kelompok khusus desa.

### D. Peristiwa penduduk

**Jenis:** lahir, datang, pindah, meninggal, perubahan status, perubahan data.

**Form umum:** warga/keluarga, jenis peristiwa, tanggal peristiwa dan tanggal lapor, lokasi/tujuan/asal, nomor dokumen pendukung, alasan, lampiran, petugas pencatat.

Setiap peristiwa memperbarui status data induk melalui transaksi dan menyimpan kondisi sebelum/sesudah. Penghapusan peristiwa tidak boleh menghilangkan audit.

### E. Pelayanan surat

**Layar kerja:** antrean permohonan, detail/verifikasi, editor surat, persetujuan, siap diserahkan, arsip, template, persyaratan.

**Form Permohonan**

- Jenis layanan, pemohon dan pihak yang diterangkan
- Keperluan, keterangan tambahan
- Persyaratan per jenis layanan dan unggahan dokumen
- Kanal pengambilan, nomor kontak, persetujuan pemrosesan data

**Alur status**

`Draf → Diajukan → Diverifikasi → Perlu Perbaikan / Disetujui → Ditandatangani → Siap Diambil → Selesai`

**Fitur petugas**

- Validasi otomatis terhadap data penduduk
- Checklist persyaratan dan alasan penolakan/perbaikan
- Template dengan variabel data, pratinjau PDF, nomor surat otomatis
- Paraf/persetujuan berjenjang, tanda tangan elektronik bila tersedia
- Notifikasi status, pencatatan penyerahan, QR verifikasi dokumen
- Arsip dan register surat keluar; klasifikasi surat dinas dipisahkan dari surat warga

### F. PBB — prioritas khas DESAKU

OpenSID memiliki pertanahan, tetapi DESAKU perlu modul PBB tahunan yang lebih operasional.

**Form Objek Pajak**

- NOP, wajib pajak, pemilik/pengguna aktual
- Alamat objek dan Dusun/RW/RT, titik/poligon peta
- Luas bumi, luas bangunan, penggunaan, kelas/NJOP bila tersedia
- Relasi ke persil dan warga/keluarga
- Status aktif, sumber dan tanggal verifikasi data

**Form SPPT/Penetapan Tahunan**

- Tahun pajak, NOP, pokok pajak, tanggal terbit/jatuh tempo
- Nomor SPPT, status distribusi, kolektor
- Sumber impor dan catatan koreksi

**Form Pembayaran**

- Tahun/NOP, tanggal, jumlah bayar, kanal pembayaran
- Nomor bukti, unggahan bukti, petugas verifikasi
- Status lunas/parsial/koreksi

**Dashboard Potensi**

- Potensi bruto per tahun = total penetapan aktif
- Realisasi, sisa/tunggakan, persentase capaian
- Per wilayah, kolektor, kelompok nominal, dan tren tahun
- Warga/KK tanpa relasi NOP, objek tanpa pemilik cocok, NOP duplikat

Catatan: jumlah penduduk bukan dasar penghitungan PBB. Potensi harus dihitung dari objek pajak/SPPT; database penduduk dipakai untuk mencocokkan dan mengelompokkan wajib pajak.

### G. Bantuan sosial dan DTKS/DTSEN

**Form Program**

- Nama dan jenis program, sasaran (penduduk/keluarga/rumah tangga/kelompok)
- Sumber dana, periode, deskripsi, kriteria, status aktif

**Form Peserta**

- Penerima, nomor kartu peserta, foto kartu
- Status pengajuan/verifikasi/penerimaan, periode
- Alasan masuk/keluar dan dokumen pendukung

**Pendataan sosial-ekonomi lanjutan**

- Kepesertaan program keluarga/individu
- Kepemilikan aset dan akses layanan dasar
- Kondisi rumah, sanitasi, listrik, air, pendidikan, pekerjaan
- Isian bersyarat, foto, impor/ekspor, daftar awal/prelist

Simpan versi instrumen karena kolom pendataan pemerintah dapat berubah. Jangan mencampur nilai survei tahun berbeda pada satu kolom profil warga.

### H. Kesehatan

**Layar:** sasaran kesehatan, ibu/anak, imunisasi/vaksin, pemantauan, stunting.

**Form:** warga/sasaran, jenis layanan, tanggal, fasilitas/petugas, pengukuran yang relevan, hasil/status, tindak lanjut, lampiran. Data kesehatan memiliki izin khusus dan tidak terlihat oleh operator umum.

### I. Pembangunan dan potensi desa

**Form Kegiatan Pembangunan**

- Nama, jenis/sifat kegiatan, pelaksana, lokasi/peta
- Volume, waktu, tahun anggaran
- Sumber dana dan pagu: pusat, provinsi, kabupaten, desa, swadaya
- Realisasi, SILPA, progres fisik, manfaat, catatan
- Tahapan/foto progres dan dokumen

**Form Potensi Desa**

- Kategori: alam, pertanian, peternakan, wisata, budaya, SDM, sarana, peluang investasi
- Nama, deskripsi, lokasi/peta, pemilik/pengelola
- Satuan dan kapasitas/produksi per tahun
- Kondisi, legalitas, kebutuhan pengembangan
- Foto/dokumen, status publikasi

Potensi desa bukan hanya artikel; data kuantitatif per tahun diperlukan agar dashboard dapat membandingkan kapasitas, produksi, tenaga kerja, dan nilai ekonomi.

### J. UMKM/BUMDes dan lapak

**Form Pelaku Usaha**

- Pemilik (relasi warga), nama usaha, NIB/legalitas
- Kategori, alamat/lokasi, kontak, status verifikasi

**Form Produk**

- Usaha/pengelola, nama, kategori, deskripsi
- Harga, satuan, stok/ketersediaan, foto
- Nomor kontak/pemesanan, status tayang

**Fitur:** moderasi produk, katalog publik, pencarian/filter, pesanan sederhana, laporan produk aktif dan omzet (bila transaksi dipakai).

### K. Pertanahan dan inventaris

**Persil/C-Desa:** nomor persil dan urut bidang, tipe/kelas dan luas tanah, pemilik awal, lokasi/peta, nomor C-Desa, pemilik tertulis, riwayat rincian dan mutasi.

**Inventaris:** kelompok tanah, gedung, peralatan/mesin, jalan/jaringan, aset tetap lain, konstruksi dalam pengerjaan.

Kolom bersama inventaris: nama/kode/nomor register, tahun pengadaan, lokasi, penggunaan, asal-usul, nilai, kondisi, dokumen kepemilikan, penanggung jawab, foto, dan keterangan. Kendaraan/mesin menambah merek, nomor rangka/mesin/polisi/BPKB; gedung/jalan menambah ukuran, konstruksi, status tanah. Semua aset memiliki riwayat mutasi dan laporan kondisi.

### L. Statistik, peta, publikasi, dan pengaduan

**Statistik:** komposisi penduduk, laporan bulanan, kelahiran/kematian/pindah/datang, kelompok rentan, pendidikan, pekerjaan, kesehatan, bantuan, PBB, dan ekonomi. Setiap angka dapat ditelusuri ke daftar internal, tetapi tampilan publik tetap agregat.

**Peta:** batas wilayah, persebaran penduduk agregat, objek PBB, fasilitas, pembangunan, potensi, usaha, dan aset. Layer ditampilkan sesuai peran.

**Publikasi/PPID:** artikel, kategori, agenda, galeri, menu, dokumen informasi publik, permohonan informasi, klasifikasi serta status publikasi.

**Pengaduan:** kategori, pelapor (boleh anonim sesuai kebijakan), lokasi, uraian, lampiran, disposisi, status, tindak lanjut, SLA, dan notifikasi.

## 4. Peran dan hak akses awal

| Peran | Cakupan utama |
|---|---|
| Super Admin | konfigurasi sistem, seluruh desa, pemulihan data; bukan pekerjaan harian |
| Admin Desa | identitas desa, pengguna, referensi, pengawasan seluruh modul desa |
| Operator Kependudukan | penduduk, KK, rumah tangga, peristiwa; tanpa konfigurasi keamanan |
| Petugas Pelayanan | permohonan dan surat; melihat data warga secukupnya |
| Sekretaris Desa | verifikasi/persetujuan, surat dinas, laporan |
| Kepala Desa | persetujuan akhir, dashboard dan laporan; akses edit terbatas |
| Operator PBB | objek, SPPT, pembayaran, rekonsiliasi dan laporan PBB |
| Operator Program | bantuan, kesehatan atau pembangunan sesuai unit |
| Operator BUMDes | usaha, produk, pesanan, potensi ekonomi |
| Admin Publikasi/PPID | konten publik dan informasi publik; tanpa data sensitif penduduk |
| Kepala Dusun/RT | baca/ubah data yang diizinkan hanya pada wilayahnya |
| Auditor/Pemeriksa | baca dan ekspor laporan sesuai mandat; tanpa edit |

Izin harus berbasis aksi (`lihat`, `buat`, `ubah`, `verifikasi`, `setujui`, `ekspor`, `arsipkan`) dan dapat dibatasi wilayah. Hindari satu peran “pengelola” yang otomatis dapat melihat semua data.

## 5. Pola UX dashboard

- Beranda menampilkan **Pekerjaan Saya**, bukan hanya kartu angka: permohonan menunggu, data belum lengkap, pembayaran belum diverifikasi, kegiatan perlu diperbarui.
- Pencarian global menerima NIK, nama, KK, NOP, nomor surat, atau nomor permohonan dan menampilkan hasil sesuai izin.
- Setiap daftar memakai filter chips, kolom tersimpan, aksi massal aman, impor/ekspor, dan empty state yang memberi arahan.
- Profil warga memakai tab: Ringkasan, Keluarga, Alamat, Peristiwa, Pelayanan, Bantuan, PBB, Dokumen, dan Riwayat. Tab kesehatan hanya muncul bagi peran berizin.
- Form panjang memakai stepper, validasi langsung, draf otomatis, ringkasan sebelum simpan, dan peringatan duplikasi NIK/KK/NOP.
- Detail permohonan memakai timeline dan panel aksi tetap agar petugas tidak kehilangan konteks.
- Desktop dioptimalkan untuk operator; layar ponsel tetap mendukung pencarian, verifikasi, foto lapangan, dan persetujuan.

## 6. Keamanan dan tata kelola data

- Enkripsi NIK, KK, nomor jaminan, dokumen kesehatan, dan berkas sensitif; sediakan indeks pencarian aman/terkendali.
- MFA untuk administrator dan pemberi persetujuan; rate limit, manajemen sesi, serta pencabutan akses pegawai yang tidak aktif.
- Audit log berisi pelaku, waktu, perangkat/IP, aksi, alasan, dan perubahan sebelum/sesudah.
- Ekspor membutuhkan izin tersendiri, dicatat, dibatasi kolom, dan diberi watermark bila berupa dokumen.
- Gunakan arsip/soft delete dan retensi berkas; penghapusan permanen menjadi prosedur khusus.
- Backup terenkripsi dan uji pemulihan berkala.
- Statistik publik memakai ambang agregasi agar kelompok sangat kecil tidak mudah diidentifikasi.

## 7. Tahapan implementasi

### Fase 0 — fondasi dashboard

- Shell dashboard, navigasi, pencarian global
- Organisasi/desa, wilayah, pengguna, peran/izin wilayah
- Referensi data, audit log, penyimpanan lampiran, impor/ekspor

### Fase 1 — operasi inti desa

- Penduduk, KK, rumah tangga, peristiwa
- Profil warga 360°
- Pelayanan surat lengkap dengan verifikasi/persetujuan/arsip
- PBB objek–SPPT–pembayaran–potensi

### Fase 2 — program dan ekonomi

- Bantuan/DTKS-DTSEN, kesehatan terbatas
- Pembangunan dan potensi desa per tahun
- UMKM/BUMDes, katalog produk dan moderasi
- Statistik serta peta dasar

### Fase 3 — tata kelola lengkap

- Pertanahan dan inventaris
- Pengaduan, PPID, publikasi
- Integrasi TTE, notifikasi, API eksternal, anjungan/layanan mandiri

## 8. Keputusan untuk DESAKU

**Diadopsi:** kedalaman data kependudukan, keluarga/rumah tangga, alur surat, sasaran bantuan, inventaris/pertanahan, pembatasan akses wilayah, serta statistik lintas modul.

**Diperbaiki:** form penduduk menjadi wizard, profil warga 360°, antrean berbasis tugas, pencarian global, impor dengan pratinjau, data-quality score, dan dashboard berbasis pekerjaan.

**Tidak langsung dibangun:** CMS situs yang terlalu rinci, anjungan, absensi perangkat, integrasi khusus, dan seluruh instrumen survei nasional. Modul tersebut masuk setelah fondasi dan operasi inti stabil.

## 9. Sumber acuan

- Repositori resmi OpenSID: https://github.com/OpenSID/OpenSID
- Panduan Halaman Administrasi: https://www.panduan.opendesa.id/id/opensid/halaman-administrasi
- Panduan Layanan Surat: https://panduan.opendesa.id/opensid/halaman-administrasi/layanan-surat
- Panduan pendataan DTKS: https://www.panduan.opendesa.id/opensid/halaman-administrasi/kependudukan/rumah-tangga/dtks
