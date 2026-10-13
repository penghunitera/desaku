# Arsitektur SIMARE

SIMARE memakai **modular monolith multi-tenant**. Satu deployment Laravel dan satu klaster MySQL melayani banyak desa, tetapi setiap data bisnis wajib memiliki `village_id` dan setiap akses melewati tenant scope.

## Batas modul

- Platform: tenant, pengguna, peran, persetujuan, dokumen, notifikasi, audit.
- Kependudukan: data referensi warga dan prototipe pengajuan KTP-el, KK, surat pindah.
- Kesehatan populasi: program, skrining, risiko, tindak lanjut, agregat kabupaten. Modul ini bukan EMR klinis.
- Pendapatan daerah: objek PBB, pembayaran, estimasi BPHTB, potensi PAD.
- BUMDes: katalog, profil ternak, stok, pesanan, pembayaran, serah terima.
- Program pemerintah: program, persyaratan, pendaftaran, verifikasi, hasil.

## Open API

API diberi versi (`/api/v1`) dan menggunakan OAuth client per mitra. Scope minimum: `residents:read`, `services:write`, `health-aggregate:read`, `tax-summary:read`, dan `commerce:write`. Data individu tidak boleh tersedia melalui API agregat.

## Jalan menuju microservices

Ekstraksi hanya dilakukan bila ada alasan operasional terukur. Kandidat pertama adalah notifikasi, marketplace BUMDes, atau gateway integrasi pemerintah. Modul berkomunikasi melalui service contract dan domain event agar proses ekstraksi tidak mengubah UI serta aturan inti.

## PDP

- NIK/NOP disimpan terenkripsi dan memiliki hash deterministik untuk pencarian duplikat.
- Semua akses data pribadi memiliki audit log.
- Statistik publik hanya agregat dan menerapkan ambang minimum kelompok.
- Dokumen memiliki tujuan, dasar pemrosesan, consent bila relevan, masa retensi, dan jadwal penghapusan.
- Data kesehatan hanya dapat diakses petugas dengan kewenangan khusus.
