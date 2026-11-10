<?php
/**
 * Kontroler: LaporanPenjualanController
 * Penulis: Fauzi Al Wahid (FW - 255150400111016)
 * Referensi CD: CD-03 (Melihat Laporan Penjualan dan Laba)
 * Referensi Pseudocode: PC-03.01 s/d PC-03.04
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ReportPdfHelper.php';

class LaporanPenjualanController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * [PC-03.01] Membuka tampilan awal laporan penjualan & laba dengan rentang default
     */
    public function buka_laporan_penjualan() {
        $tgl_akhir = date('Y-m-d');
        $tgl_mulai = date('Y-m-d', strtotime('-6 days')); // default rentang 7 hari terakhir
        return [
            'tgl_mulai' => $tgl_mulai,
            'tgl_akhir' => $tgl_akhir
        ];
    }

    /**
     * [PC-03.02] Memvalidasi keabsahan parameter rentang periode tanggal
     */
    public function validasi_periode($tgl_mulai, $tgl_akhir) {
        if (empty($tgl_mulai) || empty($tgl_akhir)) {
            return [
                'valid' => false,
                'message' => 'Tanggal awal dan tanggal akhir periode wajib diisi!'
            ];
        }

        $timeMulai = strtotime($tgl_mulai);
        $timeAkhir = strtotime($tgl_akhir);

        if ($timeMulai === false || $timeAkhir === false) {
            return [
                'valid' => false,
                'message' => 'Format tanggal yang dimasukkan tidak valid!'
            ];
        }

        if ($timeMulai > $timeAkhir) {
            return [
                'valid' => false,
                'message' => 'Tanggal mulai tidak boleh lebih besar dari tanggal akhir periode!'
            ];
        }

        return [
            'valid' => true,
            'message' => 'Periode valid.'
        ];
    }

    /**
     * [PC-03.03] Menghitung metrik ringkasan, rincian per produk, dan tren harian penjualan & laba
     */
    public function get_laporan_penjualan($tgl_mulai, $tgl_akhir) {
        $validasi = $this->validasi_periode($tgl_mulai, $tgl_akhir);
        if (!$validasi['valid']) {
            return [
                'status' => false,
                'message' => $validasi['message'],
                'ringkasan' => [
                    'penjualan_bersih' => 0,
                    'total_hpp' => 0,
                    'laba_kotor' => 0,
                    'margin' => 0,
                    'total_qty' => 0,
                    'total_sku' => 0,
                    'jumlah_hari' => 0,
                ],
                'tren_harian' => [],
                'detail_produk' => []
            ];
        }

        $startDate = $tgl_mulai . ' 00:00:00';
        $endDate   = $tgl_akhir . ' 23:59:59';

        // 1. Rincian Penjualan per Produk
        $stmtDetail = $this->db->prepare("
            SELECT 
                p.id_produk,
                p.nama_produk,
                p.kategori,
                COALESCE(SUM(dp.jumlah), 0) AS total_qty,
                COALESCE(SUM(dp.subtotal), 0) AS total_penjualan,
                COALESCE(SUM(dp.hpp * dp.jumlah), 0) AS total_hpp,
                COALESCE(SUM(dp.subtotal - (dp.hpp * dp.jumlah)), 0) AS laba_kotor,
                CASE 
                    WHEN SUM(dp.subtotal) > 0 THEN 
                        ROUND((SUM(dp.subtotal - (dp.hpp * dp.jumlah)) / SUM(dp.subtotal)) * 100, 2)
                    ELSE 0 
                END AS margin
            FROM detail_penjualan dp
            JOIN transaksi t ON dp.id_transaksi = t.id_transaksi
            JOIN produk p ON dp.id_produk = p.id_produk
            WHERE t.tanggal_transaksi BETWEEN ? AND ?
            GROUP BY p.id_produk, p.nama_produk, p.kategori
            ORDER BY total_penjualan DESC, total_qty DESC
        ");
        $stmtDetail->execute([$startDate, $endDate]);
        $detailProduk = $stmtDetail->fetchAll();

        // 2. Metrik Agregat Ringkasan Periode
        $totalPenjualan = 0;
        $totalHpp = 0;
        $totalQty = 0;
        foreach ($detailProduk as $row) {
            $totalPenjualan += (float)$row['total_penjualan'];
            $totalHpp += (float)$row['total_hpp'];
            $totalQty += (int)$row['total_qty'];
        }

        $labaKotor = $totalPenjualan - $totalHpp;
        $margin = ($totalPenjualan > 0) ? round(($labaKotor / $totalPenjualan) * 100, 2) : 0;
        $totalSku = count($detailProduk);

        $days = (int)round((strtotime($tgl_akhir) - strtotime($tgl_mulai)) / 86400) + 1;

        $ringkasan = [
            'penjualan_bersih' => $totalPenjualan,
            'total_hpp' => $totalHpp,
            'laba_kotor' => $labaKotor,
            'margin' => $margin,
            'total_qty' => $totalQty,
            'total_sku' => $totalSku,
            'jumlah_hari' => $days
        ];

        // 3. Tren Harian Penjualan & Laba Kotor untuk Chart
        $trenHarian = $this->get_tren_harian($tgl_mulai, $tgl_akhir);

        return [
            'status' => true,
            'message' => 'Laporan penjualan dan laba kotor berhasil dimuat.',
            'periode' => ['mulai' => $tgl_mulai, 'akhir' => $tgl_akhir],
            'ringkasan' => $ringkasan,
            'tren_harian' => $trenHarian,
            'detail_produk' => $detailProduk
        ];
    }

    /**
     * Mengambil rekap transaksi harian untuk visualisasi kurva grafik tren
     */
    private function get_tren_harian($tgl_mulai, $tgl_akhir) {
        $startDate = $tgl_mulai . ' 00:00:00';
        $endDate   = $tgl_akhir . ' 23:59:59';

        $stmt = $this->db->prepare("
            SELECT 
                SUBSTR(t.tanggal_transaksi, 1, 10) AS tgl,
                COALESCE(SUM(dp.subtotal), 0) AS penjualan,
                COALESCE(SUM(dp.hpp * dp.jumlah), 0) AS hpp,
                COALESCE(SUM(dp.subtotal - (dp.hpp * dp.jumlah)), 0) AS laba
            FROM detail_penjualan dp
            JOIN transaksi t ON dp.id_transaksi = t.id_transaksi
            WHERE t.tanggal_transaksi BETWEEN ? AND ?
            GROUP BY SUBSTR(t.tanggal_transaksi, 1, 10)
            ORDER BY tgl ASC
        ");
        $stmt->execute([$startDate, $endDate]);
        $rows = $stmt->fetchAll();

        $lookup = [];
        foreach ($rows as $r) {
            $lookup[$r['tgl']] = $r;
        }

        // Susun rentang tanggal lengkap dari tgl_mulai hingga tgl_akhir
        $list = [];
        $cur = strtotime($tgl_mulai);
        $last = strtotime($tgl_akhir);
        // Batasi maksimal 31 titik agar grafik tetap proporsional
        $stepDays = 1;
        $totalDays = (int)round(($last - $cur) / 86400) + 1;
        if ($totalDays > 31) {
            $stepDays = ceil($totalDays / 31);
        }

        while ($cur <= $last) {
            $dStr = date('Y-m-d', $cur);
            if (isset($lookup[$dStr])) {
                $list[] = [
                    'tanggal' => $dStr,
                    'label' => date('d M', $cur),
                    'penjualan' => (float)$lookup[$dStr]['penjualan'],
                    'hpp' => (float)$lookup[$dStr]['hpp'],
                    'laba' => (float)$lookup[$dStr]['laba'],
                ];
            } else {
                $list[] = [
                    'tanggal' => $dStr,
                    'label' => date('d M', $cur),
                    'penjualan' => 0,
                    'hpp' => 0,
                    'laba' => 0,
                ];
            }
            $cur = strtotime("+$stepDays days", $cur);
        }

        return $list;
    }

    /**
     * [PC-03.04] Mengunduh berkas laporan penjualan dan laba kotor dalam format PDF
     */
    public function unduh_pdf($tgl_mulai, $tgl_akhir) {
        $data = $this->get_laporan_penjualan($tgl_mulai, $tgl_akhir);
        ReportPdfHelper::generateLaporanPenjualan(
            $tgl_mulai,
            $tgl_akhir,
            $data['ringkasan'],
            $data['detail_produk']
        );
    }
}
