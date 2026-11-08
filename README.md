# MES Quotation

Aplikasi PHP (MVC tanpa framework) untuk membuat quotation A4 Mirai Energy Supply.

## Pasang

```bash
composer install --no-dev          # DomPDF (opsional; tanpa ini dipakai cetak browser)
cp .env.example .env && chmod 640 .env   # lalu isi DB_USER / DB_PASS
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seeders.sql  # data contoh + akun admin sementara
# edit password di database/app_user.example.sql, lalu:
mysql -u root -p < database/app_user.example.sql
```

Arahkan document root web server ke `public/`. Untuk uji lokal: `php -S 127.0.0.1:8000 -t public`.

## Login

Seeder membuat `admin` / `Mes-Admin-2026!` (wajib diganti saat login pertama). Di server nyata hapus akun itu dan buat sendiri:

```bash
php bin/user.php create budi finance "Budi Santoso"
php bin/user.php list | passwd | disable | enable | unlock <username>
```

Role `admin` dapat membuka Pengaturan Perusahaan; `finance` hanya modul quotation.

## Kredensial

Semua rahasia ada di `.env` (tidak masuk Git) atau environment variable server, yang diutamakan. Production menolak `DB_USER=root` dan password kosong/contoh.

## Aset

`npm install && npm run build:css` bila mengubah tampilan Tailwind.
