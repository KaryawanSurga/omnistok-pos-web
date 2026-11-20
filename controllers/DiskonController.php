<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../models/DiskonModel.php';

/**
 * Kontroler: DiskonController
 * Menangani aksi tambah/ubah/hapus/toggle promo serta menyiapkan data view.
 */
final class DiskonController
{
    private DiskonModel $model;
    private string $baseUrl;

    public function __construct(PDO $db, string $baseUrl = 'kelola_diskon.php')
    {
        $this->model = new DiskonModel($db);
        $this->baseUrl = $baseUrl;
    }

    public function handlePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        try {
            verifyCsrf();

            $action = (string) ($_POST['action'] ?? '');

            if ($action === 'tambah_diskon') {
                $data = $this->model->validasi($_POST);
                $hasil = $this->model->tambah($data);

                redirectWithFlash(
                    'success',
                    "Promo '{$data['nama_diskon']}' berhasil ditambahkan dengan kode {$hasil['kode']}.",
                    $this->baseUrl
                );
            }

            if ($action === 'ubah_diskon') {
                $idDiskon = filter_var($_POST['id_diskon'] ?? null, FILTER_VALIDATE_INT);
                if ($idDiskon === false || $idDiskon <= 0) {
                    throw new InvalidArgumentException('ID promo yang akan diubah tidak valid.');
                }

                if ($this->model->cari($idDiskon) === false) {
                    throw new InvalidArgumentException('Promo yang akan diubah tidak ditemukan.');
                }

                $data = $this->model->validasi($_POST, false);
                $this->model->ubah($idDiskon, $data);

                redirectWithFlash('success', "Promo '{$data['nama_diskon']}' berhasil diperbarui.", $this->baseUrl);
            }

            if ($action === 'hapus_diskon') {
                $idDiskon = filter_var($_POST['id_diskon'] ?? null, FILTER_VALIDATE_INT);
                if ($idDiskon === false || $idDiskon <= 0) {
                    throw new InvalidArgumentException('ID promo yang akan dihapus tidak valid.');
                }

                $existing = $this->model->cari($idDiskon);
                if ($existing === false) {
                    throw new InvalidArgumentException('Promo yang akan dihapus tidak ditemukan.');
                }

                $this->model->hapus($idDiskon);

                redirectWithFlash('success', "Promo '{$existing['nama_diskon']}' berhasil dihapus.", $this->baseUrl);
            }

            if ($action === 'toggle_diskon') {
                $idDiskon = filter_var($_POST['id_diskon'] ?? null, FILTER_VALIDATE_INT);
                if ($idDiskon === false || $idDiskon <= 0) {
                    throw new InvalidArgumentException('ID promo tidak valid.');
                }

                $statusAktif = ((string) ($_POST['status_aktif'] ?? '0')) === '1' ? 1 : 0;

                $existing = $this->model->cari($idDiskon);
                if ($existing === false) {
                    throw new InvalidArgumentException('Promo tidak ditemukan.');
                }

                $this->model->toggle($idDiskon, $statusAktif);
                $keterangan = $statusAktif === 1 ? 'diaktifkan' : 'dinonaktifkan';

                redirectWithFlash(
                    'success',
                    "Promo '{$existing['nama_diskon']}' berhasil {$keterangan}.",
                    $this->baseUrl
                );
            }

            throw new InvalidArgumentException('Aksi yang diminta tidak dikenali.');
        } catch (InvalidArgumentException $e) {
            redirectWithFlash('danger', $e->getMessage(), $this->baseUrl);
        } catch (Throwable $e) {
            error_log('[kelola_diskon] ' . $e->getMessage());
            redirectWithFlash('danger', 'Gagal memproses data: ' . $e->getMessage(), $this->baseUrl);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function index(): array
    {
        $flash = ambilFlash();

        $produkList = $this->model->semuaProduk();

        $produkById = [];
        $produkPerKategori = [];
        $hargaProduk = [];
        foreach ($produkList as $produk) {
            $idProduk = (int) $produk['id_produk'];
            $kodeProduk = trim((string) ($produk['kode_produk'] ?? ''));
            $kategoriProduk = trim((string) ($produk['kategori'] ?? ''));

            $produk['kode_tampil'] = $kodeProduk !== '' ? $kodeProduk : sprintf('PRD-%03d', $idProduk);
            $produk['kategori_tampil'] = $kategoriProduk !== '' ? $kategoriProduk : 'Umum';

            $produkById[$idProduk] = $produk;
            if ((int) ($produk['status_aktif'] ?? 1) === 1) {
                $produkPerKategori[$produk['kategori_tampil']][] = $produk;
            }
            $hargaProduk[$idProduk] = (float) $produk['harga_jual'];
        }
        ksort($produkPerKategori);

        // Kolom kode/kategori produk tidak wajib ada di database lama; nilainya
        // dilengkapi dari $produkById setelah query utama.
        $diskonList = $this->model->semuaDenganProduk();

        foreach ($diskonList as &$diskon) {
            $produk = $produkById[(int) $diskon['id_produk']] ?? null;
            $diskon['kode_produk'] = $produk['kode_tampil'] ?? sprintf('PRD-%03d', (int) $diskon['id_produk']);
            $diskon['kategori'] = $produk['kategori_tampil'] ?? 'Umum';
        }
        unset($diskon);

        $statusCount = ['all' => count($diskonList), 'active' => 0, 'scheduled' => 0, 'expired' => 0, 'inactive' => 0];
        foreach ($diskonList as $diskon) {
            $statusCount[promoStatus($diskon)['key']]++;
        }
        $riwayatCount = $statusCount['expired'] + $statusCount['inactive'];

        $statistik = $this->model->statistik();
        $potonganHariIni = $statistik['potongan'];
        $transaksiHariIni = $statistik['transaksi'];
        $transaksiPromoHariIni = $statistik['transaksiPromo'];

        $rasioPromo = $transaksiHariIni > 0 ? ($transaksiPromoHariIni / $transaksiHariIni) * 100 : 0.0;
        $rasioLabel = rtrim(rtrim(number_format($rasioPromo, 1, ',', '.'), '0'), ',');

        /* Data promo untuk modal edit */
        $promoData = [];
        foreach ($diskonList as $diskon) {
            $promoData[(int) $diskon['id_diskon']] = [
                'id_diskon' => (int) $diskon['id_diskon'],
                'kode_diskon' => (string) (($diskon['kode_diskon'] ?? '') ?: 'DSK-' . str_pad((string) $diskon['id_diskon'], 3, '0', STR_PAD_LEFT)),
                'id_produk' => (int) $diskon['id_produk'],
                'nama_diskon' => (string) $diskon['nama_diskon'],
                'presentase_diskon' => (float) $diskon['presentase_diskon'],
                'maks_diskon' => $diskon['maks_diskon'] === null ? '' : (float) $diskon['maks_diskon'],
                'kuota' => $diskon['kuota'] === null ? '' : (int) $diskon['kuota'],
                'tanggal_mulai' => (string) $diskon['tanggal_mulai'],
                'tanggal_selesai' => (string) $diskon['tanggal_selesai'],
                'status_aktif' => (int) $diskon['status_aktif'],
            ];
        }

        return [
            'flash' => $flash,
            'produkList' => $produkList,
            'produkById' => $produkById,
            'produkPerKategori' => $produkPerKategori,
            'hargaProduk' => $hargaProduk,
            'diskonList' => $diskonList,
            'statusCount' => $statusCount,
            'riwayatCount' => $riwayatCount,
            'promoAktifBerjalan' => $statistik['aktif'],
            'promoBaruMingguIni' => $statistik['baru'],
            'promoBerakhirMingguIni' => $statistik['berakhir'],
            'namaPromoBerakhir' => $statistik['namaBerakhir'],
            'potonganHariIni' => $potonganHariIni,
            'transaksiHariIni' => $transaksiHariIni,
            'transaksiPromoHariIni' => $transaksiPromoHariIni,
            'rasioPromo' => $rasioPromo,
            'rasioLabel' => $rasioLabel,
            'promoData' => $promoData,
            'jsonFlags' => JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE,
            'defaultMulai' => date('Y-m-d'),
            'defaultSelesai' => date('Y-m-d', strtotime('+7 days')),
            'activePage' => 'kelola_diskon',
            'pageTitle' => 'Kelola Diskon & Promo',
            'moduleBreadcrumb' => 'PRODUK / PROGRAM PROMOSI',
            'userName' => 'Justin Kelvin',
            'userRole' => 'Supervisor Toko (JK)',
            'userInitials' => 'JK',
        ];
    }
}
