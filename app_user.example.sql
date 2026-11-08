-- =====================================================================
-- Akun database khusus aplikasi (hak minimal)
--
-- Aplikasi hanya perlu membaca dan menulis data, TIDAK perlu membuat atau
-- menghapus tabel. Jalankan sekali sebagai root/admin MySQL, setelah
-- schema.sql dan seeders.sql:
--
--   mysql -u root -p < database/app_user.example.sql
--
-- GANTI password di bawah (buat dengan `openssl rand -base64 24`), lalu
-- isi nilai yang sama pada DB_USER dan DB_PASS di file .env.
-- Bila aplikasi dan MySQL berada di server berbeda, ganti 'localhost'
-- dengan alamat server aplikasi.
-- =====================================================================

CREATE USER IF NOT EXISTS 'mes_app'@'localhost' IDENTIFIED BY 'GANTI_DENGAN_PASSWORD_KUAT';

GRANT SELECT, INSERT, UPDATE, DELETE ON `mes_quotation`.* TO 'mes_app'@'localhost';

FLUSH PRIVILEGES;
