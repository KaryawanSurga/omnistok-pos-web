<?php
/**
 * Kontroler: TransaksiController
 * Penulis: Ferdyansah (FF - 255150400111039)
 * Referensi CD: CD-07 (Mencatat Transaksi Penjualan POS)
 *                CD-08 (Merekonsiliasi Kas Shift)
 * Referensi Pseudocode: PC-07.1 s/d PC-07.16, PC-08.1 s/d PC-08.9
 */

require_once __DIR__ . '/../config/database.php';

class TransaksiController {
    /** Modal awal laci kasir setiap shift (Rp) */
    const MODAL_AWAL = 500000;

    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Ekspresi waktu sesuai driver database aktif.
     * SQLite memakai datetime('now','localtime'), MySQL memakai NOW().
     */
    private function nowExpression() {
        return ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql')
            ? 'NOW()'
            : "datetime('now','localtime')";
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

        $id_akun      = (int) $id_akun;
        $metode_bayar = (strtoupper((string) $metode_bayar) === 'QRIS') ? 'QRIS' : 'Tunai';
        $total_harga  = round((float) $total_harga, 2);
        $nominal_bayar = round((float) $nominal_bayar, 2);

        if ($id_akun <= 0) {
            return ['status' => false, 'message' => 'Akun kasir tidak valid. Silakan login kembali.'];
        }

        if ($total_harga <= 0) {
            return ['status' => false, 'message' => 'Total tagihan transaksi tidak valid!'];
        }

        if ($metode_bayar === 'Tunai' && $nominal_bayar < $total_harga) {
            return ['status' => false, 'message' => 'Nominal pembayaran tunai kurang dari total tagihan!'];
        }

        if ($metode_bayar === 'QRIS') {
            // Pembayaran QRIS selalu lunas sesuai nominal tagihan.
            $nominal_bayar = $total_harga;
        }

        $kembalian = ($metode_bayar === 'Tunai')
            ? max(0, $nominal_bayar - $total_harga)
            : 0;

        try {
            $this->db->beginTransaction();

            // 1. Simpan transaksi induk beserta informasi pembayaran
            $stmt = $this->db->prepare("
                INSERT INTO transaksi (id_akun, tanggal_transaksi, total_harga, metode_bayar, nominal_bayar, kembalian)
                VALUES (?, {$this->nowExpression()}, ?, ?, ?, ?)
            ");
            $stmt->execute([$id_akun, $total_harga, $metode_bayar, $nominal_bayar, $kembalian]);
            $id_transaksi = (int) $this->db->lastInsertId();

            // 2. Simpan detail item dan kurangi stok
            $stmtDetail = $this->db->prepare("
                INSERT INTO detail_penjualan (id_transaksi, id_produk, jumlah, harga_jual, hpp, subtotal)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmtStok = $this->db->prepare("
                UPDATE stok
                SET jumlah_stok = jumlah_stok - ?
                WHERE id_produk = ? AND jumlah_stok >= ?
            ");

            $stmtMutasi = $this->db->prepare("
                INSERT INTO mutasi_stok (id_produk, jenis_mutasi, jumlah, alasan, tanggal_mutasi)
                VALUES (?, 'Keluar', ?, ?, {$this->nowExpression()})
            ");

            $stmtProduk = $this->db->prepare("SELECT nama_produk, harga_modal FROM produk WHERE id_produk = ?");

            foreach ($items as $item) {
                $id_produk = (int) $item['id_produk'];
                $qty       = (int) $item['qty'];
                $harga     = (float) $item['harga_jual'];
                $diskon    = isset($item['diskon']) ? (float) $item['diskon'] : 0.0;

                if ($qty <= 0) {
                    continue;
                }

                $stmtProduk->execute([$id_produk]);
                $produk = $stmtProduk->fetch();

                if (!$produk) {
                    throw new Exception("Produk dengan ID {$id_produk} tidak ditemukan.");
                }

                // HPP memakai harga modal asli produk agar laporan laba akurat
                $hpp = isset($item['harga_modal']) && $item['harga_modal'] !== null
                    ? (float) $item['harga_modal']
                    : (float) $produk['harga_modal'];

                $subtotal = round(max(0, $harga - $diskon) * $qty, 2);

                $stmtDetail->execute([
                    $id_transaksi,
                    $id_produk,
                    $qty,
                    $harga,
                    $hpp,
                    $subtotal
                ]);

                // Kurangi stok; bila stok tidak cukup, seluruh transaksi dibatalkan
                $stmtStok->execute([$qty, $id_produk, $qty]);
                if ($stmtStok->rowCount() === 0) {
                    throw new Exception("Stok produk '{$produk['nama_produk']}' tidak mencukupi untuk jumlah {$qty}.");
                }

                // Catat mutasi stok keluar
                $stmtMutasi->execute([
                    $id_produk,
                    $qty,
                    "Penjualan POS No Transaksi #{$id_transaksi}"
                ]);
            }

            $this->db->commit();

            return [
                'status'       => true,
                'id_transaksi' => $id_transaksi,
                'metode_bayar' => $metode_bayar,
                'nominal_bayar' => $nominal_bayar,
                'kembalian'    => $kembalian,
                'message'      => 'Transaksi penjualan berhasil diproses dan disimpan!'
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['status' => false, 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()];
        }
    }

    /**
     * [PC-08.1 s/d PC-08.4] Rekap transaksi pada shift kasir yang sedang berjalan.
     * Transaksi dihitung sejak penutupan shift (rekonsiliasi) terakhir.
     */
    public function get_ringkasan_shift() {
        $tanggalTerakhir = $this->db->query("SELECT MAX(tanggal) FROM rekonsiliasi")->fetchColumn();

        $filter = '';
        $params = [];
        if (!empty($tanggalTerakhir)) {
            $filter = ' AND tanggal_transaksi > ? ';
            $params = [$tanggalTerakhir];
        }

        $stmtTunai = $this->db->prepare("
            SELECT COALESCE(SUM(total_harga), 0) AS total, COUNT(*) AS jumlah
            FROM transaksi
            WHERE metode_bayar = 'Tunai' {$filter}
        ");
        $stmtTunai->execute($params);
        $tunai = $stmtTunai->fetch();

        $stmtQris = $this->db->prepare("
            SELECT COALESCE(SUM(total_harga), 0) AS total, COUNT(*) AS jumlah
            FROM transaksi
            WHERE metode_bayar = 'QRIS' {$filter}
        ");
        $stmtQris->execute($params);
        $qris = $stmtQris->fetch();

        $modalAwal   = self::MODAL_AWAL;
        $totalTunai  = (float) ($tunai['total'] ?? 0);
        $totalQris   = (float) ($qris['total'] ?? 0);

        return [
            'modal_awal'         => $modalAwal,
            'total_tunai'        => $totalTunai,
            'total_qris'         => $totalQris,
            'jumlah_tunai'       => (int) ($tunai['jumlah'] ?? 0),
            'jumlah_qris'        => (int) ($qris['jumlah'] ?? 0),
            'kas_sistem'         => $modalAwal + $totalTunai,
            'sejak'              => $tanggalTerakhir,
        ];
    }

    /**
     * [PC-08.5 s/d PC-08.9] Menyimpan berita acara rekonsiliasi / tutup shift.
     * Total tunai & QRIS dihitung ulang di server (tidak memercayai input form).
     */
    public function simpan_rekonsiliasi($kas_fisik, $catatan = '') {
        $kas_fisik = round((float) $kas_fisik, 2);

        if ($kas_fisik < 0) {
            return ['status' => false, 'message' => 'Kas fisik tidak boleh bernilai negatif!'];
        }

        $ringkasan = $this->get_ringkasan_shift();
        $kasSistem = $ringkasan['kas_sistem'];
        $selisih   = round($kas_fisik - $kasSistem, 2);

        if (abs($selisih) < 0.01) {
            $selisih = 0.0;
            $status  = 'Cocok (Balance)';
        } elseif ($selisih < 0) {
            $status = 'Selisih Kurang';
        } else {
            $status = 'Selisih Lebih';
        }

        try {
            // Referensi transaksi terakhir pada shift ini (boleh NULL bila belum ada transaksi)
            $idTransaksiTerakhir = $this->db->query("SELECT MAX(id_transaksi) FROM transaksi")->fetchColumn();
            $idTransaksiTerakhir = $idTransaksiTerakhir !== null ? (int) $idTransaksiTerakhir : null;

            $stmt = $this->db->prepare("
                INSERT INTO rekonsiliasi
                    (id_transaksi, status, tanggal, modal_awal, total_tunai, total_qris, kas_fisik, selisih, catatan)
                VALUES (?, ?, {$this->nowExpression()}, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $idTransaksiTerakhir,
                $status,
                $ringkasan['modal_awal'],
                $ringkasan['total_tunai'],
                $ringkasan['total_qris'],
                $kas_fisik,
                $selisih,
                ($catatan !== '' ? $catatan : null),
            ]);

            return [
                'status'       => true,
                'id_rekonsiliasi' => (int) $this->db->lastInsertId(),
                'status_kas'   => $status,
                'selisih'      => $selisih,
                'kas_sistem'   => $kasSistem,
                'kas_fisik'    => $kas_fisik,
                'message'      => 'Shift kasir berhasil ditutup dan berita acara rekonsiliasi telah disimpan.',
            ];
        } catch (Exception $e) {
            return ['status' => false, 'message' => 'Gagal menyimpan rekonsiliasi: ' . $e->getMessage()];
        }
    }

    // Riwayat tutup kasir (berita acara rekonsiliasi) terbaru lebih dulu
    public function get_riwayat_rekonsiliasi($limit = 25) {
        $limit = max(1, (int) $limit);
        $stmt = $this->db->query("
            SELECT *
            FROM rekonsiliasi
            ORDER BY tanggal DESC, id_rekonsiliasi DESC
            LIMIT {$limit}
        ");
        return $stmt->fetchAll();
    }
}
