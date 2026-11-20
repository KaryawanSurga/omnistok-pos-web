<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../models/ProdukModel.php';

/**
 * Kontroler: ProdukController
 * Menangani aksi tambah/ubah/hapus/aktifkan produk dan pembaruan harga,
 * serta menyiapkan data untuk view katalog dan view harga.
 */
final class ProdukController
{
    private ProdukModel $model;

    public function __construct(PDO $db)
    {
        $this->model = new ProdukModel($db);
    }

    public function handlePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        try {
            verifyCsrf();

            $action = (string) ($_POST['action'] ?? '');

            if ($action === 'tambah_produk') {
                $data = $this->model->validasi($_POST);
                $newId = $this->model->tambah($data);

                redirectWithFlash(
                    'success',
                    "Produk '{$data['nama_produk']}' (" . kodeProduk($newId) . ') berhasil ditambahkan.',
                    'daftar_produk.php?tab=katalog'
                );
            }

            if ($action === 'ubah_produk') {
                $idProduk = filter_var($_POST['id_produk'] ?? null, FILTER_VALIDATE_INT);
                if ($idProduk === false || $idProduk <= 0) {
                    throw new InvalidArgumentException('ID produk yang akan diubah tidak valid.');
                }

                $produkLama = $this->model->cari($idProduk);
                if ($produkLama === false) {
                    throw new InvalidArgumentException('Produk yang akan diubah tidak ditemukan.');
                }

                $data = $this->model->validasi($_POST);
                $this->model->ubah($idProduk, $produkLama, $data);

                redirectWithFlash(
                    'success',
                    "Produk '{$data['nama_produk']}' berhasil diperbarui.",
                    'daftar_produk.php?tab=katalog'
                );
            }

            if ($action === 'hapus_produk') {
                $idProduk = filter_var($_POST['id_produk'] ?? null, FILTER_VALIDATE_INT);
                if ($idProduk === false || $idProduk <= 0) {
                    throw new InvalidArgumentException('ID produk yang akan dihapus tidak valid.');
                }

                $hasil = $this->model->hapus($idProduk);

                if ($hasil['mode'] === 'arsip') {
                    redirectWithFlash(
                        'warning',
                        "Produk '{$hasil['nama']}' (" . kodeProduk($idProduk) . ') tidak dihapus permanen karena masih dipakai pada: ' .
                        implode(', ', $hasil['referensi']) . '. Produk dinonaktifkan (diarsipkan) dan tidak lagi tampil di POS/katalog aktif.',
                        'daftar_produk.php?tab=katalog'
                    );
                }

                redirectWithFlash(
                    'success',
                    "Produk '{$hasil['nama']}' (" . kodeProduk($idProduk) . ') berhasil dihapus.',
                    'daftar_produk.php?tab=katalog'
                );
            }

            if ($action === 'aktifkan_produk') {
                $idProduk = filter_var($_POST['id_produk'] ?? null, FILTER_VALIDATE_INT);
                if ($idProduk === false || $idProduk <= 0) {
                    throw new InvalidArgumentException('ID produk yang akan diaktifkan tidak valid.');
                }

                $namaProduk = $this->model->aktifkan($idProduk);

                redirectWithFlash(
                    'success',
                    "Produk '{$namaProduk}' (" . kodeProduk($idProduk) . ') berhasil diaktifkan kembali dan tampil lagi di POS/katalog.',
                    'daftar_produk.php?tab=katalog'
                );
            }

            if ($action === 'update_harga') {
                $idProduk = filter_var($_POST['id_produk'] ?? null, FILTER_VALIDATE_INT);
                if ($idProduk === false || $idProduk <= 0) {
                    throw new InvalidArgumentException('Produk yang akan diperbarui tidak valid.');
                }

                $data = $this->model->validasiHarga($_POST);
                $this->model->updateHarga($idProduk, $data['harga_modal'], $data['harga_jual'], $data['tanggal']);

                redirectWithFlash(
                    'success',
                    'Struktur harga ' . kodeProduk($idProduk) . ' berhasil diperbarui dan dicatat dalam audit trail.',
                    'daftar_produk.php?tab=harga&id=' . $idProduk
                );
            }

            throw new InvalidArgumentException('Aksi yang diminta tidak dikenali.');
        } catch (InvalidArgumentException $e) {
            $target = 'daftar_produk.php?tab=katalog';
            if (($_POST['action'] ?? '') === 'update_harga') {
                $id = filter_var($_POST['id_produk'] ?? 0, FILTER_VALIDATE_INT);
                $target = 'daftar_produk.php?tab=harga' . ($id !== false && $id > 0 ? '&id=' . $id : '');
            }
            redirectWithFlash('danger', $e->getMessage(), $target);
        } catch (Throwable $e) {
            error_log('[daftar_produk] ' . $e->getMessage());
            redirectWithFlash('danger', 'Gagal memproses data produk: ' . $e->getMessage(), 'daftar_produk.php?tab=katalog');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function index(): array
    {
        $flash = ambilFlash();

        $produkList = $this->model->semuaDenganStok();

        $kategoriList = [];
        $stokMenipis = 0;
        $nilaiPersediaan = 0.0;
        $totalMargin = 0.0;
        $produkMargin = 0;
        $marginRendah = 0;
        $totalArsip = 0;
        $produkData = [];

        foreach ($produkList as &$produk) {
            $produk['kode_tampil'] = kodeProduk((int) $produk['id_produk']);
            $produk['nama_produk'] = (string) $produk['nama_produk'];
            $produk['kategori'] = trim((string) ($produk['kategori'] ?? '')) !== '' ? (string) $produk['kategori'] : 'Umum';
            $produk['deskripsi'] = trim((string) ($produk['deskripsi'] ?? ''));
            $produk['jumlah_stok'] = (int) $produk['jumlah_stok'];
            $produk['batas_minimum'] = (int) $produk['batas_minimum'];
            $produk['margin'] = hitungMargin((float) $produk['harga_jual'], (float) $produk['harga_modal']);
            $produk['stok_menipis'] = $produk['jumlah_stok'] <= $produk['batas_minimum'];
            $produk['aktif'] = ((int) ($produk['status_aktif'] ?? 1) === 1);

            if (!$produk['aktif']) {
                $totalArsip++;
            } else {
                $kategoriList[$produk['kategori']] = ($kategoriList[$produk['kategori']] ?? 0) + 1;
                if ($produk['stok_menipis']) {
                    $stokMenipis++;
                }
                $nilaiPersediaan += $produk['jumlah_stok'] * (float) $produk['harga_modal'];
                $totalMargin += $produk['margin'];
                $produkMargin++;
                if ($produk['margin'] < 8) {
                    $marginRendah++;
                }
            }

            $produkData[(int) $produk['id_produk']] = [
                'id_produk' => (int) $produk['id_produk'],
                'kode' => $produk['kode_tampil'],
                'nama_produk' => $produk['nama_produk'],
                'kategori' => $produk['kategori'],
                'deskripsi' => $produk['deskripsi'],
                'harga_modal' => (float) $produk['harga_modal'],
                'harga_jual' => (float) $produk['harga_jual'],
                'jumlah_stok' => $produk['jumlah_stok'],
                'batas_minimum' => $produk['batas_minimum'],
            ];
        }
        unset($produk);
        ksort($kategoriList);

        $totalProduk = count($produkList) - $totalArsip;
        $rataMargin = $produkMargin > 0 ? $totalMargin / $produkMargin : 0.0;

        $activeTab = (string) ($_GET['tab'] ?? 'katalog');
        if (!in_array($activeTab, ['katalog', 'harga'], true)) {
            $activeTab = 'katalog';
        }

        $selectedProdId = isset($_GET['id']) ? (int) $_GET['id'] : ($produkList !== [] ? (int) $produkList[0]['id_produk'] : 0);
        $selectedProd = null;
        if ($selectedProdId > 0) {
            foreach ($produkList as $produk) {
                if ((int) $produk['id_produk'] === $selectedProdId) {
                    $selectedProd = $produk;
                    break;
                }
            }
        }
        if ($selectedProd === null && $produkList !== []) {
            $selectedProd = $produkList[0];
        }
        $selectedProdId = $selectedProd !== null ? (int) $selectedProd['id_produk'] : 0;

        $currMargin = $selectedProd !== null
            ? hitungMargin((float) $selectedProd['harga_jual'], (float) $selectedProd['harga_modal'])
            : 0.0;

        $riwayat = $selectedProd !== null ? $this->model->riwayatHarga($selectedProdId) : [];

        return [
            'flash' => $flash,
            'produkList' => $produkList,
            'kategoriList' => $kategoriList,
            'stokMenipis' => $stokMenipis,
            'nilaiPersediaan' => $nilaiPersediaan,
            'totalMargin' => $totalMargin,
            'produkMargin' => $produkMargin,
            'marginRendah' => $marginRendah,
            'totalArsip' => $totalArsip,
            'produkData' => $produkData,
            'totalProduk' => $totalProduk,
            'rataMargin' => $rataMargin,
            'activeTab' => $activeTab,
            'selectedProdId' => $selectedProdId,
            'selectedProd' => $selectedProd,
            'currMargin' => $currMargin,
            'riwayat' => $riwayat,
            'jsonFlags' => JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE,
            'activePage' => 'daftar_produk',
            'pageTitle' => ($activeTab === 'katalog') ? 'Daftar Produk & Master Data' : 'Pembaruan Harga Modal & Jual',
            'moduleBreadcrumb' => ($activeTab === 'katalog') ? 'PRODUK & KATALOG / MASTER DATA' : 'PRODUCTS / KELOLA HARGA PRODUK & MARGIN',
            'userName' => 'Zainal Maarif',
            'userRole' => 'Petugas Stok Gudang',
            'userInitials' => 'ZM',
        ];
    }
}
