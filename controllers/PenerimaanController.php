<?php
/**
 * Kontroler: PenerimaanController
 * Penulis: Zainal Maarif (ZM - 255150400111070)
 * Referensi CD: CD-09 (Mencatat Penerimaan Barang)
 * Referensi Pseudocode: PC-09.01 s/d PC-09.05
 */

require_once __DIR__ . '/../config/database.php';

class PenerimaanController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // [PC-09.01] Mengambil daftar pesanan pembelian yang berstatus 'Diproses'
    public function ambilDaftarPesanan() {
        $stmt = $this->db->query("
            SELECT po.id_pesanan, po.nomor_pesanan, po.status_pesanan, 
                   p.id_pemasok, p.nama_pemasok
            FROM pesanan po
            JOIN pemasok p ON po.id_pemasok = p.id_pemasok
            WHERE po.status_pesanan = 'Diproses'
            ORDER BY po.id_pesanan DESC
        ");
        return $stmt->fetchAll();
    }

    // [PC-09.02] Mengambil detail item barang dalam pesanan pembelian (PO)
    public function ambilDetailPesanan($id_pesanan) {
        $stmt = $this->db->prepare("
            SELECT dp.*, p.nama_produk, p.kategori, p.harga_modal
            FROM detail_pesanan dp
            JOIN produk p ON dp.id_produk = p.id_produk
            WHERE dp.id_pesanan = ?
        ");
        $stmt->execute([$id_pesanan]);
        return $stmt->fetchAll();
    }

    // [PC-09.03 s/d PC-09.05] Memproses penerimaan barang fisik dari pemasok
    public function prosesPenerimaan($data) {
        $id_pesanan = $data['id_pesanan'];
        $id_pemasok = $data['id_pemasok'];
        $nomor_nota = trim($data['nomor_nota']);
        $tanggal_penerimaan = $data['tanggal_penerimaan'];
        $items = $data['items']; // array of [id_produk, jumlah_diterima, tgl_kadaluwarsa]

        if (empty($id_pesanan) || empty($nomor_nota) || empty($items)) {
            return [
                'status' => false,
                'code' => 'EMPTY_FIELDS',
                'message' => 'Nomor nota faktur dan rincian barang fisik wajib diisi!'
            ];
        }

        // [PC-09.05] Cek apakah nomor nota sudah pernah diinput sebelumnya
        $stmtCheck = $this->db->prepare("SELECT id_penerimaan FROM penerimaan WHERE nomor_nota = ?");
        $stmtCheck->execute([$nomor_nota]);
        if ($stmtCheck->fetch()) {
            return $this->hasilNotaSudahAda();
        }

        try {
            $this->db->beginTransaction();

            // 1. Catat Penerimaan Induk
            $stmt = $this->db->prepare("
                INSERT INTO penerimaan (id_pemasok, id_pesanan, nomor_nota, tanggal_penerimaan)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$id_pemasok, $id_pesanan, $nomor_nota, $tanggal_penerimaan]);
            $id_penerimaan = $this->db->lastInsertId();

            // 2. Simpan Batch Produk, Detail Penerimaan, dan Tambah Stok
            $stmtBatch = $this->db->prepare("
                INSERT INTO batch_produk (id_produk, tanggal_kadaluwarsa)
                VALUES (?, ?)
            ");

            $stmtDetail = $this->db->prepare("
                INSERT INTO detail_penerimaan (id_penerimaan, id_produk, id_batch, jumlah_diterima)
                VALUES (?, ?, ?, ?)
            ");

            $stmtStok = $this->db->prepare("
                UPDATE stok SET jumlah_stok = jumlah_stok + ? WHERE id_produk = ?
            ");

            $stmtMutasi = $this->db->prepare("
                INSERT INTO mutasi_stok (id_produk, jenis_mutasi, jumlah, alasan, tanggal_mutasi)
                VALUES (?, 'Masuk', ?, ?, datetime('now'))
            ");
            if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $stmtMutasi = $this->db->prepare("
                    INSERT INTO mutasi_stok (id_produk, jenis_mutasi, jumlah, alasan, tanggal_mutasi)
                    VALUES (?, 'Masuk', ?, ?, NOW())
                ");
            }

            foreach ($items as $item) {
                // Buat nomor batch baru dengan tanggal kadaluwarsa
                $tglExp = !empty($item['tgl_kadaluwarsa']) ? $item['tgl_kadaluwarsa'] : date('Y-m-d', strtotime('+1 year'));
                $stmtBatch->execute([$item['id_produk'], $tglExp]);
                $id_batch = $this->db->lastInsertId();

                // Catat detail penerimaan
                $stmtDetail->execute([$id_penerimaan, $item['id_produk'], $id_batch, $item['jumlah_diterima']]);

                // Update stok produk
                $stmtStok->execute([$item['jumlah_diterima'], $item['id_produk']]);

                // Catat riwayat mutasi stok masuk
                $stmtMutasi->execute([
                    $item['id_produk'],
                    $item['jumlah_diterima'],
                    "Penerimaan Barang Nota {$nomor_nota} (PO #{$id_pesanan})"
                ]);
            }

            // 3. Update status pesanan pembelian menjadi 'Selesai'
            $stmtUpdatePO = $this->db->prepare("UPDATE pesanan SET status_pesanan = 'Selesai' WHERE id_pesanan = ?");
            $stmtUpdatePO->execute([$id_pesanan]);

            $this->db->commit();

            return $this->hasilPenerimaanSukses($id_penerimaan, $nomor_nota);
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['status' => false, 'code' => 'ERROR', 'message' => 'Gagal memproses penerimaan: ' . $e->getMessage()];
        }
    }

    // [PC-09.04] Respons sukses penerimaan barang
    public function hasilPenerimaanSukses($idPenerimaan, $noNota) {
        return [
            'status' => true,
            'code' => 'SUCCESS',
            'id_penerimaan' => $idPenerimaan,
            'nomor_nota' => $noNota,
            'message' => "Penerimaan barang faktur [{$noNota}] berhasil dicatat! Stok barang otomatis bertambah."
        ];
    }

    // [PC-09.05] Respons error nomor nota duplikat
    public function hasilNotaSudahAda() {
        return [
            'status' => false,
            'code' => 'DUPLICATE_NOTA',
            'message' => 'Peringatan: Nomor nota faktur sudah pernah dicatat dalam sistem!'
        ];
    }

    // Mengambil riwayat penerimaan barang terkini
    public function ambilRiwayatPenerimaan() {
        $stmt = $this->db->query("
            SELECT pen.*, pem.nama_pemasok, po.nomor_pesanan,
                   COUNT(dp.id_detail_penerimaan) as total_item,
                   SUM(dp.jumlah_diterima) as total_qty
            FROM penerimaan pen
            JOIN pemasok pem ON pen.id_pemasok = pem.id_pemasok
            JOIN pesanan po ON pen.id_pesanan = po.id_pesanan
            LEFT JOIN detail_penerimaan dp ON pen.id_penerimaan = dp.id_penerimaan
            GROUP BY pen.id_penerimaan
            ORDER BY pen.tanggal_penerimaan DESC, pen.id_penerimaan DESC
        ");
        return $stmt->fetchAll();
    }
}
