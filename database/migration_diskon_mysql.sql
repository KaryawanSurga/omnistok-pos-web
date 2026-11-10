-- Migrasi tabel diskon OmniStok untuk database MySQL/MariaDB yang sudah ada.
-- Backup database terlebih dahulu. Jalankan tiap blok satu per satu.
-- Jika muncul "Duplicate column name", lewati blok tersebut.

ALTER TABLE diskon
    ADD COLUMN kode_diskon VARCHAR(20) NULL AFTER id_diskon;

ALTER TABLE diskon
    ADD COLUMN maks_diskon DECIMAL(12,2) NULL AFTER presentase_diskon;

ALTER TABLE diskon
    ADD COLUMN kuota INT NULL AFTER maks_diskon;

ALTER TABLE diskon
    ADD COLUMN terpakai INT NOT NULL DEFAULT 0 AFTER kuota;

ALTER TABLE diskon
    ADD COLUMN status_aktif TINYINT(1) NOT NULL DEFAULT 1 AFTER tanggal_selesai;

ALTER TABLE diskon
    ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE diskon
    ADD COLUMN updated_at DATETIME NULL;

UPDATE diskon
SET kode_diskon = CONCAT('DSK-', LPAD(id_diskon, 3, '0'))
WHERE kode_diskon IS NULL OR kode_diskon = '';

ALTER TABLE diskon
    ADD UNIQUE INDEX uq_diskon_kode (kode_diskon);

-- Catatan: tabel transaksi OmniStok belum mencatat potongan promo.
-- Statistik "Potongan Hari Ini" akan bernilai 0 sampai POS mencatat
-- total_diskon/id_diskon pada transaksi. CRUD diskon tetap berjalan.
