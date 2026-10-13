# Deployment DESAKU di aaPanel dengan MySQL

## Situs yang disiapkan

Situs aaPanel bernama `desaku.test` memakai PHP 8.4 dan direktori `/www/wwwroot/desaku.test`. Database dan pengguna khusus yang dibuat otomatis oleh aaPanel bernama `sql_desaku_test`, dengan akses localhost. Alamat publik adalah `http://103.170.89.151/desaku/`, memakai port 80 yang sudah terbuka. Port 8088 hanya listener internal `127.0.0.1:8088`; tidak perlu meminta pembukaan port ke penyedia server.

Aplikasi terpasang pada 13 Oktober 2026 dengan PHP 8.4.14, MariaDB 10.11.10, dan build Vite production. Snapshot lokal telah diimpor: 24 tabel bisnis, 150.691 baris, 150.677 baris DPT, dan 4 dokumen privat. Checksum DPT, rekap 240 wilayah, enkripsi identifier, dan hash akun terverifikasi. Tiga baris judul DPT tetap disimpan tetapi dikecualikan dari statistik. Akun menggunakan password yang sama dengan instalasi lokal.

Setelah domain tersedia, bind domain ke situs, pasang SSL, perbarui `APP_URL`, dan set `SESSION_SECURE_COOKIE=true`. Alamat uji saat ini masih menggunakan HTTP.

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
APP_URL=http://103.170.89.151/desaku
ASSET_URL=http://103.170.89.151/desaku
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
SESSION_PATH=/desaku
SESSION_COOKIE=desaku_session
CACHE_STORE=database
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=180
FILESYSTEM_DISK=local
```

Untuk database benar-benar baru, jalankan `artisan key:generate` sekali dengan PHP 8.4. Untuk memindahkan data terenkripsi yang sudah ada, salin **APP_KEY lama** secara privat dan jangan membuat kunci baru. Kunci baru membuat NIK/KK lama tidak dapat didekripsi serta mengubah hash pencariannya.

Jangan jalankan `db:seed`, `migrate:fresh`, atau `composer setup` pada database production. Git tidak membawa data lokal. Migrasi SQLite ke MySQL dilakukan terpisah setelah schema tersedia, lalu jumlah baris dan dekripsi identifier diverifikasi. Dokumen pada `storage/app/private` dipindahkan secara privat.

## Build dan migration

Migration MySQL menyiapkan tabel sumber DPT dan view statistik dengan aturan validasi usia yang sama seperti impor SQLite. Baris sumber DPT tetap dipindahkan secara privat; migration tidak mengisi atau mengganti data warga.

```bash
cd /www/wwwroot/desaku.test
/www/server/php/84/bin/php /usr/bin/composer check-platform-reqs --no-dev
export PATH=/www/server/desaku-node/v22.23.3/bin:$PATH
npm ci
npm run build
/www/server/php/84/bin/php artisan migrate --force
/www/server/php/84/bin/php artisan optimize
```

Node 22.23.3 untuk DESAKU dipasang terpisah di `/www/server/desaku-node/v22.23.3`; Node aplikasi lain tidak diganti. `public/build` dihasilkan saat build dan tidak dilacak Git. Jangan membawa `public/hot` atau menjalankan Vite dev server di production.

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

Konfigurasi `ops/nginx-desaku-path.conf` di-include hanya pada server block IP port 80, melalui `/www/server/panel/vhost/nginx/extension/103.170.89.151/desaku-path.conf`. File virtual host `/www/server/panel/vhost/nginx/103.170.89.151.conf` mendapat satu directive include; location aplikasi lain tetap dipertahankan. Virtual host DESAKU mendengarkan `127.0.0.1:8088`.

Proxy menghapus prefix `/desaku/` pada request upstream dan mengirim `X-Forwarded-Prefix: /desaku`. Laravel hanya mempercayai proxy `127.0.0.1`. Tautan, aset, redirect login, dan cookie sesi memakai jalur DESAKU. `.env` dan `storage/app/private` tidak boleh tersaji melalui HTTP. Setelah bind domain sendiri, sesuaikan kembali APP_URL, ASSET_URL, dan SESSION_PATH dengan lokasi situs.

`ops/aapanel-path.py` memasang konfigurasi tersebut dengan backup privat dan `nginx -t` sebelum reload. Helper membandingkan respons root dan entry SIMRS sebelum/sesudah, serta memeriksa prefix login dan path cookie. Gunakan hanya pada server yang sesuai; jangan menyalin konfigurasi aplikasi lain atau mengubah root/PHP handler mereka.

Dengan `QUEUE_CONNECTION=database`, server ini memakai unit systemd khusus `desaku-queue.service`, pengguna `www`, working directory proyek. Konfigurasi berada di `ops/desaku-queue.service`, dengan perintah worker:

```bash
/www/server/php/84/bin/php /www/wwwroot/desaku.test/artisan queue:work --sleep=3 --tries=3 --timeout=120
```

Worker diperlukan untuk job sinkronisasi PBB melalui queue. `routes/console.php` saat ini belum mendefinisikan jadwal aplikasi.

Periksa worker melalui sesi administrator:

```bash
systemctl is-active desaku-queue.service
systemctl is-enabled desaku-queue.service
systemctl status desaku-queue.service --no-pager
```

`DB_QUEUE_RETRY_AFTER=180` lebih panjang daripada timeout worker 120 detik. Unit berjalan otomatis saat boot dan restart bila proses berhenti. Tidak ada jadwal sinkronisasi PBB otomatis; pengiriman tetap mengikuti tindakan pengelola.

## Snapshot privat

Paket dan log impor disimpan di direktori root terbatas `/tmp/desaku-staging-20261013` dan `/tmp/desaku-deploy-work-20261013`, di luar document root. `ops/aapanel-import.py` hanya untuk impor snapshot yang telah disetujui ke tabel bisnis kosong; marker keberhasilan mencegah pengulangan. Jangan menggunakannya untuk update rutin.

`ops/aapanel-finalize.py` memeriksa marker impor, mengaktifkan worker khusus, memverifikasi file, dan menguji respons HTTP. Helper tidak membawa password database, APP_KEY, NIK, atau isi dokumen ke Git.

## Update melalui Git

Backup database dan file privat terlebih dahulu. Periksa perubahan lokal sebelum pull; pertahankan `.env` dan data.

```bash
cd /www/wwwroot/desaku.test
git status --short
git pull --ff-only origin main
/www/server/php/84/bin/php /usr/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
export PATH=/www/server/desaku-node/v22.23.3/bin:$PATH
npm ci
npm run build
/www/server/php/84/bin/php artisan migrate --force
/www/server/php/84/bin/php artisan optimize
/www/server/php/84/bin/php artisan queue:restart
git rev-parse HEAD
```

Periksa `/up`, halaman utama, aset, login pengelola, dan akses MySQL setelah update. SHA Git membuktikan versi source; build, migration, worker, dan perilaku HTTP perlu diverifikasi terpisah.
