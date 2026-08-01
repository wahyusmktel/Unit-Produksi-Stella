# Deployment Unit Produksi dan SSO SISFO

## 1. Registrasi aplikasi pada SISFO

1. Masuk ke SISFO sebagai **Super Admin**.
2. Buka menu **Aplikasi SSO** lalu pilih **Tambah Aplikasi**.
3. Isi nama `Unit Produksi SMK Telkom Lampung`.
4. Pilih jenis client **Public + PKCE**. Aplikasi ini tidak memakai client secret.
5. Isi redirect URI produksi secara persis:

   ```text
   https://up.smktelkom-lpg.id/auth/sisfo/callback
   ```

6. Aktifkan aplikasi dan simpan nilai **Client ID** yang dihasilkan.
7. Untuk pengembangan lokal, tambahkan redirect URI berikut jika form mendukung lebih dari satu URI:

   ```text
   http://127.0.0.1:8000/auth/sisfo/callback
   ```

Scope yang diminta aplikasi adalah `profile:read`. Aplikasi menyimpan profil dasar dan role pengguna pada database lokal, tetapi tidak menyimpan access token SISFO setelah proses login selesai.

## 2. Persiapan project di server SISFO

```bash
cd /var/www
sudo git clone git@github.com:wahyusmktel/Unit-Produksi-Stella.git
sudo chown -R wahyurah55:www-data /var/www/Unit-Produksi-Stella
cd /var/www/Unit-Produksi-Stella
cp .env.example .env
composer install --no-dev --optimize-autoloader
php artisan key:generate
```

Project mengunci resolusi dependency Composer ke platform PHP 8.3. Jangan menjalankan `composer update` di server produksi. File `composer.lock` dari repository sudah disiapkan agar kompatibel dengan PHP 8.3.

Siapkan database MySQL terpisah untuk aplikasi Unit Produksi. Masuk melalui akun administratif MySQL di Ubuntu:

```bash
sudo mysql
```

Jangan menggunakan perintah `mysql` tanpa `sudo` untuk tahap ini karena perintah tersebut dapat masuk sebagai user MySQL biasa yang tidak memiliki izin membuat database. Setelah prompt berubah menjadi `mysql>`, jalankan:

```sql
CREATE DATABASE unit_produksi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'unit_produksi'@'localhost' IDENTIFIED BY 'GANTI_PASSWORD_KUAT';
GRANT ALL PRIVILEGES ON unit_produksi.* TO 'unit_produksi'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Uji akun database aplikasi sebelum melanjutkan:

```bash
mysql -u unit_produksi -p unit_produksi
```

Konfigurasi penting `.env` produksi:

```dotenv
APP_NAME="Unit Produksi SMK Telkom Lampung"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://up.smktelkom-lpg.id
ASSET_URL=https://up.smktelkom-lpg.id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=unit_produksi
DB_USERNAME=unit_produksi
DB_PASSWORD=GANTI_PASSWORD_KUAT

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

SISFO_SSO_URL=https://sso.smktelkom-lpg.id
SISFO_SSO_CLIENT_ID=CLIENT_ID_DARI_SISFO
SISFO_SSO_REDIRECT_URI=https://up.smktelkom-lpg.id/auth/sisfo/callback
SISFO_SSO_SCOPE=profile:read
```

Lanjutkan instalasi:

```bash
php artisan migrate --force
npm ci
npm run build
php artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
php artisan optimize
```

## 3. Virtual host Nginx

Buat file baru agar aplikasi Unit Produksi tidak tercampur dengan virtual host SISFO:

```bash
sudo nano /etc/nginx/sites-available/unit-produksi
```

Isi:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name up.smktelkom-lpg.id;

    root /var/www/Unit-Produksi-Stella/public;
    index index.php;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

Aktifkan dan uji:

```bash
sudo ln -s /etc/nginx/sites-available/unit-produksi /etc/nginx/sites-enabled/unit-produksi
sudo nginx -t
sudo systemctl reload nginx
curl -I -H "Host: up.smktelkom-lpg.id" http://127.0.0.1
```

Respons `200` atau `302` menandakan virtual host sudah dikenali.

Karena aplikasi berada di belakang Cloudflare Tunnel, Laravel telah dikonfigurasi untuk memercayai forwarded proxy headers. `ASSET_URL` produksi tetap diisi HTTPS agar URL CSS, JavaScript, dan font tidak pernah dihasilkan memakai HTTP.

Virtual host SISFO yang melayani `sso.smktelkom-lpg.id` juga wajib meneruskan bearer token ke PHP-FPM. Tambahkan baris berikut di dalam blok `location ~ \.php$` milik SISFO:

```nginx
fastcgi_param HTTP_AUTHORIZATION $http_authorization;
```

Tanpa parameter tersebut, endpoint `https://sso.smktelkom-lpg.id/api/sso/user` akan menerima request tanpa token dan membalas `401 Unauthenticated` meskipun proses `/oauth/token` berhasil.

