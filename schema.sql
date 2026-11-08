-- =====================================================================
-- MES Quotation : skema database
-- Engine  : InnoDB
-- Charset : utf8mb4 (utf8mb4_unicode_ci)
-- Target  : MySQL 8.0.16+ / MariaDB 10.5+ (CHECK constraint dihormati)
--
-- Cara pakai:
--   mysql -u root -p < database/schema.sql
--   mysql -u root -p < database/seeders.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `mes_quotation`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `mes_quotation`;

-- ---------------------------------------------------------------------
-- settings : profil perusahaan dan default pajak (satu baris aktif)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id`                     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `company_name`           VARCHAR(150)  NOT NULL,
  `company_address`        TEXT          NULL,
  `company_phone`          VARCHAR(30)   NULL,
  `company_email`          VARCHAR(120)  NULL,
  `company_logo`           VARCHAR(255)  NULL COMMENT 'Path relatif terhadap public/, mis. assets/img/logo-mes.png',
  `company_signature`      VARCHAR(255)  NULL COMMENT 'Path relatif terhadap public/, mis. assets/img/signature/default.png',
  `tax_percentage_default` DECIMAL(5,2)  NOT NULL DEFAULT 11.00 COMMENT 'Tarif PPN default dalam persen',
  `created_at`             TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_settings_tax` CHECK (`tax_percentage_default` >= 0 AND `tax_percentage_default` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- customers : data klien / pelanggan
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(120)  NOT NULL COMMENT 'Nama PIC / penerima (Quotation For)',
  `company_name` VARCHAR(150)  NULL,
  `email`        VARCHAR(120)  NULL,
  `phone`        VARCHAR(30)   NULL,
  `address`      TEXT          NULL,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customers_company_name` (`company_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- quotations : header dokumen penawaran
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quotations` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `quotation_number` VARCHAR(30)   NOT NULL COMMENT 'Format SQ-YY-MM-NNN, mis. SQ-26-11-001',
  `customer_id`      INT UNSIGNED  NOT NULL,
  `quotation_date`   DATE          NOT NULL,
  `valid_until`      DATE          NOT NULL,
  `subtotal`         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount`       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_amount`     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `notes`            TEXT          NULL,
  `status`           ENUM('Draft','Sent','Accepted','Rejected') NOT NULL DEFAULT 'Draft',
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_quotations_number` (`quotation_number`),
  KEY `idx_quotations_customer_id` (`customer_id`),
  KEY `idx_quotations_status_date` (`status`, `quotation_date`),
  CONSTRAINT `fk_quotations_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_quotations_valid` CHECK (`valid_until` >= `quotation_date`),
  CONSTRAINT `chk_quotations_amounts` CHECK (`subtotal` >= 0 AND `tax_amount` >= 0 AND `total_amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- quotation_items : rincian baris item; ikut terhapus bersama quotation
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quotation_items` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `quotation_id`     INT UNSIGNED  NOT NULL,
  `item_description` VARCHAR(500)  NOT NULL,
  `quantity`         DECIMAL(12,2) NOT NULL DEFAULT 1.00,
  `unit`             VARCHAR(20)   NOT NULL DEFAULT 'PCS',
  `unit_price`       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_price`      DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'quantity x unit_price',
  PRIMARY KEY (`id`),
  KEY `idx_items_quotation_id` (`quotation_id`),
  CONSTRAINT `fk_items_quotation`
    FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_items_values` CHECK (`quantity` > 0 AND `unit_price` >= 0 AND `total_price` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- users : akun login aplikasi
--   role   : admin (semua menu) | finance (quotation, tanpa Pengaturan)
--   Password hanya disimpan sebagai hash bcrypt (kolom password_hash).
--   Buat akun lewat: php bin/user.php create <username> <role> "<Nama>"
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `username`             VARCHAR(50)   NOT NULL COMMENT 'Huruf kecil, angka, titik, garis bawah, strip',
  `full_name`            VARCHAR(100)  NOT NULL,
  `password_hash`        VARCHAR(255)  NOT NULL COMMENT 'Hash bcrypt dari password_hash()',
  `role`                 ENUM('admin','finance') NOT NULL DEFAULT 'finance',
  `is_active`            TINYINT(1)    NOT NULL DEFAULT 1,
  `must_change_password` TINYINT(1)    NOT NULL DEFAULT 1 COMMENT '1 = wajib ganti password pada login berikutnya',
  `last_login_at`        TIMESTAMP     NULL DEFAULT NULL,
  `password_changed_at`  TIMESTAMP     NULL DEFAULT NULL,
  `created_at`           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- login_attempts : percobaan login gagal (pembatasan per username+IP dan per IP)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`     VARCHAR(50)     NOT NULL,
  `ip_address`   VARCHAR(45)     NOT NULL,
  `attempted_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_user_ip` (`username`, `ip_address`, `attempted_at`),
  KEY `idx_attempts_ip` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
