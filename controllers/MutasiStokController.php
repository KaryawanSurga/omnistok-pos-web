<?php
/**
 * Kontroler: MutasiStokController
 * Penulis: Fauzi Al Wahid (FW - 255150400111016)
 * Referensi CD: CD-01 (Melihat Laporan Mutasi Stok)
 * Referensi Pseudocode: PC-01.01 s/d PC-01.03
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ReportPdfHelper.php';

class MutasiStokController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // [PC-01.01] Membuka tampilan laporan mutasi stok dan memuat parameter default
    public function buka_laporan_mutasi() {
        $tgl_akhir = date('Y-m-d');
        $tgl_mulai = date('Y-m-d', strtotime('-30 days'));
        return [
            'tgl_mulai' => $tgl_mulai,
            'tgl_akhir' => $tgl_akhir
        ];
    }

    // [PC-01.03] Memvalidasi keabsahan format dan rentang periode laporan
    public function validasi_periode($tgl_mulai, $tgl_akhir) {
        if (empty($tgl_mulai) || empty($tgl_akhir)) {
            return ['valid' => false, 'message' => 'Tanggal awal dan akhir periode wajib ditentukan!'];
        }

        $timeMulai = strtotime($tgl_mulai);
        $timeAkhir = strtotime($tgl_akhir);

        if ($timeMulai === false || $timeAkhir === false) {
            return ['valid' => false, 'message' => 'Format tanggal yang dimasukkan tidak valid!'];
        }

        if ($timeMulai > $timeAkhir) {
            return ['valid' => false, 'message' => 'Tanggal mulai tidak boleh lebih besar dari tanggal akhir!'];
        }

        return ['valid' => true, 'message' => 'Periode valid'];
    }

    // [PC-01.02] Mengambil data riwayat mutasi stok berdasarkan periode waktu yang ditentukan
    public function get_laporan_mutasi($tgl_mulai, $tgl_akhir) {
        $validasi = $this->validasi_periode($tgl_mulai, $tgl_akhir);
        if (!$validasi['valid']) {
            return ['status' => false, 'message' => $validasi['message'], 'total_data' => 0, 'data' => []];
        }

        $startDate = $tgl_mulai . ' 00:00:00';
        $endDate   = $tgl_akhir . ' 23:59:59';

        $stmt = $this->db->prepare("
            SELECT m.id_mutasi, m.id_produk, p.nama_produk, p.kategori,
                   m.jenis_mutasi, m.jumlah, m.alasan, m.tanggal_mutasi
            FROM mutasi_stok m
            JOIN produk p ON m.id_produk = p.id_produk
            WHERE m.tanggal_mutasi BETWEEN ? AND ?
            ORDER BY m.tanggal_mutasi DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $rows = $stmt->fetchAll();

        return [
            'status' => true,
            'message' => 'Data mutasi stok berhasil dimuat.',
            'periode' => ['mulai' => $tgl_mulai, 'akhir' => $tgl_akhir],
            'total_data' => count($rows),
            'data' => $rows
        ];
    }

    // Mengambil ringkasan ketersediaan stok fisik gudang saat ini
    public function get_ringkasan_stok() {
        $stmt = $this->db->query("
            SELECT p.id_produk, p.nama_produk, p.kategori, p.harga_jual,
                   COALESCE(SUM(s.jumlah_stok), 0) AS total_stok,
                   COALESCE(MIN(s.batas_minimum), 5) AS batas_minimum,
                   CASE 
                       WHEN COALESCE(SUM(s.jumlah_stok), 0) <= COALESCE(MIN(s.batas_minimum), 5) THEN 'MENIPIS'
                       ELSE 'AMAN'
                   END AS status_stok
            FROM produk p
            LEFT JOIN stok s ON p.id_produk = s.id_produk
            GROUP BY p.id_produk
            ORDER BY status_stok DESC, p.nama_produk ASC
        ");
        return $stmt->fetchAll();
    }

    // [PC-01.04] Mengunduh berkas laporan mutasi stok dalam format PDF
    public function unduh_pdf($tgl_mulai, $tgl_akhir) {
        $hasilMutasi = $this->get_laporan_mutasi($tgl_mulai, $tgl_akhir);
        $ringkasanStok = $this->get_ringkasan_stok();
        ReportPdfHelper::generateLaporanMutasi(
            $tgl_mulai,
            $tgl_akhir,
            $hasilMutasi,
            $ringkasanStok
        );
    }
}
