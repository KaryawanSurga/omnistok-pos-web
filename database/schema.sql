-- ==============================================================================
-- DATABASE SCHEMA: OmniStok POS & Inventory Management System
-- Kelompok 6 - Kelas SI-G
-- Anggota:
-- 1. Justin Kelvin (JK - 255150407111098)
-- 2. Ferdyansah (FF - 255150400111039)
-- 3. Zainal Maarif (ZM - 255150400111070)
-- 4. Fauzi Al Wahid (FW - 255150400111016)
-- Disinkronkan 100% dengan PDM Modul 2 (Halaman 140 - 148)
-- DBMS: MySQL / MariaDB (InnoDB Engine)
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS omnistok_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE omnistok_db;

-- 1. TABEL AKUN [JK - DDL01] (PDM Halaman 140)
CREATE TABLE IF NOT EXISTS akun (
    id_akun INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    nama_lengkap VARCHAR(100) NULL,
    password_hash VARCHAR(255) NOT NULL,
    hak_akses VARCHAR(50) NOT NULL,
    role VARCHAR(50) NOT NULL,
    status_aktif BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_time DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. TABEL PRODUK (PDM Halaman 145)
CREATE TABLE IF NOT EXISTS produk (
    id_produk INT AUTO_INCREMENT PRIMARY KEY,
    nama_produk VARCHAR(150) NOT NULL,
    kategori VARCHAR(50) NOT NULL,
    deskripsi TEXT NULL,
    harga_jual DECIMAL(10,2) NOT NULL,
    harga_modal DECIMAL(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. TABEL PEMASOK (PDM Halaman 145-146)
CREATE TABLE IF NOT EXISTS pemasok (
    id_pemasok INT AUTO_INCREMENT PRIMARY KEY,
    nama_pemasok VARCHAR(50) NOT NULL,
    no_telpon VARCHAR(30) NOT NULL,
    alamat TEXT NOT NULL,
    email VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. TABEL PESANAN / PURCHASE ORDER (PDM Halaman 146)
CREATE TABLE IF NOT EXISTS pesanan (
    id_pesanan INT AUTO_INCREMENT PRIMARY KEY,
    id_pemasok INT NOT NULL,
    nomor_pesanan VARCHAR(50) NOT NULL UNIQUE,
    status_pesanan VARCHAR(50) NOT NULL DEFAULT 'Diproses',
    CONSTRAINT fk_pesanan_pemasok FOREIGN KEY (id_pemasok) REFERENCES pemasok(id_pemasok) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. TABEL DETAIL PESANAN (PDM Halaman 146-147)
CREATE TABLE IF NOT EXISTS detail_pesanan (
    id_detail_pesanan INT AUTO_INCREMENT PRIMARY KEY,
    id_pesanan INT NOT NULL,
    id_produk INT NOT NULL,
    jumlah_pesanan INT NOT NULL,
    harga_pesan DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_detailpesanan_pesanan FOREIGN KEY (id_pesanan) REFERENCES pesanan(id_pesanan) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_detailpesanan_produk FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. TABEL BATCH PRODUK (PDM Halaman 144-145)
CREATE TABLE IF NOT EXISTS batch_produk (
    id_batch INT AUTO_INCREMENT PRIMARY KEY,
    id_produk INT NOT NULL,
    tanggal_kadaluwarsa DATE NOT NULL,
    CONSTRAINT fk_batch_produk FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. TABEL STOK [FW - DDL02] (PDM Halaman 142)
CREATE TABLE IF NOT EXISTS stok (
    id_stok INT AUTO_INCREMENT PRIMARY KEY,
    id_produk INT NOT NULL,
    id_batch INT NOT NULL,
    lokasi_stok VARCHAR(50) NOT NULL DEFAULT 'Gudang Utama',
    jumlah_stok INT NOT NULL DEFAULT 0,
    batas_minimum INT NOT NULL DEFAULT 5,
    CONSTRAINT fk_stok_produk FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_stok_batch FOREIGN KEY (id_batch) REFERENCES batch_produk(id_batch) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. TABEL TRANSAKSI [FF - DDL01] (PDM Halaman 140-141)
CREATE TABLE IF NOT EXISTS transaksi (
    id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
    id_akun INT NOT NULL,
    tanggal_transaksi DATETIME NOT NULL,
    total_harga DECIMAL(10,2) NOT NULL,
    metode_bayar VARCHAR(20) NOT NULL DEFAULT 'Tunai',
    nominal_bayar DECIMAL(10,2) NOT NULL DEFAULT 0,
    kembalian DECIMAL(10,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_transaksi_akun FOREIGN KEY (id_akun) REFERENCES akun(id_akun) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. TABEL DETAIL PENJUALAN [FF - DDL02] (PDM Halaman 141)
CREATE TABLE IF NOT EXISTS detail_penjualan (
    id_detail_penjualan INT AUTO_INCREMENT PRIMARY KEY,
    id_transaksi INT NOT NULL,
    id_produk INT NOT NULL,
    jumlah INT NOT NULL,
    harga_jual DECIMAL(10,2) NOT NULL,
    hpp DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_detailpenjualan_transaksi FOREIGN KEY (id_transaksi) REFERENCES transaksi(id_transaksi) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_detailpenjualan_produk FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. TABEL REKONSILIASI KAS (PDM Halaman 141-142)
CREATE TABLE IF NOT EXISTS rekonsiliasi (
    id_rekonsiliasi INT AUTO_INCREMENT PRIMARY KEY,
    id_transaksi INT NULL,
    status VARCHAR(50) NOT NULL,
    tanggal DATETIME NOT NULL,
    modal_awal DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_tunai DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_qris DECIMAL(10,2) NOT NULL DEFAULT 0,
    kas_fisik DECIMAL(10,2) NOT NULL DEFAULT 0,
    selisih DECIMAL(10,2) NOT NULL DEFAULT 0,
    catatan TEXT NULL,
    CONSTRAINT fk_rekonsiliasi_transaksi FOREIGN KEY (id_transaksi) REFERENCES transaksi(id_transaksi) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. TABEL MUTASI STOK [FW - DDL01] (PDM Halaman 142-143)
CREATE TABLE IF NOT EXISTS mutasi_stok (
    id_mutasi INT AUTO_INCREMENT PRIMARY KEY,
    id_produk INT NOT NULL,
    jenis_mutasi VARCHAR(50) NOT NULL, -- Masuk / Keluar / Penyesuaian
    jumlah INT NOT NULL,
    alasan TEXT NOT NULL,
    tanggal_mutasi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mutasistok_produk FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. TABEL PENERIMAAN BARANG [ZM - DDL01] (PDM Halaman 147)
CREATE TABLE IF NOT EXISTS penerimaan (
    id_penerimaan INT AUTO_INCREMENT PRIMARY KEY,
    id_pemasok INT NOT NULL,
    id_pesanan INT NOT NULL,
    nomor_nota VARCHAR(50) NOT NULL UNIQUE,
    tanggal_penerimaan DATE NOT NULL,
    CONSTRAINT fk_penerimaan_pemasok FOREIGN KEY (id_pemasok) REFERENCES pemasok(id_pemasok) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_penerimaan_pesanan FOREIGN KEY (id_pesanan) REFERENCES pesanan(id_pesanan) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. TABEL DETAIL PENERIMAAN [ZM - DDL02] (PDM Halaman 147)
CREATE TABLE IF NOT EXISTS detail_penerimaan (
    id_detail_penerimaan INT AUTO_INCREMENT PRIMARY KEY,
    id_penerimaan INT NOT NULL,
    id_produk INT NOT NULL,
    id_batch INT NOT NULL,
    jumlah_diterima INT NOT NULL,
    CONSTRAINT fk_detailpenerimaan_penerimaan FOREIGN KEY (id_penerimaan) REFERENCES penerimaan(id_penerimaan) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_detailpenerimaan_produk FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_detailpenerimaan_batch FOREIGN KEY (id_batch) REFERENCES batch_produk(id_batch) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. TABEL RETUR PENERIMAAN (PDM Halaman 147-148)
CREATE TABLE IF NOT EXISTS retur_penerimaan (
    id_retur INT AUTO_INCREMENT PRIMARY KEY,
    id_penerimaan INT NOT NULL,
    nomor_retur VARCHAR(50) NOT NULL UNIQUE,
    tanggal_retur DATE NOT NULL,
    CONSTRAINT fk_retur_penerimaan FOREIGN KEY (id_penerimaan) REFERENCES penerimaan(id_penerimaan) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. TABEL DETAIL RETUR (PDM Halaman 148)
CREATE TABLE IF NOT EXISTS detail_retur (
    id_detail_retur INT AUTO_INCREMENT PRIMARY KEY,
    id_retur INT NOT NULL,
    id_produk INT NOT NULL,
    jumlah_retur INT NOT NULL,
    alasan_retur TEXT NOT NULL,
    CONSTRAINT fk_detailretur_retur FOREIGN KEY (id_retur) REFERENCES retur_penerimaan(id_retur) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_detailretur_produk FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. TABEL DISKON (PDM Halaman 143-144)
CREATE TABLE IF NOT EXISTS diskon (
    id_diskon INT AUTO_INCREMENT PRIMARY KEY,
    kode_diskon VARCHAR(20) NULL UNIQUE,
    id_produk INT NOT NULL,
    nama_diskon VARCHAR(50) NOT NULL,
    presentase_diskon DECIMAL(10,2) NOT NULL,
    maks_diskon DECIMAL(12,2) NULL,
    kuota INT NULL,
    terpakai INT NOT NULL DEFAULT 0,
    tanggal_mulai DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    status_aktif TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    CONSTRAINT fk_diskon_produk FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. TABEL RIWAYAT HARGA (PDM Halaman 144)
CREATE TABLE IF NOT EXISTS riwayat_harga (
    id_riwayat_harga INT AUTO_INCREMENT PRIMARY KEY,
    id_produk INT NOT NULL,
    harga_jual_lama DECIMAL(10,2) NOT NULL,
    harga_modal_lama DECIMAL(10,2) NOT NULL,
    tanggal_perubahan DATE NOT NULL,
    CONSTRAINT fk_riwayatharga_produk FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================================================================
-- SEED DATA AWAL (UNTUK PENGUJIAN SISTEM)
-- ==============================================================================

INSERT INTO akun (id_akun, email, nama_lengkap, password_hash, hak_akses, role, status_aktif, last_login_time) VALUES
(1, 'admin@omnistok.com', 'Administrator Toko', '$2y$10$e8oYqW5aLkm6b/Wc7jM9I.k4bHqyvA7zE3uJkQk7w7.qL6rG', 'SUPER_ADMIN', 'Pemilik Toko', TRUE, NOW()),
(2, 'kasir@omnistok.com', 'Kasir Utama', '$2y$10$e8oYqW5aLkm6b/Wc7jM9I.k4bHqyvA7zE3uJkQk7w7.qL6rG', 'KASIR_POS', 'Kasir', TRUE, NOW()),
(3, 'gudang@omnistok.com', 'Petugas Gudang', '$2y$10$e8oYqW5aLkm6b/Wc7jM9I.k4bHqyvA7zE3uJkQk7w7.qL6rG', 'PETUGAS_GUDANG', 'Petugas Stok', TRUE, NOW())
ON DUPLICATE KEY UPDATE email=VALUES(email);

INSERT INTO produk (id_produk, nama_produk, kategori, deskripsi, harga_jual, harga_modal) VALUES
(101, 'Beras Premium 5 Kg', 'Sembako', 'Beras kualitas premium pulen', 75000.00, 65000.00),
(102, 'Minyak Goreng Sania 2L', 'Sembako', 'Minyak goreng kelapa sawit higienis', 36000.00, 31500.00),
(103, 'Gula Pasir Gulaku 1 Kg', 'Sembako', 'Gula pasir kristal putih tebu murni', 18500.00, 16000.00),
(104, 'Susu UHT Full Cream 1L', 'Minuman', 'Susu sapi murni kaya nutrisi', 21000.00, 17500.00),
(105, 'Mie Instan Goreng (Karton)', 'Makanan', 'Mie instan goreng isi 40 bungkus', 120000.00, 105000.00)
ON DUPLICATE KEY UPDATE nama_produk=VALUES(nama_produk);

INSERT INTO pemasok (id_pemasok, nama_pemasok, no_telpon, alamat, email) VALUES
(901, 'CV Sumber Pangan Makmur', '081234567890', 'Jl. Industri No. 12, Malang, Jawa Timur', 'sales@sumberpangan.com'),
(902, 'PT Distribusi Niaga Sejahtera', '081987654321', 'Kawasan Rungkut Industri III, Surabaya', 'info@distribusiniaga.co.id')
ON DUPLICATE KEY UPDATE nama_pemasok=VALUES(nama_pemasok);

INSERT INTO batch_produk (id_batch, id_produk, tanggal_kadaluwarsa) VALUES
(501, 101, '2027-06-30'),
(502, 102, '2026-12-31'),
(503, 103, '2027-03-15'),
(504, 104, '2025-04-10'),
(505, 105, '2026-08-20')
ON DUPLICATE KEY UPDATE tanggal_kadaluwarsa=VALUES(tanggal_kadaluwarsa);

INSERT INTO stok (id_stok, id_produk, id_batch, lokasi_stok, jumlah_stok, batas_minimum) VALUES
(4001, 101, 501, 'Gudang Utama', 45, 10),
(4002, 102, 502, 'Gudang Utama', 60, 15),
(4003, 103, 503, 'Gudang Utama', 35, 10),
(4004, 104, 504, 'Gudang Utama', 12, 15),
(4005, 105, 505, 'Gudang Utama', 25, 5)
ON DUPLICATE KEY UPDATE jumlah_stok=VALUES(jumlah_stok);

INSERT INTO pesanan (id_pesanan, id_pemasok, nomor_pesanan, status_pesanan) VALUES
(10001, 901, 'PO-2026-001', 'Selesai'),
(10002, 901, 'PO-2026-002', 'Diproses'),
(10003, 902, 'PO-2026-003', 'Diproses')
ON DUPLICATE KEY UPDATE nomor_pesanan=VALUES(nomor_pesanan);

INSERT INTO detail_pesanan (id_detail_pesanan, id_pesanan, id_produk, jumlah_pesanan, harga_pesan) VALUES
(11001, 10001, 101, 20, 65000.00),
(11002, 10002, 102, 50, 31500.00),
(11003, 10003, 104, 30, 17500.00)
ON DUPLICATE KEY UPDATE jumlah_pesanan=VALUES(jumlah_pesanan);

INSERT INTO penerimaan (id_penerimaan, id_pemasok, id_pesanan, nomor_nota, tanggal_penerimaan) VALUES
(12001, 901, 10001, 'INV-2026-001', '2026-10-19')
ON DUPLICATE KEY UPDATE nomor_nota=VALUES(nomor_nota);

INSERT INTO detail_penerimaan (id_detail_penerimaan, id_penerimaan, id_produk, id_batch, jumlah_diterima) VALUES
(13001, 12001, 101, 501, 20)
ON DUPLICATE KEY UPDATE jumlah_diterima=VALUES(jumlah_diterima);

INSERT INTO diskon (id_diskon, kode_diskon, id_produk, nama_diskon, presentase_diskon, maks_diskon, kuota, terpakai, tanggal_mulai, tanggal_selesai, status_aktif, created_at, updated_at) VALUES
(7001, 'DSK-7001', 101, 'Promo Beras Murah', 10.00, 15000.00, NULL, 0, '2026-10-01', '2026-11-30', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE nama_diskon=VALUES(nama_diskon), status_aktif=VALUES(status_aktif);

INSERT INTO mutasi_stok (id_mutasi, id_produk, jenis_mutasi, jumlah, alasan, tanggal_mutasi) VALUES
(6001, 101, 'Masuk', 20, 'Penerimaan barang dari PO-2026-001', '2026-10-19 09:30:00'),
(6002, 101, 'Keluar', 2, 'Penjualan kasir POS struk #1001', '2026-10-19 10:45:00'),
(6003, 104, 'Penyesuaian', -3, 'Stok rusak botol bocor saat opname', '2026-10-20 14:00:00')
ON DUPLICATE KEY UPDATE id_mutasi=VALUES(id_mutasi);
