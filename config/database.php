<?php
/**
 * Database Connection Helper - OmniStok
 * Kelompok 6 - Praktikum RSI
 */

class Database {
    private static $instance = null;
    private $pdo;

    private $host = '127.0.0.1';
    private $db   = 'omnistok_db';
    private $user = 'root';
    private $pass = '';
    private $charset = 'utf8mb4';

    private function __construct() {
        $dsn = "mysql:host={$this->host};dbname={$this->db};charset={$this->charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
        } catch (PDOException $e) {
            // Fallback to SQLite file (local development & serverless deployment)
            $localDb = __DIR__ . '/../database/omnistok_local.sqlite';
            $targetDb = $localDb;

            // Di platform serverless (seperti Vercel), folder kode berstatus read-only.
            // Pindahkan database ke /tmp jika direktori tidak writable agar INSERT/UPDATE berfungsi penuh.
            if (!is_writable(dirname($localDb)) || (file_exists($localDb) && !is_writable($localDb))) {
                $tempDb = sys_get_temp_dir() . '/omnistok_local.sqlite';
                if (!file_exists($tempDb) && file_exists($localDb)) {
                    @copy($localDb, $tempDb);
                }
                $targetDb = $tempDb;
            }

            $this->pdo = new PDO("sqlite:" . $targetDb);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->initSqlite();
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->pdo;
    }

    private function initSqlite() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS akun (
                id_akun INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                hak_akses TEXT NOT NULL,
                role TEXT NOT NULL,
                status_aktif INTEGER NOT NULL DEFAULT 1,
                last_login_time DATETIME NULL
            );

            CREATE TABLE IF NOT EXISTS produk (
                id_produk INTEGER PRIMARY KEY AUTOINCREMENT,
                nama_produk TEXT NOT NULL,
                kategori TEXT NOT NULL,
                deskripsi TEXT NULL,
                harga_jual REAL NOT NULL,
                harga_modal REAL NOT NULL
            );

            CREATE TABLE IF NOT EXISTS pemasok (
                id_pemasok INTEGER PRIMARY KEY AUTOINCREMENT,
                nama_pemasok TEXT NOT NULL,
                no_telpon TEXT NOT NULL,
                alamat TEXT NOT NULL,
                email TEXT NULL
            );

            CREATE TABLE IF NOT EXISTS pesanan (
                id_pesanan INTEGER PRIMARY KEY AUTOINCREMENT,
                id_pemasok INTEGER NOT NULL,
                nomor_pesanan TEXT NOT NULL UNIQUE,
                status_pesanan TEXT NOT NULL DEFAULT 'Diproses'
            );

            CREATE TABLE IF NOT EXISTS detail_pesanan (
                id_detail_pesanan INTEGER PRIMARY KEY AUTOINCREMENT,
                id_pesanan INTEGER NOT NULL,
                id_produk INTEGER NOT NULL,
                jumlah_pesanan INTEGER NOT NULL,
                harga_pesan REAL NOT NULL
            );

            CREATE TABLE IF NOT EXISTS batch_produk (
                id_batch INTEGER PRIMARY KEY AUTOINCREMENT,
                id_produk INTEGER NOT NULL,
                tanggal_kadaluwarsa DATE NOT NULL
            );

            CREATE TABLE IF NOT EXISTS stok (
                id_stok INTEGER PRIMARY KEY AUTOINCREMENT,
                id_produk INTEGER NOT NULL,
                id_batch INTEGER NOT NULL,
                lokasi_stok TEXT NOT NULL DEFAULT 'Gudang Utama',
                jumlah_stok INTEGER NOT NULL DEFAULT 0,
                batas_minimum INTEGER NOT NULL DEFAULT 5
            );

            CREATE TABLE IF NOT EXISTS transaksi (
                id_transaksi INTEGER PRIMARY KEY AUTOINCREMENT,
                id_akun INTEGER NOT NULL,
                tanggal_transaksi DATETIME NOT NULL,
                total_harga REAL NOT NULL
            );

