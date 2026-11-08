-- =====================================================================
-- MES Quotation : data awal (dummy)
-- Jalankan SETELAH schema.sql, pada database kosong.
-- Semua data di bawah ini dummy (email memakai domain .example, telepon
-- dan alamat hanya contoh). CV Amora Prakarsa mengikuti quotation
-- referensi. Ganti profil MES di tabel settings dengan data asli.
-- =====================================================================

USE `mes_quotation`;

START TRANSACTION;

-- ---------------------------------------------------------------------
-- settings : profil perusahaan default
-- ---------------------------------------------------------------------
INSERT INTO `settings`
  (`id`, `company_name`, `company_address`, `company_phone`, `company_email`,
   `company_logo`, `company_signature`, `tax_percentage_default`)
VALUES
  (1, 'Mirai Energy Supply',
   'Gedung Contoh Lt. 5, Jl. Jend. Sudirman Kav. 10\nJakarta Selatan 12190, Indonesia',
   '+62 21 5550 1234', 'finance@mes-energy.example',
   'assets/img/logo-mes.png', 'assets/img/signature/default.png', 11.00);

-- ---------------------------------------------------------------------
-- users : akun admin awal
--   username : admin
--   password : Mes-Admin-2026!   (SEMENTARA: wajib diganti saat login pertama)
--   Pada server nyata, hapus baris ini dan buat akun sendiri:
--     php bin/user.php create <username> admin "<Nama>"
-- ---------------------------------------------------------------------
INSERT INTO `users`
  (`id`, `username`, `full_name`, `password_hash`, `role`, `is_active`, `must_change_password`)
VALUES
  (1, 'admin', 'Administrator',
   '$2y$10$LRF.ceOzdnrhuqH9oWukp.RfSCdxKdlf129mlWBZoJRMgq/aHnAX.', 'admin', 1, 1);

-- ---------------------------------------------------------------------
-- customers : pelanggan korporat
-- ---------------------------------------------------------------------
INSERT INTO `customers`
  (`id`, `name`, `company_name`, `email`, `phone`, `address`)
VALUES
  (1, 'Rudi Hartono',  'PT Nusantara Daya Mandiri',  'procurement@nusantaradaya.example', '+62 254 555 0101',
   'Kawasan Industri Krakatau Blok C-12\nCilegon, Banten 42435'),
  (2, 'Yunan Prasetyo', 'CV Amora Prakarsa',          'purchasing@amoraprakarsa.example',  '+62 254 555 0202',
   'BBS III Blok C4 No. 12\nCilegon, Banten 42447'),
  (3, 'Maya Kartika',  'PT Samudra Kimia Utama',     'maya.kartika@samudrakimia.example', '+62 21 555 0303',
   'Jl. Raya Bekasi KM 28 No. 45\nBekasi, Jawa Barat 17530'),
  (4, 'Dedi Setiawan', 'PT Bumi Teknik Perkasa',     'dedi.s@bumiteknik.example',         '+62 267 555 0404',
   'Kawasan Industri Surya Cipta Kav. 7\nKarawang, Jawa Barat 41363');

-- ---------------------------------------------------------------------
-- quotations : subtotal + PPN 11% = total (dihitung dari item di bawah)
-- ---------------------------------------------------------------------
INSERT INTO `quotations`
  (`id`, `quotation_number`, `customer_id`, `quotation_date`, `valid_until`,
   `subtotal`, `tax_amount`, `total_amount`, `notes`, `status`)
VALUES
  (1, 'SQ-26-10-001', 1, '2026-10-05', '2026-10-12',
   26300000.00, 2893000.00, 29193000.00,
   'Payment Terms: 30 hari setelah invoice.\nDelivery: Free area Cilegon.\nLead Time: 2 minggu setelah PO.',
   'Accepted'),
  (2, 'SQ-26-10-002', 2, '2026-10-20', '2026-10-27',
   23300000.00, 2563000.00, 25863000.00,
   'Payment Terms: C.O.D.\nDelivery: Free.\nLead Time: 1 minggu setelah PO.',
   'Sent'),
  (3, 'SQ-26-10-003', 4, '2026-10-12', '2026-10-19',
   59300000.00, 6523000.00, 65823000.00,
   'Payment Terms: DP 50%, pelunasan sebelum pengiriman.\nDelivery: Free area Karawang.\nLead Time: 3 minggu setelah DP.',
   'Rejected'),
  (4, 'SQ-26-11-001', 3, '2026-11-02', '2026-11-09',
   42500000.00, 4675000.00, 47175000.00,
   'Payment Terms: 14 hari setelah invoice.\nDelivery: Free area Bekasi.\nLead Time: 4 minggu setelah PO.',
   'Draft');

-- ---------------------------------------------------------------------
-- quotation_items : total_price = quantity x unit_price
-- ---------------------------------------------------------------------
INSERT INTO `quotation_items`
  (`quotation_id`, `item_description`, `quantity`, `unit`, `unit_price`, `total_price`)
VALUES
  -- SQ-26-10-001 : subtotal 26.300.000
  (1, 'Regulator Gas Industri 2 Stage',               4.00,  'UNIT',   3450000.00, 13800000.00),
  (1, 'Selang Gas Tahan Tekanan, panjang 20 m',       6.00,  'ROLL',   1275000.00,  7650000.00),
  (1, 'Pressure Gauge 0-10 Bar',                     10.00,  'PCS',     485000.00,  4850000.00),

  -- SQ-26-10-002 : subtotal 23.300.000
  (2, 'Hand Cutting Torch M-2500 (without nozzle)',   5.00,  'PCS',    2850000.00, 14250000.00),
  (2, 'Cutting Nozzle Propane No. 2',                20.00,  'PCS',     165000.00,  3300000.00),
  (2, 'Flashback Arrestor',                           5.00,  'PCS',    1150000.00,  5750000.00),

  -- SQ-26-10-003 : subtotal 59.300.000
  (3, 'Pipa Baja Seamless 2 inch SCH 40, panjang 6 m',30.00, 'BATANG', 1850000.00, 55500000.00),
  (3, 'Fitting Elbow 2 inch SCH 40',                 40.00,  'PCS',      95000.00,  3800000.00),

  -- SQ-26-11-001 : subtotal 42.500.000
  (4, 'Gas Detector Portable LEL',                    2.00,  'UNIT',  18500000.00, 37000000.00),
  (4, 'Kalibrasi Gas Detector dan sertifikat',        2.00,  'UNIT',   2750000.00,  5500000.00);

COMMIT;

-- ---------------------------------------------------------------------
-- Cek konsistensi (opsional): harus mengembalikan 0 baris
-- ---------------------------------------------------------------------
-- SELECT q.quotation_number, q.subtotal, SUM(i.total_price) AS sum_items
--   FROM quotations q
--   JOIN quotation_items i ON i.quotation_id = q.id
--  GROUP BY q.id
-- HAVING q.subtotal <> SUM(i.total_price)
--     OR q.total_amount <> q.subtotal + q.tax_amount;
