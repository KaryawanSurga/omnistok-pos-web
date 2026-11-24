<?php
/**
 * Model: MutasiStokModel
 * Penulis: Fauzi Al Wahid (FW - 255150400111016)
 * Referensi: UC-01 (Melihat Laporan Mutasi Stok) & SD-01 (mutasi_stok_model)
 * Menangani seluruh akses data basis data untuk tabel `mutasi_stok`, `produk`, dan `stok`.
 */

class MutasiStokModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * [SD-01 / mutasi_stok_model.get_data_mutasi]
     * Mengambil data riwayat mutasi stok pada rentang periode tertentu.
     */
    public function get_data_mutasi(string $tgl_mulai, string $tgl_akhir): array
    {
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

        return $stmt->fetchAll();
    }

    /**
     * Mengambil ringkasan ketersediaan stok fisik gudang saat ini.
     */
    public function get_ringkasan_stok(): array
    {
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
            WHERE p.status_aktif = 1
            GROUP BY p.id_produk
            ORDER BY status_stok DESC, p.nama_produk ASC
        ");

        return $stmt->fetchAll();
    }
}