            CREATE TABLE IF NOT EXISTS detail_penjualan (
                id_detail_penjualan INTEGER PRIMARY KEY AUTOINCREMENT,
                id_transaksi INTEGER NOT NULL,
                id_produk INTEGER NOT NULL,
                jumlah INTEGER NOT NULL,
                harga_jual REAL NOT NULL,
                hpp REAL NOT NULL,
                subtotal REAL NOT NULL
            );

            CREATE TABLE IF NOT EXISTS rekonsiliasi (
                id_rekonsiliasi INTEGER PRIMARY KEY AUTOINCREMENT,
                id_transaksi INTEGER NOT NULL,
                status TEXT NOT NULL,
                tanggal DATETIME NOT NULL
            );

            CREATE TABLE IF NOT EXISTS mutasi_stok (
                id_mutasi INTEGER PRIMARY KEY AUTOINCREMENT,
                id_produk INTEGER NOT NULL,
                jenis_mutasi TEXT NOT NULL,
                jumlah INTEGER NOT NULL,
                alasan TEXT NOT NULL,
                tanggal_mutasi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS penerimaan (
                id_penerimaan INTEGER PRIMARY KEY AUTOINCREMENT,
                id_pemasok INTEGER NOT NULL,
                id_pesanan INTEGER NOT NULL,
                nomor_nota TEXT NOT NULL UNIQUE,
                tanggal_penerimaan DATE NOT NULL
            );

            CREATE TABLE IF NOT EXISTS detail_penerimaan (
                id_detail_penerimaan INTEGER PRIMARY KEY AUTOINCREMENT,
                id_penerimaan INTEGER NOT NULL,
                id_produk INTEGER NOT NULL,
                id_batch INTEGER NOT NULL,
                jumlah_diterima INTEGER NOT NULL
            );

            CREATE TABLE IF NOT EXISTS retur_penerimaan (
                id_retur INTEGER PRIMARY KEY AUTOINCREMENT,
                id_penerimaan INTEGER NOT NULL,
                nomor_retur TEXT NOT NULL UNIQUE,
                tanggal_retur DATE NOT NULL
            );

            CREATE TABLE IF NOT EXISTS detail_retur (
                id_detail_retur INTEGER PRIMARY KEY AUTOINCREMENT,
                id_retur INTEGER NOT NULL,
                id_produk INTEGER NOT NULL,
                jumlah_retur INTEGER NOT NULL,
                alasan_retur TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS diskon (
                id_diskon INTEGER PRIMARY KEY AUTOINCREMENT,
                id_produk INTEGER NOT NULL,
                nama_diskon TEXT NOT NULL,
                presentase_diskon REAL NOT NULL,
                tanggal_mulai DATE NOT NULL,
                tanggal_selesai DATE NOT NULL
            );

            CREATE TABLE IF NOT EXISTS riwayat_harga (
                id_riwayat_harga INTEGER PRIMARY KEY AUTOINCREMENT,
                id_produk INTEGER NOT NULL,
                harga_jual_lama REAL NOT NULL,
                harga_modal_lama REAL NOT NULL,
                tanggal_perubahan DATE NOT NULL
            );
        ");

        // Seed jika tabel riwayat_harga masih kosong
        $stmtRH = $this->pdo->query("SELECT COUNT(*) as cnt FROM riwayat_harga");
        if ($stmtRH->fetch()['cnt'] == 0) {
            $this->pdo->exec("
                INSERT INTO riwayat_harga (id_produk, harga_jual_lama, harga_modal_lama, tanggal_perubahan) VALUES
                (101, 72000.0, 62000.0, '2026-10-15'),
                (102, 34000.0, 30000.0, '2026-10-12'),
                (103, 17500.0, 15000.0, '2026-10-01');

                INSERT INTO diskon (id_produk, nama_diskon, presentase_diskon, tanggal_mulai, tanggal_selesai) VALUES
                (101, 'Promo Beras Murah Meriah', 10.0, '2026-10-01', '2026-11-30'),
                (102, 'Diskon Minyak Goreng Sehat', 5.0, '2026-10-05', '2026-11-15');

                INSERT INTO rekonsiliasi (id_transaksi, status, tanggal) VALUES
                (1, 'Cocok (Balance)', '2026-10-19 18:00:00');
            ");
        }
    }
}
