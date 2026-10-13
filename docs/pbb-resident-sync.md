# PBB yang terhubung dengan penduduk

Dashboard: `/pengelola/pbb`. Admin desa/operator PBB boleh melakukan sinkronisasi dan entry SPPT; kepala desa/auditor hanya membaca rekap dan mengunduh CSV. Seluruh data dibatasi ke desa akun pengguna. Akun lama tanpa desa memakai SIMARE.

## Kontrak API yang sudah teruji

`GET http://103.139.138.194/Api/tunggakan_pbb_nik` dengan body JSON `{"NIK":"16 angka"}`. Respons aktual yang sudah diuji dengan NIK dummy: `Total Tunggakan: 0`. Parser juga menerima JSON dengan field eksplisit `total_tunggakan` atau `total_outstanding`, di root atau object `data`. Format lain ditandai belum didukung, bukan dianggap nol. Tidak ada bukti endpoint ini menyediakan NOP, tahun, ketetapan, pembayaran, atau lokasi objek.

Total API disimpan sebagai hasil terakhir per NIK, tanpa mengisi/mengubah tabel ketetapan tahunan. Nilai nol tidak membuktikan NIK memiliki objek terdaftar. API berbasis NIK bisa mencakup objek di luar desa; jumlah hasil NIK warga bukan rekap geografis seluruh objek desa. PBB kabupaten/kota tidak otomatis menjadi pendapatan APBDes. Karena endpoint memakai HTTP, NIK belum terlindungi oleh enkripsi transport; gunakan HTTPS/private network dari penyedia sebelum produksi dengan data warga asli.

## Pemakaian

1. Isi NIK penduduk di Data Warga; NIK disimpan terenkripsi.
2. Di PBB Desa, klik **Cek API** per warga atau **Sinkronkan warga aktif**. Massal hanya mencakup warga tetap/sementara dengan NIK, melewati hasil sukses kurang dari 24 jam dan antrean aktif. Maksimal 500 warga per pengiriman; klik lagi untuk warga berikutnya. Batas worker global: 30 permintaan per menit.
3. Pastikan `.env` memakai `QUEUE_CONNECTION=database`, jalankan migrasi dan worker:

```sh
php artisan migrate
php artisan queue:work --timeout=30
```

Gunakan PHP >=8.4.1 untuk dependensi terpasang; di mesin pengembangan ini tersedia `/opt/homebrew/opt/php@8.4/bin/php`. `composer run dev` juga menjalankan listener antrean default. Worker terpisah diperlukan jika hanya memakai `php artisan serve`. Tidak ada sinkronisasi terjadwal otomatis.

4. Muat ulang dashboard untuk status/hasil. Jika gagal, klik **Coba lagi**. Hasil sukses sebelumnya tetap disimpan dengan tanggalnya. Hasil NIK lama tidak dipakai setelah identitas berubah.
5. **Entry SPPT / ketetapan** memilih penduduk terdaftar (tanpa mengetik ulang NIK), NOP 18 angka, tahun, nilai ketetapan, pembayaran tercatat, status verifikasi, dan jatuh tempo. Masukkan hanya objek dalam wilayah desa. NOP disimpan terenkripsi. NOP+tahun yang sama memperbarui ketetapan, bukan membuat duplikasi. Perubahan pemilik NOP yang sudah terhubung ditolak sampai direkonsiliasi.
6. Rekap: potensi = jumlah ketetapan/estimasi; realisasi = pembayaran tercatat; sisa = jumlah `max(ketetapan - pembayaran, 0)` **per objek**. Kelebihan pembayaran objek lain tidak menghapus tunggakan. Tunggakan jatuh tempo hanya sisa SPPT terverifikasi dengan jatuh tempo sebelum hari ini. Belum terdata berbeda dari lunas.
7. Ekspor CSV berisi agregat per tahun tanpa NIK, nama, atau NOP. Dashboard publik hanya menggunakan agregat SPPT/estimasi, bukan total API per warga.

Untuk mengisi angka tahunan otomatis dari API, diperlukan kontrak/rincian resmi minimal NOP, tahun pajak, ketetapan, pembayaran/sisa pokok, dan lokasi objek. Total tunggakan lintas tahun tidak boleh ditebak pembagiannya; denda tidak boleh otomatis disamakan dengan ketetapan pokok.
