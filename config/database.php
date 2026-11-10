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
                nama_lengkap TEXT NULL,
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
                total_harga REAL NOT NULL,
                metode_bayar TEXT NOT NULL DEFAULT 'Tunai',
                nominal_bayar REAL NOT NULL DEFAULT 0,
                kembalian REAL NOT NULL DEFAULT 0
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
                id_transaksi INTEGER NULL,
                status TEXT NOT NULL,
                tanggal DATETIME NOT NULL,
                modal_awal REAL NOT NULL DEFAULT 0,
                total_tunai REAL NOT NULL DEFAULT 0,
                total_qris REAL NOT NULL DEFAULT 0,
                kas_fisik REAL NOT NULL DEFAULT 0,
                selisih REAL NOT NULL DEFAULT 0,
                catatan TEXT NULL
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
                kode_diskon TEXT UNIQUE,
                id_produk INTEGER NOT NULL,
                nama_diskon TEXT NOT NULL,
                presentase_diskon REAL NOT NULL,
                maks_diskon REAL NULL,
                kuota INTEGER NULL,
                terpakai INTEGER NOT NULL DEFAULT 0,
                tanggal_mulai DATE NOT NULL,
                tanggal_selesai DATE NOT NULL,
                status_aktif INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL
            );

            CREATE TABLE IF NOT EXISTS riwayat_harga (
                id_riwayat_harga INTEGER PRIMARY KEY AUTOINCREMENT,
                id_produk INTEGER NOT NULL,
                harga_jual_lama REAL NOT NULL,
                harga_modal_lama REAL NOT NULL,
                tanggal_perubahan DATE NOT NULL
            );
        ");

        $this->migrateSqliteDiskon();
        $this->migrateSqliteAkun();
        $this->migrateSqliteTransaksi();
        $this->migrateSqliteRekonsiliasi();

        // Seed jika tabel riwayat_harga masih kosong
        $stmtRH = $this->pdo->query("SELECT COUNT(*) as cnt FROM riwayat_harga");
        if ($stmtRH->fetch()['cnt'] == 0) {
            $this->pdo->exec("
                INSERT INTO riwayat_harga (id_produk, harga_jual_lama, harga_modal_lama, tanggal_perubahan) VALUES
                (101, 72000.0, 62000.0, '2026-10-15'),
                (102, 34000.0, 30000.0, '2026-10-12'),
                (103, 17500.0, 15000.0, '2026-10-01');

                INSERT INTO diskon (id_diskon, id_produk, kode_diskon, nama_diskon, presentase_diskon, maks_diskon, kuota, terpakai, tanggal_mulai, tanggal_selesai, status_aktif, created_at, updated_at) VALUES
                (7001, 101, 'DSK-7001', 'Promo Beras Murah Meriah', 10.0, 15000.0, NULL, 0, '2026-10-01', '2026-11-30', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (7002, 102, 'DSK-7002', 'Diskon Minyak Goreng Sehat', 5.0, 8000.0, NULL, 0, '2026-10-05', '2026-11-15', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

                INSERT INTO rekonsiliasi (id_transaksi, status, tanggal) VALUES
                (1, 'Cocok (Balance)', '2026-10-19 18:00:00');
            ");
        }
    }

    /**
     * Migrasi ringan untuk database SQLite yang sudah ada.
     * CREATE TABLE IF NOT EXISTS tidak menambah kolom pada tabel lama.
     */
    private function migrateSqliteAkun() {
        $existing = [];
        foreach ($this->pdo->query('PRAGMA table_info(akun)')->fetchAll() as $column) {
            $existing[strtolower((string) $column['name'])] = true;
        }

        if (!isset($existing['nama_lengkap'])) {
            $this->pdo->exec('ALTER TABLE akun ADD COLUMN nama_lengkap TEXT');
        }
    }

    /**
     * Migrasi ringan untuk database SQLite yang sudah ada.
     * CREATE TABLE IF NOT EXISTS tidak menambah kolom pada tabel lama.
     */
    private function migrateSqliteDiskon() {
        $existing = [];
        foreach ($this->pdo->query('PRAGMA table_info(diskon)')->fetchAll() as $column) {
            $existing[strtolower((string) $column['name'])] = true;
        }

        $definitions = [
            'kode_diskon'  => 'TEXT',
            'maks_diskon'  => 'REAL',
            'kuota'        => 'INTEGER',
            'terpakai'     => 'INTEGER NOT NULL DEFAULT 0',
            'status_aktif' => 'INTEGER NOT NULL DEFAULT 1',
            'created_at'   => 'DATETIME',
            'updated_at'   => 'DATETIME',
        ];

        foreach ($definitions as $name => $definition) {
            if (!isset($existing[$name])) {
                $this->pdo->exec("ALTER TABLE diskon ADD COLUMN {$name} {$definition}");
            }
        }

        $this->pdo->exec("UPDATE diskon SET status_aktif = 1 WHERE status_aktif IS NULL");
        $this->pdo->exec("UPDATE diskon SET terpakai = 0 WHERE terpakai IS NULL");
        $this->pdo->exec("UPDATE diskon SET created_at = CURRENT_TIMESTAMP WHERE created_at IS NULL");
        $this->pdo->exec("
            UPDATE diskon
            SET kode_diskon = 'DSK-' || printf('%03d', id_diskon)
            WHERE kode_diskon IS NULL OR kode_diskon = ''
        ");
        $this->pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_diskon_kode ON diskon (kode_diskon)");
    }

    /**
     * Menambahkan kolom pembayaran pada tabel transaksi untuk database SQLite lama.
     * Tanpa kolom ini metode Tunai/QRIS dan nominal bayar tidak dapat dicatat.
     */
    private function migrateSqliteTransaksi() {
        $existing = [];
        foreach ($this->pdo->query('PRAGMA table_info(transaksi)')->fetchAll() as $column) {
            $existing[strtolower((string) $column['name'])] = true;
        }

        $definitions = [
            'metode_bayar'  => "TEXT NOT NULL DEFAULT 'Tunai'",
            'nominal_bayar' => 'REAL NOT NULL DEFAULT 0',
            'kembalian'     => 'REAL NOT NULL DEFAULT 0',
        ];

        foreach ($definitions as $name => $definition) {
            if (!isset($existing[$name])) {
                $this->pdo->exec("ALTER TABLE transaksi ADD COLUMN {$name} {$definition}");
            }
        }
    }

    /**
     * Menambahkan kolom audit shift pada tabel rekonsiliasi SQLite lama.
     * Satu baris rekonsiliasi mewakili satu sesi kasir (tutup shift), sehingga
     * id_transaksi dibuat boleh NULL karena shift bisa ditutup tanpa transaksi.
     */
    private function migrateSqliteRekonsiliasi() {
        $columns = [];
        foreach ($this->pdo->query('PRAGMA table_info(rekonsiliasi)')->fetchAll() as $column) {
            $columns[strtolower((string) $column['name'])] = $column;
        }

        $definitions = [
            'modal_awal'  => 'REAL NOT NULL DEFAULT 0',
            'total_tunai' => 'REAL NOT NULL DEFAULT 0',
            'total_qris'  => 'REAL NOT NULL DEFAULT 0',
            'kas_fisik'   => 'REAL NOT NULL DEFAULT 0',
            'selisih'     => 'REAL NOT NULL DEFAULT 0',
            'catatan'     => 'TEXT NULL',
        ];

        foreach ($definitions as $name => $definition) {
            if (!isset($columns[$name])) {
                $this->pdo->exec("ALTER TABLE rekonsiliasi ADD COLUMN {$name} {$definition}");
            }
        }

        if (!empty($columns['id_transaksi']['notnull'])) {
            $this->rebuildSqliteRekonsiliasi();
        }

        $this->pdo->exec("
            UPDATE rekonsiliasi
            SET status = CASE
                WHEN selisih = 0 THEN 'Cocok (Balance)'
                WHEN selisih < 0 THEN 'Selisih Kurang'
                ELSE 'Selisih Lebih'
            END
            WHERE status IS NULL OR status = ''
        ");
    }

    /**
     * SQLite tidak mendukung ALTER COLUMN, sehingga tabel rekonsiliasi dibangun
     * ulang agar kolom id_transaksi menjadi nullable tanpa kehilangan data lama.
     */
    private function rebuildSqliteRekonsiliasi() {
        $this->pdo->exec('PRAGMA foreign_keys = OFF');
        $this->pdo->exec('BEGIN');
        try {
            $this->pdo->exec('ALTER TABLE rekonsiliasi RENAME TO rekonsiliasi_lama');
            $this->pdo->exec("
                CREATE TABLE rekonsiliasi (
                    id_rekonsiliasi INTEGER PRIMARY KEY AUTOINCREMENT,
                    id_transaksi INTEGER NULL,
                    status TEXT NOT NULL,
                    tanggal DATETIME NOT NULL,
                    modal_awal REAL NOT NULL DEFAULT 0,
                    total_tunai REAL NOT NULL DEFAULT 0,
                    total_qris REAL NOT NULL DEFAULT 0,
                    kas_fisik REAL NOT NULL DEFAULT 0,
                    selisih REAL NOT NULL DEFAULT 0,
                    catatan TEXT NULL
                )
            ");
            $this->pdo->exec("
                INSERT INTO rekonsiliasi (id_rekonsiliasi, id_transaksi, status, tanggal, modal_awal, total_tunai, total_qris, kas_fisik, selisih, catatan)
                SELECT r.id_rekonsiliasi,
                       (SELECT t.id_transaksi FROM transaksi t WHERE t.id_transaksi = r.id_transaksi),
                       r.status, r.tanggal, r.modal_awal, r.total_tunai, r.total_qris, r.kas_fisik, r.selisih, r.catatan
                FROM rekonsiliasi_lama r
            ");
            $this->pdo->exec('DROP TABLE rekonsiliasi_lama');
            $this->pdo->exec('COMMIT');
        } catch (Exception $e) {
            $this->pdo->exec('ROLLBACK');
            throw $e;
        } finally {
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        }
    }
}
