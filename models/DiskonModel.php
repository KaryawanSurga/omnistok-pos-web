<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';

/**
 * Model Diskon
 * Menangani akses data dan aturan bisnis tabel `diskon`.
 */
final class DiskonModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return list<array<string, mixed>> */
    public function semuaProduk(): array
    {
        return $this->db->query('SELECT * FROM produk ORDER BY nama_produk ASC')->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function semuaDenganProduk(): array
    {
        return $this->db->query(
            'SELECT d.*, p.nama_produk, p.harga_jual
             FROM diskon d
             INNER JOIN produk p ON p.id_produk = d.id_produk
             ORDER BY d.status_aktif DESC, d.tanggal_mulai DESC, d.id_diskon DESC'
        )->fetchAll();
    }

    /** @return array<string, mixed>|false */
    public function cari(int $idDiskon): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM diskon WHERE id_diskon = ?');
        $stmt->execute([$idDiskon]);

        return $stmt->fetch();
    }

    /**
     * @return array{id: int, kode: string}
     */
    public function tambah(array $data): array
    {
        $now = date('Y-m-d H:i:s');

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO diskon
                    (id_produk, nama_diskon, presentase_diskon, maks_diskon, kuota,
                     terpakai, tanggal_mulai, tanggal_selesai, status_aktif, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['id_produk'],
                $data['nama_diskon'],
                $data['presentase_diskon'],
                $data['maks_diskon'],
                $data['kuota'],
                $data['tanggal_mulai'],
                $data['tanggal_selesai'],
                $data['status_aktif'],
                $now,
                $now,
            ]);

            $newId = (int) $this->db->lastInsertId();
            $kode = 'DSK-' . str_pad((string) $newId, 3, '0', STR_PAD_LEFT);
            $this->db->prepare('UPDATE diskon SET kode_diskon = ? WHERE id_diskon = ?')
                ->execute([$kode, $newId]);

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return ['id' => $newId, 'kode' => $kode];
    }

    public function ubah(int $idDiskon, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE diskon SET
                id_produk = ?,
                nama_diskon = ?,
                presentase_diskon = ?,
                maks_diskon = ?,
                kuota = ?,
                tanggal_mulai = ?,
                tanggal_selesai = ?,
                status_aktif = ?,
                updated_at = ?
             WHERE id_diskon = ?'
        );
        $stmt->execute([
            $data['id_produk'],
            $data['nama_diskon'],
            $data['presentase_diskon'],
            $data['maks_diskon'],
            $data['kuota'],
            $data['tanggal_mulai'],
            $data['tanggal_selesai'],
            $data['status_aktif'],
            date('Y-m-d H:i:s'),
            $idDiskon,
        ]);
    }

    public function hapus(int $idDiskon): void
    {
        $this->db->prepare('DELETE FROM diskon WHERE id_diskon = ?')->execute([$idDiskon]);
    }

    public function toggle(int $idDiskon, int $statusAktif): void
    {
        $this->db->prepare('UPDATE diskon SET status_aktif = ?, updated_at = ? WHERE id_diskon = ?')
            ->execute([$statusAktif, date('Y-m-d H:i:s'), $idDiskon]);
    }

    /**
     * Statistik ringkas halaman diskon.
     *
     * @return array{aktif: int, baru: int, berakhir: int, namaBerakhir: string|false,
     *               potongan: float, transaksi: int, transaksiPromo: int}
     */
    public function statistik(): array
    {
        $today = date('Y-m-d');
        $weekAhead = date('Y-m-d', strtotime('+7 days'));
        $weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM diskon
             WHERE status_aktif = 1 AND tanggal_mulai <= ? AND tanggal_selesai >= ?'
        );
        $stmt->execute([$today, $today]);
        $promoAktifBerjalan = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM diskon WHERE created_at >= ?');
        $stmt->execute([$weekAgo]);
        $promoBaruMingguIni = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM diskon
             WHERE status_aktif = 1 AND tanggal_selesai BETWEEN ? AND ?'
        );
        $stmt->execute([$today, $weekAhead]);
        $promoBerakhirMingguIni = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare(
            'SELECT nama_diskon FROM diskon
             WHERE status_aktif = 1 AND tanggal_selesai BETWEEN ? AND ?
             ORDER BY tanggal_selesai ASC, id_diskon ASC
             LIMIT 1'
        );
        $stmt->execute([$today, $weekAhead]);
        $namaPromoBerakhir = $stmt->fetchColumn();

        $potonganHariIni = 0.0;
        $transaksiHariIni = 0;
        $transaksiPromoHariIni = 0;

        // Statistik hanya dihitung bila tabel transaksi memang mencatat promo.
        $kolomTransaksi = $this->daftarKolom('transaksi');
        if (isset($kolomTransaksi['tanggal_transaksi'])) {
            try {
                $stmt = $this->db->prepare('SELECT COUNT(*) FROM transaksi WHERE DATE(tanggal_transaksi) = ?');
                $stmt->execute([$today]);
                $transaksiHariIni = (int) $stmt->fetchColumn();

                $punyaTotalDiskon = isset($kolomTransaksi['total_diskon']);
                $punyaIdDiskon = isset($kolomTransaksi['id_diskon']);

                if ($punyaTotalDiskon || $punyaIdDiskon) {
                    $sumPotongan = $punyaTotalDiskon ? 'COALESCE(SUM(total_diskon), 0)' : '0';
                    $sumPromo = $punyaIdDiskon
                        ? 'COALESCE(SUM(CASE WHEN id_diskon IS NOT NULL THEN 1 ELSE 0 END), 0)'
                        : 'COALESCE(SUM(CASE WHEN total_diskon > 0 THEN 1 ELSE 0 END), 0)';

                    $stmt = $this->db->prepare(
                        "SELECT {$sumPotongan} AS total_potongan, {$sumPromo} AS trx_promo
                         FROM transaksi
                         WHERE DATE(tanggal_transaksi) = ?"
                    );
                    $stmt->execute([$today]);
                    $statTransaksi = $stmt->fetch();

                    if (is_array($statTransaksi)) {
                        $potonganHariIni = (float) $statTransaksi['total_potongan'];
                        $transaksiPromoHariIni = (int) $statTransaksi['trx_promo'];
                    }
                }
            } catch (Throwable $e) {
                error_log('[diskon] Statistik transaksi tidak tersedia: ' . $e->getMessage());
            }
        }

        return [
            'aktif' => $promoAktifBerjalan,
            'baru' => $promoBaruMingguIni,
            'berakhir' => $promoBerakhirMingguIni,
            'namaBerakhir' => $namaPromoBerakhir,
            'potongan' => $potonganHariIni,
            'transaksi' => $transaksiHariIni,
            'transaksiPromo' => $transaksiPromoHariIni,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function daftarKolom(string $table): array
    {
        $columns = [];

        try {
            $driver = (string) $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                foreach ($this->db->query('PRAGMA table_info(' . $table . ')')->fetchAll() as $column) {
                    $columns[strtolower((string) $column['name'])] = true;
                }
            } else {
                foreach ($this->db->query('SHOW COLUMNS FROM ' . $table)->fetchAll() as $column) {
                    $columns[strtolower((string) $column['Field'])] = true;
                }
            }
        } catch (Throwable $e) {
            // Tabel belum ada atau tidak dapat dibaca: anggap tanpa kolom.
        }

        return $columns;
    }

    /**
     * Membaca sekaligus memvalidasi input form promo.
     *
     * @return array{id_produk: int, nama_diskon: string, presentase_diskon: float,
     *               maks_diskon: float|null, kuota: int|null, tanggal_mulai: string,
     *               tanggal_selesai: string, status_aktif: int}
     */
    public function validasi(array $input, bool $hanyaProdukAktif = true): array
    {
        $errors = [];

        $idProduk = filter_var($input['id_produk'] ?? null, FILTER_VALIDATE_INT);
        if ($idProduk === false || $idProduk <= 0) {
            $errors[] = 'Produk sasaran promo wajib dipilih.';
        }

        $nama = trim((string) ($input['nama_diskon'] ?? ''));
        $panjangNama = function_exists('mb_strlen') ? mb_strlen($nama) : strlen($nama);
        if ($nama === '') {
            $errors[] = 'Nama program promosi wajib diisi.';
        } elseif ($panjangNama < 3) {
            $errors[] = 'Nama program promosi minimal 3 karakter.';
        } elseif ($panjangNama > 50) {
            // Mengikuti lebar kolom nama_diskon VARCHAR(50) pada skema OmniStok.
            $errors[] = 'Nama program promosi maksimal 50 karakter.';
        }

        $persenRaw = trim((string) ($input['presentase_diskon'] ?? ''));
        $persen = filter_var($persenRaw, FILTER_VALIDATE_FLOAT);
        if ($persenRaw === '' || $persen === false || $persen <= 0 || $persen > 90) {
            $errors[] = 'Persentase diskon harus berupa angka lebih dari 0 dan maksimal 90%.';
        }

        $maks = null;
        $maksRaw = trim((string) ($input['maks_diskon'] ?? ''));
        if ($maksRaw !== '') {
            $maksValue = filter_var($maksRaw, FILTER_VALIDATE_FLOAT);
            if ($maksValue === false || $maksValue < 0) {
                $errors[] = 'Maksimal potongan harus berupa angka 0 atau lebih.';
            } elseif ($maksValue > 0) {
                $maks = round((float) $maksValue, 2);
            }
        }

        $kuota = null;
        $kuotaRaw = trim((string) ($input['kuota'] ?? ''));
        if ($kuotaRaw !== '') {
            $kuotaValue = filter_var($kuotaRaw, FILTER_VALIDATE_INT);
            if ($kuotaValue === false || $kuotaValue < 0) {
                $errors[] = 'Kuota pemakaian harus berupa bilangan bulat 0 atau lebih.';
            } elseif ($kuotaValue > 0) {
                $kuota = (int) $kuotaValue;
            }
        }

        $mulai = trim((string) ($input['tanggal_mulai'] ?? ''));
        $selesai = trim((string) ($input['tanggal_selesai'] ?? ''));

        if (!tanggalValid($mulai)) {
            $errors[] = 'Tanggal mulai tidak valid.';
        }
        if (!tanggalValid($selesai)) {
            $errors[] = 'Tanggal berakhir tidak valid.';
        }
        if (tanggalValid($mulai) && tanggalValid($selesai) && $selesai < $mulai) {
            $errors[] = 'Tanggal berakhir tidak boleh lebih awal dari tanggal mulai.';
        }

        if ($idProduk !== false && $idProduk > 0) {
            $sqlProduk = 'SELECT id_produk FROM produk WHERE id_produk = ?'
                . ($hanyaProdukAktif ? ' AND status_aktif = 1' : '');
            $stmt = $this->db->prepare($sqlProduk);
            $stmt->execute([$idProduk]);
            if ($stmt->fetchColumn() === false) {
                $errors[] = $hanyaProdukAktif
                    ? 'Produk yang dipilih tidak ditemukan atau sudah dinonaktifkan.'
                    : 'Produk yang dipilih tidak ditemukan.';
            }
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        return [
            'id_produk' => (int) $idProduk,
            'nama_diskon' => $nama,
            'presentase_diskon' => round((float) $persen, 2),
            'maks_diskon' => $maks,
            'kuota' => $kuota,
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $selesai,
            'status_aktif' => ((string) ($input['status_aktif'] ?? '0')) === '1' ? 1 : 0,
        ];
    }
}
