# DESAKU / SIMARE

Aplikasi layanan dan informasi desa berbasis Laravel: kependudukan, layanan surat, portal warga, PBB, statistik DPT, informasi publik, BUMDes, dan program TB desa.

## Kebutuhan

- PHP **8.4.1+** untuk dependency yang terkunci di `composer.lock`.
- Composer 2 dan MySQL 8 / MariaDB 10.11.
- Node.js **20.19+ atau 22.12+** untuk build Vite; Node tidak diperlukan saat melayani request.
- Ekstensi PHP: PDO MySQL, mbstring, fileinfo, curl, openssl, DOM/XML, serta ekstensi lain yang diperiksa Composer.

## Pengembangan lokal

```bash
git clone https://github.com/penghunitera/desaku.git
cd desaku
cp .env.example .env
composer install
php artisan key:generate
```

Isi kredensial MySQL lokal di `.env`, lalu jalankan:

```bash
php artisan migrate
npm ci
npm run build
php artisan serve
```

Untuk database pengembangan baru, isi `ADMIN_PASSWORD` dengan password unik minimal 12 karakter sebelum `php artisan db:seed`. Seeder mengisi data contoh dan mempertahankan password admin yang sudah ada. Jangan gunakan seeder untuk mengimpor data production.

```bash
php artisan test
python3 -m unittest discover -s scripts/tests
```

## Hosting di aaPanel

Lihat [panduan deployment aaPanel dan MySQL](docs/AAPANEL_DEPLOYMENT.md) untuk clone pertama dan update melalui `git pull`.

Repositori berisi kode, migration, test, dan konfigurasi contoh. `.env`, database SQLite, data impor DPT, dokumen warga, log, `vendor`, `node_modules`, dan cache runtime tidak masuk Git. Data dan `APP_KEY` dipindahkan melalui jalur privat jika memigrasikan instalasi yang sudah memiliki data terenkripsi.

## Dokumentasi modul

- [Arsitektur](docs/ARCHITECTURE.md)
- [Blueprint fitur](docs/OPENSID_FEATURE_BLUEPRINT.md)
- [Sinkronisasi PBB penduduk](docs/pbb-resident-sync.md)
