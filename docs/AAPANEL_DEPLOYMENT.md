# Deployment DESAKU di aaPanel dengan MySQL

## Situs yang disiapkan

Situs aaPanel bernama `desaku.test` memakai PHP 8.4, port 8088, dan direktori `/www/wwwroot/desaku.test`. Database dan pengguna khusus yang dibuat otomatis oleh aaPanel bernama `sql_desaku_test`, dengan akses localhost. Nama `desaku.test` hanya nama internal situs; alamat uji yang direncanakan adalah `http://103.170.89.151:8088`.

Situs dan database sudah dibuat, tetapi aplikasi belum terpasang. Setelah domain tersedia, bind domain ke situs, pasang SSL, perbarui `APP_URL`, dan set `SESSION_SECURE_COOKIE=true`.

## Clone pertama

Jalankan perintah sebagai pengguna yang memiliki direktori proyek. Terminal aaPanel server ini memakai akun `rsudporsea`; operasi yang membutuhkan root harus dilakukan melalui sesi administrator yang sah. Gunakan hanya direktori DESAKU.

`git clone` memerlukan folder tujuan kosong. Situs baru aaPanel biasanya sudah berisi halaman default dan `.user.ini`. Pindahkan halaman default ke backup lewat Files aaPanel, atau clone ke folder kosong sementara dan pindahkan seluruh hasil clone termasuk `.git` ke folder situs. Pertahankan `.user.ini` milik aaPanel. Jangan melakukan clone pertama ke instalasi yang sudah berisi aplikasi/data.

```bash
git clone --branch main https://github.com/penghunitera/desaku.git /www/wwwroot/desaku.test
cd /www/wwwroot/desaku.test
cp .env.example .env
/www/server/php/84/bin/php /usr/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
```

Untuk repositori private, autentikasi GitHub disiapkan pemilik akun. Jangan menyimpan token di remote URL, script, atau repository.

## Konfigurasi server

Edit `.env` di server. Password database diambil dari aaPanel dan disimpan hanya dalam `.env` server.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=http://103.170.89.151:8088
APP_LOCALE=id
LOG_CHANNEL=daily
LOG_LEVEL=warning
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sql_desaku_test
DB_USERNAME=sql_desaku_test
DB_PASSWORD="isi-password-database-di-server"
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=false
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
```

Untuk database benar-benar baru, jalankan `artisan key:generate` sekali dengan PHP 8.4. Untuk memindahkan data terenkripsi yang sudah ada, salin **APP_KEY lama** secara privat dan jangan membuat kunci baru. Kunci baru membuat NIK/KK lama tidak dapat didekripsi serta mengubah hash pencariannya.

Jangan jalankan `db:seed`, `migrate:fresh`, atau `composer setup` pada database production. Git tidak membawa data lokal. Migrasi SQLite ke MySQL dilakukan terpisah setelah schema tersedia, lalu jumlah baris dan dekripsi identifier diverifikasi. Dokumen pada `storage/app/private` dipindahkan secara privat.

## Build dan migration

```bash
cd /www/wwwroot/desaku.test
/www/server/php/84/bin/php /usr/bin/composer check-platform-reqs --no-dev
npm ci
npm run build
/www/server/php/84/bin/php artisan migrate --force
/www/server/php/84/bin/php artisan optimize
```

Gunakan Node yang memenuhi versi di README. `public/build` dihasilkan saat build dan tidak dilacak Git. Jangan membawa `public/hot` atau menjalankan Vite dev server di production.

PHP-FPM (`www`) harus bisa membaca aplikasi dan menulis `storage` serta `bootstrap/cache`. Batasi akses `.env` dan dokumen privat; hindari permission 777. Jalankan Composer, build, dan Artisan dengan kepemilikan file yang konsisten.

## Website → Conf

- **Directory**: site directory `/www/wwwroot/desaku.test`, running directory `/public`.
- Pertahankan **Anti-XSS / open_basedir**, dengan akses ke root proyek dan `/tmp` agar PHP dapat membaca `vendor` dan `storage`.
- **PHP version**: 8.4.
- **URL rewrite**:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

Port 8088 perlu diizinkan firewall jika belum terbuka. `.env` dan `storage/app/private` tidak boleh tersaji melalui HTTP. Alamat IP bekerja sebagai situs default pada listener 8088 selama tidak ada konfigurasi situs lain pada port itu.

Dengan `QUEUE_CONNECTION=database`, siapkan worker khusus melalui Supervisor aaPanel, pengguna `www`, working directory proyek:

```bash
/www/server/php/84/bin/php /www/wwwroot/desaku.test/artisan queue:work --sleep=3 --tries=3 --timeout=120
```

Worker diperlukan untuk job sinkronisasi PBB melalui queue. `routes/console.php` saat ini belum mendefinisikan jadwal aplikasi.

## Update melalui Git

Backup database dan file privat terlebih dahulu. Periksa perubahan lokal sebelum pull; pertahankan `.env` dan data.

```bash
cd /www/wwwroot/desaku.test
git status --short
git pull --ff-only origin main
/www/server/php/84/bin/php /usr/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
/www/server/php/84/bin/php artisan migrate --force
/www/server/php/84/bin/php artisan optimize
/www/server/php/84/bin/php artisan queue:restart
git rev-parse HEAD
```

Periksa `/up`, halaman utama, aset, login pengelola, dan akses MySQL setelah update. SHA Git membuktikan versi source; build, migration, worker, dan perilaku HTTP perlu diverifikasi terpisah.
