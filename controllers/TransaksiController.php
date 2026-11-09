<?php
/**
 * Kontroler: TransaksiController
 * Penulis: Ferdyansah (FF - 255150400111039)
 * Referensi CD: CD-07 (Mencatat Transaksi Penjualan POS)
 * Referensi Pseudocode: PC-07.1 s/d PC-07.16
 */

require_once __DIR__ . '/../config/database.php';

class TransaksiController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Mengambil katalog produk aktif untuk antarmuka kasir POS
    public function get_daftar_produk() {
        $stmt = $this->db->query("
            SELECT p.*, COALESCE(SUM(s.jumlah_stok), 0) AS total_stok
            FROM produk p
            LEFT JOIN stok s ON p.id_produk = s.id_produk
            GROUP BY p.id_produk
            ORDER BY p.nama_produk ASC
        ");
        return $stmt->fetchAll();
    }

    // [PC-07.1 & PC-07.2] Memindai barcode atau mencari produk berdasarkan ID
    public function scan_barcode($id_produk) {
        $stmt = $this->db->prepare("
            SELECT p.*, COALESCE(SUM(s.jumlah_stok), 0) AS total_stok
            FROM produk p
            LEFT JOIN stok s ON p.id_produk = s.id_produk
            WHERE p.id_produk = ?
            GROUP BY p.id_produk
        ");
        $stmt->execute([$id_produk]);
        return $stmt->fetch();
    }

    // [PC-07.3 & PC-07.4] Menghitung subtotal transaksi belanjaan
    public function hitung_subtotal($items) {
        $total = 0;
        foreach ($items as $item) {
            $total += ($item['harga_jual'] * $item['qty']);
        }
        return $total;
    }

    // [PC-07.10] Menghitung nilai kembalian uang tunai
    public function hitung_kembalian($total_harga, $nominal_bayar) {
        return max(0, $nominal_bayar - $total_harga);
    }

    // [PC-07.12 s/d PC-07.14] Menyimpan transaksi penjualan dan memperbarui stok
    public function simpan_transaksi($id_akun, $items, $total_harga, $metode_bayar = 'Tunai', $nominal_bayar = 0) {
        if (empty($items)) {
            return ['status' => false, 'message' => 'Keranjang transaksi masih kosong!'];
        }

        if ($metode_bayar === 'Tunai' && $nominal_bayar < $total_harga) {
            return ['status' => false, 'message' => 'Nominal pembayaran tunai kurang dari total tagihan!'];
        }

        try {
            $this->db->beginTransaction();

            // 1. Simpan Transaksi Induk
            $stmt = $this->db->prepare("
                INSERT INTO transaksi (id_akun, tanggal_transaksi, total_harga)
                VALUES (?, datetime('now'), ?)
            ");
            // Check driver syntax for MySQL vs SQLite
            if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $stmt = $this->db->prepare("
                    INSERT INTO transaksi (id_akun, tanggal_transaksi, total_harga)
                    VALUES (?, NOW(), ?)
                ");
            }
            $stmt->execute([$id_akun, $total_harga]);
            $id_transaksi = $this->db->lastInsertId();

            // 2. Simpan Detail Item dan Kurangi Stok
            $stmtDetail = $this->db->prepare("
                INSERT INTO detail_penjualan (id_transaksi, id_produk, jumlah, harga_jual, hpp, subtotal)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmtStok = $this->db->prepare("
                UPDATE stok SET jumlah_stok = jumlah_stok - ? WHERE id_produk = ?
            ");

            $stmtMutasi = $this->db->prepare("
                INSERT INTO mutasi_stok (id_produk, jenis_mutasi, jumlah, alasan, tanggal_mutasi)
                VALUES (?, 'Keluar', ?, ?, datetime('now'))
            ");
            if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $stmtMutasi = $this->db->prepare("
                    INSERT INTO mutasi_stok (id_produk, jenis_mutasi, jumlah, alasan, tanggal_mutasi)
                    VALUES (?, 'Keluar', ?, ?, NOW())
                ");
            }

            foreach ($items as $item) {
                $subtotal = $item['harga_jual'] * $item['qty'];
                $hpp = isset($item['harga_modal']) ? $item['harga_modal'] : ($item['harga_jual'] * 0.85);

                $stmtDetail->execute([
                    $id_transaksi,
                    $item['id_produk'],
                    $item['qty'],
                    $item['harga_jual'],
                    $hpp,
                    $subtotal
                ]);

                // Kurangi stok di database
                $stmtStok->execute([$item['qty'], $item['id_produk']]);

                // Catat mutasi stok keluar
                $stmtMutasi->execute([
                    $item['id_produk'],
                    $item['qty'],
                    "Penjualan POS No Transaksi #{$id_transaksi}"
                ]);
            }

            $this->db->commit();

            return [
                'status' => true,
                'id_transaksi' => $id_transaksi,
                'kembalian' => ($metode_bayar === 'Tunai') ? ($nominal_bayar - $total_harga) : 0,
                'message' => 'Transaksi penjualan berhasil diproses dan disimpan!'
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['status' => false, 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()];
        }
    }
}
