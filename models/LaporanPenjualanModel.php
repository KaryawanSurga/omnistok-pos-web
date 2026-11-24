<?php
/**
 * Model: LaporanPenjualanModel
 * Penulis: Fauzi Al Wahid (FW - 255150400111016)
 * Referensi: UC-03 (Melihat Laporan Penjualan dan Laba) & SD-06 (penjualan_model)
 * Menangani seluruh akses data basis data untuk tabel `transaksi`, `detail_penjualan`, dan `produk`.
 */

class LaporanPenjualanModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * [SD-06 / penjualan_model.get_transaksi_dan_hpp]
     * Mengambil transaksi berhasil beserta HPP historis sesuai periode.
     */
    public function get_transaksi_dan_hpp(string $tgl_mulai, string $tgl_akhir): array
    {
        $startDate = $tgl_mulai . ' 00:00:00';
        $endDate   = $tgl_akhir . ' 23:59:59';

        $stmt = $this->db->prepare("
            SELECT
                dp.id_detail_penjualan,
                dp.id_transaksi,
                t.tanggal_transaksi,
                dp.id_produk,
                p.nama_produk,
                p.kategori,
                dp.jumlah AS jumlah_beli,
                dp.harga_jual,
                dp.hpp,
                (dp.hpp * dp.jumlah) AS hpp_total,
                dp.subtotal
            FROM detail_penjualan dp
            JOIN transaksi t ON dp.id_transaksi = t.id_transaksi
            JOIN produk p ON dp.id_produk = p.id_produk
            WHERE t.tanggal_transaksi BETWEEN ? AND ?
            ORDER BY t.tanggal_transaksi DESC, dp.id_detail_penjualan ASC
        ");
        $stmt->execute([$startDate, $endDate]);

        return $stmt->fetchAll();
    }

    /**
     * [SD-06 / penjualan_model.get_data_detail]
     * Mengambil baris detail penjualan sesuai periode dan jenis_detail.
     */
    public function get_data_detail(string $tgl_mulai, string $tgl_akhir, string $jenis_detail = 'produk'): array
    {
        $startDate = $tgl_mulai . ' 00:00:00';
        $endDate   = $tgl_akhir . ' 23:59:59';

        $stmt = $this->db->prepare("
            SELECT
                dp.id_detail_penjualan,
                dp.id_transaksi,
                t.tanggal_transaksi,
                t.metode_bayar,
                dp.id_produk,
                p.nama_produk,
                p.kategori,
                dp.jumlah AS jumlah_beli,
                dp.harga_jual,
                dp.hpp,
                (dp.hpp * dp.jumlah) AS hpp_total,
                dp.subtotal
            FROM detail_penjualan dp
            JOIN transaksi t ON dp.id_transaksi = t.id_transaksi
            JOIN produk p ON dp.id_produk = p.id_produk
            WHERE t.tanggal_transaksi BETWEEN ? AND ?
            ORDER BY t.tanggal_transaksi DESC, p.nama_produk ASC
        ");
        $stmt->execute([$startDate, $endDate]);

        return $stmt->fetchAll();
    }

    /**
     * Mengambil rekap agregat harian penjualan, HPP, dan laba kotor untuk grafik tren.
     */
    public function get_tren_harian_raw(string $tgl_mulai, string $tgl_akhir): array
    {
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

        return $stmt->fetchAll();
    }
}
