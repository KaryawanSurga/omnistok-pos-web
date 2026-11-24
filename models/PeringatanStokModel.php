<?php
/**
 * Model: PeringatanStokModel
 * Penulis: Fauzi Al Wahid (FW - 255150400111016)
 * Referensi: UC-05 (Peringatan Stok Menipis) & UC-06 (Peringatan Barang Kadaluwarsa)
 * Menangani seluruh akses data basis data untuk tabel `produk`, `stok`, dan `batch_produk`.
 */

class PeringatanStokModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * [UC-05] Mengambil daftar produk aktif yang total stok fisiknya <= batas_minimum.
     */
    public function get_stok_menipis(): array
    {
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
            WHERE p.status_aktif = 1
            GROUP BY p.id_produk, p.nama_produk, p.kategori, p.harga_jual
            HAVING COALESCE(SUM(s.jumlah_stok), 0) <= COALESCE(MIN(s.batas_minimum), 5)
            ORDER BY total_stok ASC, p.nama_produk ASC
        ");

        return $stmt->fetchAll();
    }

    /**
     * [UC-06] Mengambil daftar seluruh batch produk beserta tanggal kedaluwarsa dan sisa stoknya.
     */
    public function get_batch_produk(): array
    {
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

        return $stmt->fetchAll();
    }
}
