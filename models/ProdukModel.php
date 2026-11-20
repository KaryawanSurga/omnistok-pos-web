<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';

/**
 * Model Produk
 * Menangani akses data dan aturan bisnis produk, stok, batch,
 * mutasi stok, dan riwayat harga.
 */
final class ProdukModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return list<array<string, mixed>> */
    public function semuaDenganStok(): array
    {
        return $this->db->query(
            'SELECT p.*,
                    (SELECT COALESCE(SUM(s.jumlah_stok), 0) FROM stok s WHERE s.id_produk = p.id_produk) AS jumlah_stok,
                    (SELECT COALESCE(MAX(s.batas_minimum), 5) FROM stok s WHERE s.id_produk = p.id_produk) AS batas_minimum
             FROM produk p
             ORDER BY p.id_produk ASC'
        )->fetchAll();
    }

    /** @return array<string, mixed>|false */
    public function cari(int $idProduk): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM produk WHERE id_produk = ?');
        $stmt->execute([$idProduk]);

        return $stmt->fetch();
    }

    public function stok(int $idProduk): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(jumlah_stok), 0) FROM stok WHERE id_produk = ?');
        $stmt->execute([$idProduk]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<string>
     */
    public function referensi(int $idProduk): array
    {
        $tabel = [
            'detail_penjualan' => 'transaksi penjualan',
            'detail_pesanan' => 'pesanan pembelian',
            'detail_penerimaan' => 'penerimaan barang',
            'detail_retur' => 'retur barang',
            'diskon' => 'promo diskon',
        ];

        $dipakai = [];
        foreach ($tabel as $namaTabel => $label) {
            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$namaTabel} WHERE id_produk = ?");
                $stmt->execute([$idProduk]);
                if ((int) $stmt->fetchColumn() > 0) {
                    $dipakai[] = $label;
                }
            } catch (Throwable $e) {
                // Tabel tidak ada pada instalasi lain: lewati.
            }
        }

        return $dipakai;
    }

    /**
     * Pastikan produk memiliki batch dan baris stok. Mengembalikan id baris stok utama.
     *
     * @return array{batch: int, stok: int}
     */
    private function pastikanBatchDanStok(int $idProduk): array
    {
        $stmt = $this->db->prepare('SELECT id_stok, id_batch FROM stok WHERE id_produk = ? ORDER BY id_stok ASC LIMIT 1');
        $stmt->execute([$idProduk]);
        $stokRow = $stmt->fetch();

        if ($stokRow !== false) {
            return ['batch' => (int) $stokRow['id_batch'], 'stok' => (int) $stokRow['id_stok']];
        }

        $stmt = $this->db->prepare('INSERT INTO batch_produk (id_produk, tanggal_kadaluwarsa) VALUES (?, ?)');
        $stmt->execute([$idProduk, date('Y-m-d', strtotime('+1 year'))]);
        $idBatch = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            'INSERT INTO stok (id_produk, id_batch, lokasi_stok, jumlah_stok, batas_minimum)
             VALUES (?, ?, ?, 0, 5)'
        );
        $stmt->execute([$idProduk, $idBatch, 'Gudang Utama']);

        return ['batch' => $idBatch, 'stok' => (int) $this->db->lastInsertId()];
    }

    /**
     * @param array{nama_produk: string, kategori: string, deskripsi: string|null,
     *              harga_modal: float, harga_jual: float, stok: int, batas_minimum: int} $data
     */
    public function tambah(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO produk (nama_produk, kategori, deskripsi, harga_modal, harga_jual)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['nama_produk'],
                $data['kategori'],
                $data['deskripsi'],
                $data['harga_modal'],
                $data['harga_jual'],
            ]);
            $newId = (int) $this->db->lastInsertId();

            $stmt = $this->db->prepare('INSERT INTO batch_produk (id_produk, tanggal_kadaluwarsa) VALUES (?, ?)');
            $stmt->execute([$newId, date('Y-m-d', strtotime('+1 year'))]);
            $idBatch = (int) $this->db->lastInsertId();

            $stmt = $this->db->prepare(
                'INSERT INTO stok (id_produk, id_batch, lokasi_stok, jumlah_stok, batas_minimum)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$newId, $idBatch, 'Gudang Utama', $data['stok'], $data['batas_minimum']]);

            $stmt = $this->db->prepare(
                'INSERT INTO mutasi_stok (id_produk, jenis_mutasi, jumlah, alasan, tanggal_mutasi)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$newId, 'Masuk', $data['stok'], 'Saldo awal pendaftaran master produk', $now]);

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $newId;
    }

    /**
     * @param array<string, mixed> $produkLama
     * @param array{nama_produk: string, kategori: string, deskripsi: string|null,
     *              harga_modal: float, harga_jual: float, stok: int, batas_minimum: int} $data
     */
    public function ubah(int $idProduk, array $produkLama, array $data): void
    {
        $stokSekarang = $this->stok($idProduk);
        $selisihStok = $data['stok'] - $stokSekarang;
        $hargaBerubah = (float) $produkLama['harga_modal'] !== $data['harga_modal']
            || (float) $produkLama['harga_jual'] !== $data['harga_jual'];

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'UPDATE produk SET nama_produk = ?, kategori = ?, deskripsi = ?, harga_modal = ?, harga_jual = ?
                 WHERE id_produk = ?'
            );
            $stmt->execute([
                $data['nama_produk'],
                $data['kategori'],
                $data['deskripsi'],
                $data['harga_modal'],
                $data['harga_jual'],
                $idProduk,
            ]);

            if ($hargaBerubah) {
                $stmt = $this->db->prepare(
                    'INSERT INTO riwayat_harga (id_produk, harga_jual_lama, harga_modal_lama, tanggal_perubahan)
                     VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([
                    $idProduk,
                    $produkLama['harga_jual'],
                    $produkLama['harga_modal'],
                    date('Y-m-d'),
                ]);
            }

            $referensiStok = $this->pastikanBatchDanStok($idProduk);

            if ($selisihStok !== 0) {
                // Nolkan seluruh baris stok agar total tepat sesuai input,
                // lalu isi baris utama dengan nilai baru.
                $this->db->prepare('UPDATE stok SET jumlah_stok = 0 WHERE id_produk = ?')->execute([$idProduk]);
                $this->db->prepare('UPDATE stok SET jumlah_stok = ?, batas_minimum = ? WHERE id_stok = ?')
                    ->execute([$data['stok'], $data['batas_minimum'], $referensiStok['stok']]);

                $stmt = $this->db->prepare(
                    'INSERT INTO mutasi_stok (id_produk, jenis_mutasi, jumlah, alasan, tanggal_mutasi)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $idProduk,
                    $selisihStok > 0 ? 'Masuk' : 'Keluar',
                    abs($selisihStok),
                    'Penyesuaian stok dari form ubah produk',
                    date('Y-m-d H:i:s'),
                ]);
            } else {
                $this->db->prepare('UPDATE stok SET batas_minimum = ? WHERE id_produk = ?')
                    ->execute([$data['batas_minimum'], $idProduk]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Hapus permanen bila tidak dipakai, atau arsipkan bila masih direferensikan.
     *
     * @return array{mode: string, nama: string, referensi: list<string>}
     */
    public function hapus(int $idProduk): array
    {
        $produk = $this->cari($idProduk);
        if ($produk === false) {
            throw new InvalidArgumentException('Produk yang akan dihapus tidak ditemukan.');
        }
        $namaProduk = (string) $produk['nama_produk'];

        $referensi = $this->referensi($idProduk);
        if ($referensi !== []) {
            if ((int) $produk['status_aktif'] === 0) {
                throw new InvalidArgumentException(
                    "Produk '{$namaProduk}' sudah berstatus nonaktif (diarsipkan)."
                );
            }

            // Produk masih dipakai riwayat transaksi: jangan hapus permanen
            // agar laporan lama tetap utuh. Cukup arsipkan dari katalog aktif.
            $this->db->beginTransaction();
            try {
                $this->db->prepare('UPDATE produk SET status_aktif = 0 WHERE id_produk = ?')
                    ->execute([$idProduk]);
                $this->db->prepare(
                    'UPDATE diskon SET status_aktif = 0, updated_at = ?
                     WHERE id_produk = ? AND status_aktif = 1'
                )->execute([date('Y-m-d H:i:s'), $idProduk]);
                $this->db->commit();
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $e;
            }

            return ['mode' => 'arsip', 'nama' => $namaProduk, 'referensi' => $referensi];
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM stok WHERE id_produk = ?')->execute([$idProduk]);
            $this->db->prepare('DELETE FROM batch_produk WHERE id_produk = ?')->execute([$idProduk]);
            $this->db->prepare('DELETE FROM mutasi_stok WHERE id_produk = ?')->execute([$idProduk]);
            $this->db->prepare('DELETE FROM riwayat_harga WHERE id_produk = ?')->execute([$idProduk]);
            $this->db->prepare('DELETE FROM produk WHERE id_produk = ?')->execute([$idProduk]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return ['mode' => 'hapus', 'nama' => $namaProduk, 'referensi' => []];
    }

    public function aktifkan(int $idProduk): string
    {
        $produk = $this->cari($idProduk);
        if ($produk === false) {
            throw new InvalidArgumentException('Produk yang akan diaktifkan tidak ditemukan.');
        }
        $namaProduk = (string) $produk['nama_produk'];

        $this->db->prepare('UPDATE produk SET status_aktif = 1 WHERE id_produk = ?')->execute([$idProduk]);

        return $namaProduk;
    }

    public function updateHarga(int $idProduk, float $hargaModal, float $hargaJual, string $tanggal): void
    {
        $produk = $this->cari($idProduk);
        if ($produk === false) {
            throw new InvalidArgumentException('Produk tidak ditemukan.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO riwayat_harga (id_produk, harga_jual_lama, harga_modal_lama, tanggal_perubahan)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$idProduk, $produk['harga_jual'], $produk['harga_modal'], $tanggal]);

            $stmt = $this->db->prepare('UPDATE produk SET harga_modal = ?, harga_jual = ? WHERE id_produk = ?');
            $stmt->execute([round($hargaModal, 2), round($hargaJual, 2), $idProduk]);

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public function riwayatHarga(int $idProduk): array
    {
        $stmt = $this->db->prepare(
            'SELECT rh.*, p.nama_produk
             FROM riwayat_harga rh
             JOIN produk p ON rh.id_produk = p.id_produk
             WHERE rh.id_produk = ?
             ORDER BY rh.tanggal_perubahan DESC, rh.id_riwayat_harga DESC'
        );
        $stmt->execute([$idProduk]);

        return $stmt->fetchAll();
    }

    /**
     * @return array{nama_produk: string, kategori: string, deskripsi: string|null,
     *               harga_modal: float, harga_jual: float, stok: int, batas_minimum: int}
     */
    public function validasi(array $input): array
    {
        $errors = [];

        $nama = trim((string) ($input['nama_produk'] ?? ''));
        $panjangNama = function_exists('mb_strlen') ? mb_strlen($nama) : strlen($nama);
        if ($nama === '') {
            $errors[] = 'Nama produk wajib diisi.';
        } elseif ($panjangNama < 3) {
            $errors[] = 'Nama produk minimal 3 karakter.';
        } elseif ($panjangNama > 150) {
            $errors[] = 'Nama produk maksimal 150 karakter.';
        }

        $kategori = trim((string) ($input['kategori'] ?? ''));
        $panjangKategori = function_exists('mb_strlen') ? mb_strlen($kategori) : strlen($kategori);
        if ($kategori === '') {
            $errors[] = 'Kategori produk wajib diisi.';
        } elseif ($panjangKategori > 50) {
            $errors[] = 'Kategori produk maksimal 50 karakter.';
        }

        $deskripsi = trim((string) ($input['deskripsi'] ?? ''));
        if (function_exists('mb_strlen') ? mb_strlen($deskripsi) > 1000 : strlen($deskripsi) > 1000) {
            $errors[] = 'Deskripsi produk maksimal 1000 karakter.';
        }

        $modalRaw = trim((string) ($input['harga_modal'] ?? ''));
        $jualRaw = trim((string) ($input['harga_jual'] ?? ''));
        $modal = filter_var($modalRaw, FILTER_VALIDATE_FLOAT);
        $jual = filter_var($jualRaw, FILTER_VALIDATE_FLOAT);

        if ($modalRaw === '' || $modal === false || $modal <= 0) {
            $errors[] = 'Harga modal harus berupa angka lebih dari 0.';
        }
        if ($jualRaw === '' || $jual === false || $jual <= 0) {
            $errors[] = 'Harga jual harus berupa angka lebih dari 0.';
        }
        if ($modal !== false && $modal > 99999999.99) {
            $errors[] = 'Harga modal terlalu besar.';
        }
        if ($jual !== false && $jual > 99999999.99) {
            $errors[] = 'Harga jual terlalu besar.';
        }
        if ($modal !== false && $jual !== false && $modal > 0 && $jual > 0 && $jual < $modal) {
            $errors[] = 'Harga jual tidak boleh lebih rendah dari harga modal.';
        }

        $stokRaw = trim((string) ($input['stok'] ?? '0'));
        $stok = filter_var($stokRaw, FILTER_VALIDATE_INT);
        if ($stokRaw === '' || $stok === false || $stok < 0) {
            $errors[] = 'Stok fisik harus berupa bilangan bulat 0 atau lebih.';
        } elseif ($stok > 1000000) {
            $errors[] = 'Stok fisik terlalu besar.';
        }

        $batasRaw = trim((string) ($input['batas_minimum'] ?? '5'));
        $batas = filter_var($batasRaw, FILTER_VALIDATE_INT);
        if ($batasRaw === '' || $batas === false || $batas < 0) {
            $errors[] = 'Batas minimum stok harus berupa bilangan bulat 0 atau lebih.';
        } elseif ($batas > 1000000) {
            $errors[] = 'Batas minimum stok terlalu besar.';
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        return [
            'nama_produk' => $nama,
            'kategori' => $kategori,
            'deskripsi' => $deskripsi !== '' ? $deskripsi : null,
            'harga_modal' => round((float) $modal, 2),
            'harga_jual' => round((float) $jual, 2),
            'stok' => (int) $stok,
            'batas_minimum' => (int) $batas,
        ];
    }

    /**
     * @return array{harga_modal: float, harga_jual: float, tanggal: string}
     */
    public function validasiHarga(array $input): array
    {
        $modalRaw = trim((string) ($input['harga_modal_baru'] ?? ''));
        $jualRaw = trim((string) ($input['harga_jual_baru'] ?? ''));
        $modal = filter_var($modalRaw, FILTER_VALIDATE_FLOAT);
        $jual = filter_var($jualRaw, FILTER_VALIDATE_FLOAT);
        $tanggal = trim((string) ($input['tgl_efektif'] ?? ''));

        $errors = [];
        if ($modalRaw === '' || $modal === false || $modal <= 0) {
            $errors[] = 'Harga modal baru harus berupa angka lebih dari 0.';
        }
        if ($jualRaw === '' || $jual === false || $jual <= 0) {
            $errors[] = 'Harga jual baru harus berupa angka lebih dari 0.';
        }
        if ($modal !== false && $jual !== false && $modal > 0 && $jual > 0 && $jual < $modal) {
            $errors[] = 'Harga jual tidak boleh lebih rendah dari harga modal.';
        }
        if (!tanggalValid($tanggal)) {
            $errors[] = 'Tanggal efektif tidak valid.';
        }
        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        return [
            'harga_modal' => (float) $modal,
            'harga_jual' => (float) $jual,
            'tanggal' => $tanggal,
        ];
    }
}
