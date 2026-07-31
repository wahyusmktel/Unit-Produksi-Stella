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

Siapkan database MySQL terpisah untuk aplikasi Unit Produksi. Contoh:

```sql
CREATE DATABASE unit_produksi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'unit_produksi'@'localhost' IDENTIFIED BY 'GANTI_PASSWORD_KUAT';
GRANT ALL PRIVILEGES ON unit_produksi.* TO 'unit_produksi'@'localhost';
FLUSH PRIVILEGES;
```

Konfigurasi penting `.env` produksi:

```dotenv
APP_NAME="Unit Produksi SMK Telkom Lampung"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://up.smktelkom-lpg.id

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