## 4. Subdomain melalui Cloudflare Tunnel

Periksa dahulu cara tunnel dijalankan:

```bash
sudo systemctl cat cloudflared | grep ExecStart
```

### Tunnel lokal dengan `config.yml`

Tambahkan ingress sebelum rule `http_status:404` pada `/etc/cloudflared/config.yml`:

```yaml
  - hostname: up.smktelkom-lpg.id
    service: http://127.0.0.1:80
```

Buat DNS dari Ubuntu, validasi, lalu restart:

```bash
cloudflared tunnel route dns NAMA_ATAU_UUID_TUNNEL up.smktelkom-lpg.id
cloudflared tunnel ingress validate
cloudflared tunnel ingress rule https://up.smktelkom-lpg.id
sudo systemctl restart cloudflared
sudo systemctl status cloudflared --no-pager
```

Perintah DNS membutuhkan `cert.pem` Cloudflare pada server. Jika belum ada, jalankan `cloudflared tunnel login` dengan akun Cloudflare yang berwenang.

### Tunnel remote berbasis `--token`

Jika `ExecStart` memuat `--token`, ingress tidak dibaca dari `config.yml`. Tambahkan **Published application route** melalui Cloudflare Zero Trust:

```text
Hostname: up.smktelkom-lpg.id
Service:  http://127.0.0.1:80
```

DNS dan pemetaan origin akan dibuat oleh Cloudflare. Jangan memasang Cloudflare Access di depan callback OAuth kecuali kebijakan Access memang dirancang untuk seluruh pengguna SSO.

## 5. Script deployment

Salin script project ke home user deployment dan jadikan executable:

```bash
sudo cp /var/www/Unit-Produksi-Stella/deploy_up.sh /home/wahyurah55/deploy_up.sh
sudo chown wahyurah55:wahyurah55 /home/wahyurah55/deploy_up.sh
chmod +x /home/wahyurah55/deploy_up.sh
```

Deployment berikutnya cukup dijalankan dengan:

```bash
/home/wahyurah55/deploy_up.sh
```

Script menggunakan `npm ci` agar versi dependency mengikuti `package-lock.json`, `git pull --ff-only` agar deployment berhenti jika server memiliki perubahan lokal, dan trap untuk selalu keluar dari maintenance mode.

## 6. Verifikasi akhir

```bash
curl -I https://up.smktelkom-lpg.id
curl -I https://up.smktelkom-lpg.id/login
curl -I https://sso.smktelkom-lpg.id/masuk
```

Buka `https://up.smktelkom-lpg.id/login`, pilih **Masuk dengan SISFO**, setujui akses, dan pastikan callback kembali ke dashboard Unit Produksi.

## 7. Aktivasi role adminup dan katalog produk

Role `adminup` bersumber dari SISFO dan dikirim ke aplikasi Unit Produksi melalui profil SSO. Setelah kode SISFO terbaru selesai di-deploy, buat role tersebut dengan:

```bash
cd /var/www/Kesiswaan-SMK-Telkom
php artisan db:seed --class=RoleSeeder --force
php artisan permission:cache-reset
```

Masuk ke SISFO sebagai **Super Admin**, buka **Manajemen Pengguna**, lalu tambahkan role `adminup` kepada pegawai yang mengelola Unit Produksi. Pengguna perlu keluar dari aplikasi Unit Produksi dan masuk kembali melalui SSO supaya data role lokal diperbarui.

Pastikan migrasi katalog di aplikasi Unit Produksi sudah dijalankan:

```bash
cd /var/www/Unit-Produksi-Stella
php artisan migrate --force
php artisan storage:link
```

Setelah login ulang, menu **Produk** hanya muncul bagi pengguna dengan role `adminup`. Halaman tersebut mencakup kategori, data produk, stok, harga, gambar, status publikasi, pencarian, filter, dan pagination.

Versi katalog terbaru juga menambahkan galeri maksimal delapan foto, SKU otomatis, nomor barcode unik, dan pemindai barcode melalui kamera. Jalankan deployment normal agar migrasi `product_images` dan kolom `barcode` diterapkan:

```bash
/home/wahyurah55/deploy_up.sh
```

Foto produk lama otomatis dimigrasikan menjadi foto pertama pada galeri. Pemindai barcode harus dibuka melalui `https://up.smktelkom-lpg.id` karena browser tidak mengizinkan akses kamera pada koneksi HTTP biasa. Pengguna juga harus memberikan izin kamera saat browser memintanya. Library pemindai sudah berada di bundle aplikasi dan tidak mengambil script dari CDN.

Tahap katalog ini belum mengaktifkan transaksi QRIS. Sebelum toko publik dan pembayaran dibuat, tentukan penyedia payment gateway, kredensial merchant, callback/webhook, serta aturan settlement dan pembatalan pembayaran.
