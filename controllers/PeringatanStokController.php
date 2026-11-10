<?php
/**
 * Kontroler: PeringatanStokController
 * Penulis: Fauzi Al Wahid (FW - 255150400111016)
 * Referensi CD: CD-05 (Melihat Peringatan Stok Menipis) & CD-06 (Melihat Peringatan Barang Kadaluwarsa)
 * Hak Akses: Pemilik Toko (owner) & Petugas Stok (gudang)
 */

require_once __DIR__ . '/../config/database.php';

class PeringatanStokController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * [UC-05] Mendapatkan daftar produk yang stok fisiknya menipis atau habis di bawah batas minimum
     */
    public function get_peringatan_stok_menipis() {
        $stmt = $this->db->query("
            SELECT 
                p.id_produk,
                p.nama_produk,
                p.kategori,
                p.harga_jual,
                COALESCE(SUM(s.jumlah_stok), 0) AS total_stok,
                COALESCE(MIN(s.batas_minimum), 5) AS batas_minimum,
                CASE 
                    WHEN COALESCE(SUM(s.jumlah_stok), 0) = 0 THEN 'HABIS'
                    WHEN COALESCE(SUM(s.jumlah_stok), 0) <= COALESCE(MIN(s.batas_minimum), 5) THEN 'MENIPIS'
                    ELSE 'AMAN'
                END AS status_stok
            FROM produk p
            LEFT JOIN stok s ON p.id_produk = s.id_produk
            GROUP BY p.id_produk, p.nama_produk, p.kategori, p.harga_jual
            HAVING COALESCE(SUM(s.jumlah_stok), 0) <= COALESCE(MIN(s.batas_minimum), 5)
            ORDER BY total_stok ASC, p.nama_produk ASC
        ");
        $items = $stmt->fetchAll();

        $totalHabis = 0;
        $totalMenipis = 0;
        foreach ($items as $item) {
            if ($item['status_stok'] === 'HABIS') {
                $totalHabis++;
            } else {
                $totalMenipis++;
            }
        }

        return [
            'total_peringatan' => count($items),
            'total_habis' => $totalHabis,
            'total_menipis' => $totalMenipis,
            'items' => $items
        ];
    }

    /**
     * [UC-06] Mendapatkan daftar batch produk yang telah kadaluwarsa atau mendekati masa kadaluwarsa (<= 90 hari)
     */
    public function get_peringatan_kadaluwarsa() {
        $today = date('Y-m-d');

        $stmt = $this->db->query("
            SELECT 
                b.id_batch,
                b.id_produk,
                p.nama_produk,
                p.kategori,
                b.tanggal_kadaluwarsa,
                COALESCE(SUM(s.jumlah_stok), 0) AS sisa_stok,
                COALESCE(MIN(s.lokasi_stok), 'Gudang Utama') AS lokasi_stok
            FROM batch_produk b
            JOIN produk p ON b.id_produk = p.id_produk
            LEFT JOIN stok s ON b.id_batch = s.id_batch
            GROUP BY b.id_batch, b.id_produk, p.nama_produk, p.kategori, b.tanggal_kadaluwarsa
            ORDER BY b.tanggal_kadaluwarsa ASC
        ");
        $raw = $stmt->fetchAll();

        $items = [];
        $totalExpired = 0;
        $totalWarning = 0;
        $totalSafe = 0;

        foreach ($raw as $row) {
            $tglExp = $row['tanggal_kadaluwarsa'];
            $diffDays = (int)round((strtotime($tglExp) - strtotime($today)) / 86400);

            if ($diffDays < 0) {
                $status = 'KADALUWARSA';
                $label = 'Kedaluwarsa ' . abs($diffDays) . ' hari lalu';
                $pillClass = 'pill-red';
                $totalExpired++;
            } elseif ($diffDays === 0) {
                $status = 'HARI_INI';
                $label = 'Kedaluwarsa Hari Ini';
                $pillClass = 'pill-red';
                $totalExpired++;
            } elseif ($diffDays <= 90) {
                $status = 'MENDEKATI';
                $label = $diffDays . ' hari lagi';
                $pillClass = 'pill-orange';
                $totalWarning++;
            } else {
                $status = 'AMAN';
                $label = $diffDays . ' hari lagi';
                $pillClass = 'pill-green';
                $totalSafe++;
            }

            $row['selisih_hari'] = $diffDays;
            $row['status_exp'] = $status;
            $row['label_exp'] = $label;
            $row['pill_class'] = $pillClass;

            $items[] = $row;
        }

        return [
            'total_batch' => count($items),
            'total_expired' => $totalExpired,
            'total_warning' => $totalWarning,
            'total_safe' => $totalSafe,
            'items' => $items
        ];
    }
}
