-- Migrasi tabel produk OmniStok untuk database MySQL/MariaDB yang sudah ada.
-- Backup database terlebih dahulu. Jalankan blok berikut satu kali.
-- Jika muncul "Duplicate column name", berarti kolom sudah ada dan blok dilewati.

ALTER TABLE produk
    ADD COLUMN status_aktif TINYINT(1) NOT NULL DEFAULT 1 AFTER harga_modal;

-- Produk lama dianggap aktif agar tetap tampil di katalog dan POS.
UPDATE produk
SET status_aktif = 1
WHERE status_aktif IS NULL;
