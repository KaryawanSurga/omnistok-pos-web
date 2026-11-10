-- ==============================================================================
-- MIGRASI: Kolom pembayaran POS & rekap rekonsiliasi kas
-- Kelompok 6 - Praktikum RSI (OmniStok)
--
-- Jalankan pada database MySQL/MariaDB yang sudah memiliki tabel `transaksi`
-- dan `rekonsiliasi` versi lama. Jalankan satu blok per satu blok.
-- ==============================================================================

USE omnistok_db;

-- 1. Metode pembayaran pada transaksi penjualan (UC-07)
ALTER TABLE transaksi
    ADD COLUMN metode_bayar VARCHAR(20) NOT NULL DEFAULT 'Tunai' AFTER total_harga,
    ADD COLUMN nominal_bayar DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER metode_bayar,
    ADD COLUMN kembalian DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER nominal_bayar;

-- 2. Rekap audit per sesi shift kasir (UC-08)
ALTER TABLE rekonsiliasi
    ADD COLUMN modal_awal DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER tanggal,
    ADD COLUMN total_tunai DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER modal_awal,
    ADD COLUMN total_qris DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER total_tunai,
    ADD COLUMN kas_fisik DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER total_qris,
    ADD COLUMN selisih DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER kas_fisik,
    ADD COLUMN catatan TEXT NULL AFTER selisih;

-- 3. Satu baris rekonsiliasi = satu sesi shift, bukan satu transaksi.
--    Shift boleh ditutup walau belum ada transaksi sama sekali.
ALTER TABLE rekonsiliasi MODIFY id_transaksi INT NULL;

-- 4. Rapikan status lama agar konsisten dengan format baru.
UPDATE rekonsiliasi
SET status = CASE
    WHEN selisih = 0 THEN 'Cocok (Balance)'
    WHEN selisih < 0 THEN 'Selisih Kurang'
    ELSE 'Selisih Lebih'
END
WHERE status IS NULL OR status = '';
