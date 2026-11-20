<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../models/AkunModel.php';

/**
 * Kontroler: AkunController
 * Menangani aksi tambah/ubah/hapus akun serta menyiapkan data untuk view.
 */
final class AkunController
{
    private AkunModel $model;
    private string $baseUrl;

    public function __construct(PDO $db, string $baseUrl = 'kelola_akun.php')
    {
        $this->model = new AkunModel($db);
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
            $roles = AkunModel::petaRole();

            if ($action === 'tambah' || $action === 'ubah') {
                $idAkun = 0;
                if ($action === 'ubah') {
                    $idAkun = filter_var($_POST['id_akun'] ?? null, FILTER_VALIDATE_INT);
                    if ($idAkun === false || $idAkun <= 0) {
                        throw new InvalidArgumentException('ID akun yang akan diubah tidak valid.');
                    }

                    $akunLama = $this->model->cari($idAkun);
                    if ($akunLama === false) {
                        throw new InvalidArgumentException('Akun yang akan diubah tidak ditemukan.');
                    }
                }

                $data = AkunModel::validasi($_POST, $action === 'tambah');

                $currentUserId = (int) (auth_user()['id_akun'] ?? 0);
                if ($action === 'ubah' && $idAkun === $currentUserId && (int) $data['status_aktif'] !== 1) {
                    throw new InvalidArgumentException('Anda tidak dapat menonaktifkan akun yang sedang digunakan.');
                }

                // Email harus unik, kecuali untuk akun yang sedang diubah.
                if ($this->model->emailDipakai($data['email'], $idAkun)) {
                    throw new InvalidArgumentException('Email akun sudah terdaftar dalam sistem.');
                }

                $hakAkses = $roles[$data['role']]['hak'];

                if ($action === 'tambah') {
                    $this->model->tambah($data, $hakAkses);

                    redirectWithFlash(
                        'success',
                        "Akun '{$data['nama_lengkap']}' ({$data['role']}) berhasil ditambahkan.",
                        $this->baseUrl
                    );
                }

                $this->model->ubah($idAkun, $data, $hakAkses);

                if ($idAkun === $currentUserId) {
                    $_SESSION['auth_user']['email']        = $data['email'];
                    $_SESSION['auth_user']['nama_lengkap'] = $data['nama_lengkap'];
                    $_SESSION['auth_user']['role']         = $data['role'];
                    $_SESSION['auth_user']['hak_akses']    = $hakAkses;
                }

                redirectWithFlash('success', "Akun '{$data['nama_lengkap']}' berhasil diperbarui.", $this->baseUrl);
            }

            if ($action === 'hapus') {
                $idAkun = filter_var($_POST['id_akun'] ?? null, FILTER_VALIDATE_INT);
                if ($idAkun === false || $idAkun <= 0) {
                    throw new InvalidArgumentException('ID akun yang akan dihapus tidak valid.');
                }

                $akun = $this->model->cari($idAkun);
                if ($akun === false) {
                    throw new InvalidArgumentException('Akun yang akan dihapus tidak ditemukan.');
                }

                if ($idAkun === (int) (auth_user()['id_akun'] ?? 0)) {
                    throw new InvalidArgumentException('Anda tidak dapat menghapus akun yang sedang digunakan.');
                }

                if ($akun['role'] === 'Pemilik Toko' && $this->model->jumlahOwner() <= 1) {
                    throw new InvalidArgumentException('Akun Pemilik Toko terakhir tidak boleh dihapus.');
                }

                if ($this->model->punyaTransaksi($idAkun)) {
                    throw new InvalidArgumentException(
                        'Akun tidak bisa dihapus karena memiliki riwayat transaksi. Nonaktifkan akun tersebut sebagai gantinya.'
                    );
                }

                $this->model->hapus($idAkun);

                redirectWithFlash(
                    'success',
                    "Akun '" . namaTampilAkun($akun) . "' berhasil dihapus.",
                    $this->baseUrl
                );
            }

            throw new InvalidArgumentException('Aksi yang diminta tidak dikenali.');
        } catch (InvalidArgumentException $e) {
            redirectWithFlash('danger', $e->getMessage(), $this->baseUrl);
        } catch (Throwable $e) {
            error_log('[kelola_akun] ' . $e->getMessage());
            redirectWithFlash('danger', 'Gagal memproses data akun: ' . $e->getMessage(), $this->baseUrl);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function index(): array
    {
        $flash = ambilFlash();
        $roles = AkunModel::petaRole();
        $akunList = $this->model->semua();

        $roleCount = ['owner' => 0, 'kasir' => 0, 'stok' => 0, 'lain' => 0];
        $statusCount = ['aktif' => 0, 'nonaktif' => 0];
        $akunData = [];

        foreach ($akunList as $akun) {
            $filterRole = $roles[$akun['role']]['filter'] ?? 'lain';
            $roleCount[$filterRole]++;
            $statusCount[(int) $akun['status_aktif'] === 1 ? 'aktif' : 'nonaktif']++;

            $nama = namaTampilAkun($akun);
            $akunData[(int) $akun['id_akun']] = [
                'id_akun' => (int) $akun['id_akun'],
                'nama_lengkap' => $nama,
                'email' => (string) $akun['email'],
                'role' => (string) $akun['role'],
                'status_aktif' => (int) $akun['status_aktif'],
                'inisial' => inisialNama($nama),
            ];
        }

        $totalAkun = count($akunList);
        $lastOwnerId = null;
        if ($roleCount['owner'] === 1) {
            foreach ($akunList as $akun) {
                if ($akun['role'] === 'Pemilik Toko') {
                    $lastOwnerId = (int) $akun['id_akun'];
                    break;
                }
            }
        }

        return [
            'flash' => $flash,
            'roles' => $roles,
            'akunList' => $akunList,
            'roleCount' => $roleCount,
            'statusCount' => $statusCount,
            'akunData' => $akunData,
            'totalAkun' => $totalAkun,
            'lastOwnerId' => $lastOwnerId,
            'jsonFlags' => JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE,
            'activePage' => 'kelola_akun',
            'pageTitle' => 'Kelola Akun & Hak Akses Pengguna',
            'moduleBreadcrumb' => 'USER & SETTINGS / HAK AKSES PENGGUNA',
            'userName' => 'Ferdyansah',
            'userRole' => 'Pemilik Toko (Owner)',
            'userInitials' => 'FF',
        ];
    }
}
