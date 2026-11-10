<?php
declare(strict_types=1);

/**
 * View Antarmuka: Kelola Data Akun & Hak Akses [UI-02]
 * Penulis: Justin Kelvin (JK - 255150407111098)
 * Referensi UI SDD: UI-02 (Mengelola Akun & Hak Akses), UI-03 (Menambah Akun), UI-04 (Mengubah Akun)
 *
 * Fitur:
 * - Tambah, ubah, dan hapus akun pengguna
 * - Validasi email unik, kata sandi, dan peran
 * - Proteksi CSRF + pola POST/Redirect/GET
 * - Pencarian dan filter peran/status
 * - Statistik dihitung dari database
 */

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/config/auth.php';
auth_require_owner();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';

/* --------------------------------------------------------------------------
 * Helper
 * ----------------------------------------------------------------------- */

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function redirectWithFlash(string $type, string $message): never
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    header('Location: kelola_akun.php');
    exit;
}

function verifyCsrf(): void
{
    $submitted = (string) ($_POST['csrf_token'] ?? '');
    $expected = (string) ($_SESSION['csrf_token'] ?? '');

    if ($submitted === '' || $expected === '' || !hash_equals($expected, $submitted)) {
        throw new InvalidArgumentException('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
    }
}

/** @return array<string, array{hak: string, class: string, filter: string}> */
function petaRole(): array
{
    return [
        'Pemilik Toko' => ['hak' => 'SUPER_ADMIN', 'class' => 'pill-pink', 'filter' => 'owner'],
        'Kasir' => ['hak' => 'KASIR_POS', 'class' => 'pill-blue', 'filter' => 'kasir'],
        'Petugas Stok' => ['hak' => 'PETUGAS_GUDANG', 'class' => 'pill-teal', 'filter' => 'stok'],
    ];
}

function namaTampil(array $akun): string
{
    $nama = trim((string) ($akun['nama_lengkap'] ?? ''));
    if ($nama !== '') {
        return $nama;
    }

    $email = trim((string) ($akun['email'] ?? ''));
    $prefix = strstr($email, '@', true);
    $prefix = $prefix !== false ? $prefix : $email;
    $prefix = str_replace(['.', '_', '-'], ' ', $prefix);

    return ucwords(trim($prefix)) !== '' ? ucwords(trim($prefix)) : 'Pengguna';
}

function inisialNama(string $nama): string
{
    $kata = preg_split('/\s+/', trim($nama)) ?: [];
    $kata = array_values(array_filter($kata, static fn ($item): bool => $item !== ''));

    if ($kata === []) {
        return 'PG';
    }

    $hurufPertama = function_exists('mb_substr') ? mb_substr($kata[0], 0, 1) : substr($kata[0], 0, 1);
    $hurufTerakhir = count($kata) > 1
        ? (function_exists('mb_substr') ? mb_substr($kata[count($kata) - 1], 0, 1) : substr($kata[count($kata) - 1], 0, 1))
        : (function_exists('mb_substr') ? mb_substr($kata[0], 1, 1) : substr($kata[0], 1, 1));

    $inisial = strtoupper($hurufPertama . $hurufTerakhir);

    return $inisial !== '' ? $inisial : 'PG';
}

function formatLoginTerakhir(?string $waktu): string
{
    if ($waktu === null || trim($waktu) === '') {
        return 'Belum pernah login';
    }

    $timestamp = strtotime($waktu);
    if ($timestamp === false) {
        return $waktu;
    }

    if (date('Y-m-d', $timestamp) === date('Y-m-d')) {
        return 'Hari ini, ' . date('H:i', $timestamp);
    }

    if (date('Y-m-d', $timestamp) === date('Y-m-d', strtotime('-1 day'))) {
        return 'Kemarin, ' . date('H:i', $timestamp);
    }

    return date('d M Y, H:i', $timestamp);
}

/**
 * @return array{nama_lengkap: string, email: string, role: string, status_aktif: int, password: string|null}
 */
function bacaInputAkun(array $input, bool $wajibPassword): array
{
    $errors = [];
    $roles = petaRole();

    $nama = trim((string) ($input['nama_lengkap'] ?? ''));
    $panjangNama = function_exists('mb_strlen') ? mb_strlen($nama) : strlen($nama);
    if ($nama === '') {
        $errors[] = 'Nama lengkap pegawai wajib diisi.';
    } elseif ($panjangNama < 3) {
        $errors[] = 'Nama lengkap minimal 3 karakter.';
    } elseif ($panjangNama > 100) {
        $errors[] = 'Nama lengkap maksimal 100 karakter.';
    }

    $email = strtolower(trim((string) ($input['email'] ?? '')));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'Alamat surel tidak valid.';
    } elseif (strlen($email) > 255) {
        $errors[] = 'Alamat surel maksimal 255 karakter.';
    }

    $role = trim((string) ($input['role'] ?? ''));
    if (!isset($roles[$role])) {
        $errors[] = 'Peran organisasi wajib dipilih dengan benar.';
    }

    $password = (string) ($input['password'] ?? '');
    $panjangPassword = strlen($password);

    if ($wajibPassword || $password !== '') {
        if ($panjangPassword < 6) {
            $errors[] = 'Kata sandi minimal 6 karakter.';
        } elseif ($panjangPassword > 100) {
            $errors[] = 'Kata sandi maksimal 100 karakter.';
        }
    }

    if ($errors !== []) {
        throw new InvalidArgumentException(implode(' ', $errors));
    }

    return [
        'nama_lengkap' => $nama,
        'email' => $email,
        'role' => $role,
        'status_aktif' => ((string) ($input['status_aktif'] ?? '0')) === '1' ? 1 : 0,
        'password' => $password !== '' ? $password : null,
    ];
}

/* --------------------------------------------------------------------------
 * Inisialisasi database
 * ----------------------------------------------------------------------- */

try {
    $db = Database::getInstance();
} catch (Throwable $e) {
    http_response_code(500);
    exit('Database tidak dapat dibuka: ' . e($e->getMessage()));
}

/* --------------------------------------------------------------------------
 * Proses aksi POST: tambah / ubah / hapus
 * ----------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf();

        $action = (string) ($_POST['action'] ?? '');
        $roles = petaRole();

        if ($action === 'tambah' || $action === 'ubah') {
            $idAkun = 0;
            if ($action === 'ubah') {
                $idAkun = filter_var($_POST['id_akun'] ?? null, FILTER_VALIDATE_INT);
                if ($idAkun === false || $idAkun <= 0) {
                    throw new InvalidArgumentException('ID akun yang akan diubah tidak valid.');
                }

                $stmt = $db->prepare('SELECT * FROM akun WHERE id_akun = ?');
                $stmt->execute([$idAkun]);
                $akunLama = $stmt->fetch();
                if ($akunLama === false) {
                    throw new InvalidArgumentException('Akun yang akan diubah tidak ditemukan.');
                }
            }

            $data = bacaInputAkun($_POST, $action === 'tambah');

            $currentUserId = (int) (auth_user()['id_akun'] ?? 0);
            if ($action === 'ubah' && $idAkun === $currentUserId && (int) $data['status_aktif'] !== 1) {
                throw new InvalidArgumentException('Anda tidak dapat menonaktifkan akun yang sedang digunakan.');
            }

            // Email harus unik, kecuali untuk akun yang sedang diubah.
            $stmt = $db->prepare('SELECT id_akun FROM akun WHERE email = ? AND id_akun <> ?');
            $stmt->execute([$data['email'], $idAkun]);
            if ($stmt->fetchColumn() !== false) {
                throw new InvalidArgumentException('Email akun sudah terdaftar dalam sistem.');
            }

            $hakAkses = $roles[$data['role']]['hak'];

            if ($action === 'tambah') {
                $stmt = $db->prepare(
                    'INSERT INTO akun (email, nama_lengkap, password_hash, hak_akses, role, status_aktif, last_login_time)
                     VALUES (?, ?, ?, ?, ?, ?, NULL)'
                );
                $stmt->execute([
                    $data['email'],
                    $data['nama_lengkap'],
                    password_hash((string) $data['password'], PASSWORD_BCRYPT),
                    $hakAkses,
                    $data['role'],
                    $data['status_aktif'],
                ]);

                redirectWithFlash('success', "Akun '{$data['nama_lengkap']}' ({$data['role']}) berhasil ditambahkan.");
            }

            if ($data['password'] !== null) {
                $stmt = $db->prepare(
                    'UPDATE akun SET email = ?, nama_lengkap = ?, password_hash = ?, hak_akses = ?, role = ?, status_aktif = ?
                     WHERE id_akun = ?'
                );
                $stmt->execute([
                    $data['email'],
                    $data['nama_lengkap'],
                    password_hash((string) $data['password'], PASSWORD_BCRYPT),
                    $hakAkses,
                    $data['role'],
                    $data['status_aktif'],
                    $idAkun,
                ]);
            } else {
                $stmt = $db->prepare(
                    'UPDATE akun SET email = ?, nama_lengkap = ?, hak_akses = ?, role = ?, status_aktif = ?
                     WHERE id_akun = ?'
                );
                $stmt->execute([
                    $data['email'],
                    $data['nama_lengkap'],
                    $hakAkses,
                    $data['role'],
                    $data['status_aktif'],
                    $idAkun,
                ]);
            }

            if ($action === 'ubah' && $idAkun === $currentUserId) {
                $_SESSION['auth_user']['email']        = $data['email'];
                $_SESSION['auth_user']['nama_lengkap'] = $data['nama_lengkap'];
                $_SESSION['auth_user']['role']         = $data['role'];
                $_SESSION['auth_user']['hak_akses']    = $hakAkses;
            }

            redirectWithFlash('success', "Akun '{$data['nama_lengkap']}' berhasil diperbarui.");
        }

        if ($action === 'hapus') {
            $idAkun = filter_var($_POST['id_akun'] ?? null, FILTER_VALIDATE_INT);
            if ($idAkun === false || $idAkun <= 0) {
                throw new InvalidArgumentException('ID akun yang akan dihapus tidak valid.');
            }

            $stmt = $db->prepare('SELECT * FROM akun WHERE id_akun = ?');
            $stmt->execute([$idAkun]);
            $akun = $stmt->fetch();
            if ($akun === false) {
                throw new InvalidArgumentException('Akun yang akan dihapus tidak ditemukan.');
            }

            if ($idAkun === (int) (auth_user()['id_akun'] ?? 0)) {
                throw new InvalidArgumentException('Anda tidak dapat menghapus akun yang sedang digunakan.');
            }

            if ($akun['role'] === 'Pemilik Toko') {
                $jumlahOwner = (int) $db->query("SELECT COUNT(*) FROM akun WHERE role = 'Pemilik Toko'")->fetchColumn();
                if ($jumlahOwner <= 1) {
                    throw new InvalidArgumentException('Akun Pemilik Toko terakhir tidak boleh dihapus.');
                }
            }

            $stmt = $db->prepare('SELECT COUNT(*) FROM transaksi WHERE id_akun = ?');
            $stmt->execute([$idAkun]);
            if ((int) $stmt->fetchColumn() > 0) {
                throw new InvalidArgumentException('Akun tidak bisa dihapus karena memiliki riwayat transaksi. Nonaktifkan akun tersebut sebagai gantinya.');
            }

            $db->prepare('DELETE FROM akun WHERE id_akun = ?')->execute([$idAkun]);

            redirectWithFlash('success', "Akun '" . namaTampil($akun) . "' berhasil dihapus.");
        }

        throw new InvalidArgumentException('Aksi yang diminta tidak dikenali.');
    } catch (InvalidArgumentException $e) {
        redirectWithFlash('danger', $e->getMessage());
    } catch (Throwable $e) {
        error_log('[kelola_akun] ' . $e->getMessage());
        redirectWithFlash('danger', 'Gagal memproses data akun: ' . $e->getMessage());
    }
}

/* --------------------------------------------------------------------------
 * Ambil data untuk halaman
 * ----------------------------------------------------------------------- */

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
if (!is_array($flash) || !isset($flash['type'], $flash['message'])) {
    $flash = null;
}

$roles = petaRole();
$akunList = $db->query('SELECT * FROM akun ORDER BY id_akun ASC')->fetchAll();

$roleCount = ['owner' => 0, 'kasir' => 0, 'stok' => 0, 'lain' => 0];
$statusCount = ['aktif' => 0, 'nonaktif' => 0];
$akunData = [];

foreach ($akunList as $akun) {
    $filterRole = $roles[$akun['role']]['filter'] ?? 'lain';
    $roleCount[$filterRole]++;
    $statusCount[(int) $akun['status_aktif'] === 1 ? 'aktif' : 'nonaktif']++;

    $nama = namaTampil($akun);
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

$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;

$activePage = 'kelola_akun';
$pageTitle = 'Kelola Akun & Hak Akses Pengguna';
$moduleBreadcrumb = 'USER & SETTINGS / HAK AKSES PENGGUNA';
$userName = 'Ferdyansah';
$userRole = 'Pemilik Toko (Owner)';
$userInitials = 'FF';

require_once __DIR__ . '/layout/header.php';
?>

<div class="page-title-box">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 14px;">
        <div>
            <h1 class="page-title">Kelola Akun &amp; Akses Pengguna</h1>
            <p class="page-subtitle">Otorisasi sistem, pengaturan peran karyawan (Role-Based Access Control), dan audit status sesi aktif.</p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button type="button" class="btn btn-primary btn-sm" onclick="openStaffModal()">
                + Tambah Karyawan Baru
            </button>
        </div>
    </div>
</div>

<?php if ($flash !== null): ?>
    <?php $alertType = in_array($flash['type'], ['success', 'danger', 'info', 'warning'], true) ? $flash['type'] : 'info'; ?>
    <div class="alert alert-<?= e($alertType); ?>" role="alert">
        <span><?= e($flash['message']); ?></span>
        <button type="button" class="alert-close" aria-label="Tutup pesan" onclick="this.parentElement.remove();">&times;</button>
    </div>
<?php endif; ?>

<!-- 4 KARTU RINGKASAN -->
<div class="stats-grid">
    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">Total Akun Terdaftar</div>
            <div class="stat-value"><?= $totalAkun; ?> Pengguna</div>
            <div class="stat-sub"><?= $statusCount['aktif']; ?> akun aktif</div>
        </div>
        <div class="stat-icon teal" aria-hidden="true">👥</div>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">Pemilik Toko</div>
            <div class="stat-value"><?= $roleCount['owner']; ?> Akun</div>
            <div class="stat-sub">Akses SUPER_ADMIN</div>
        </div>
        <div class="stat-icon amber" aria-hidden="true">👑</div>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">Kasir</div>
            <div class="stat-value"><?= $roleCount['kasir']; ?> Akun</div>
            <div class="stat-sub">Akses KASIR_POS</div>
        </div>
        <div class="stat-icon blue" aria-hidden="true">🧾</div>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">Petugas Stok</div>
            <div class="stat-value"><?= $roleCount['stok']; ?> Akun</div>
            <div class="stat-sub">Akses PETUGAS_GUDANG</div>
        </div>
        <div class="stat-icon green" aria-hidden="true">📦</div>
    </div>
</div>

<!-- FILTER & PENCARIAN -->
<div class="card-box filter-bar">
    <div class="filter-group" role="group" aria-label="Filter akun">
        <button type="button" class="filter-chip is-active" data-filter="all" onclick="setStatusFilter('all')" aria-pressed="true">
            Semua Akun (<?= $totalAkun; ?>)
        </button>
        <button type="button" class="filter-chip" data-filter="owner" onclick="setStatusFilter('owner')" aria-pressed="false">
            Pemilik Toko (<?= $roleCount['owner']; ?>)
        </button>
        <button type="button" class="filter-chip" data-filter="kasir" onclick="setStatusFilter('kasir')" aria-pressed="false">
            Kasir (<?= $roleCount['kasir']; ?>)
        </button>
        <button type="button" class="filter-chip" data-filter="stok" onclick="setStatusFilter('stok')" aria-pressed="false">
            Petugas Stok (<?= $roleCount['stok']; ?>)
        </button>
        <button type="button" class="filter-chip" data-filter="inactive" onclick="setStatusFilter('inactive')" aria-pressed="false">
            Nonaktif (<?= $statusCount['nonaktif']; ?>)
        </button>
    </div>
    <div class="search-box">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="11" cy="11" r="7"></circle>
            <path d="m20 20-3.5-3.5"></path>
        </svg>
        <input type="search" id="staffSearch" class="form-control" placeholder="Cari nama, email, atau ID akun..." autocomplete="off">
    </div>
</div>

<!-- TABEL AKUN -->
<div class="card-box" id="staffSection">
    <div class="table-wrap">
        <table class="sketch-table table-akun" id="staffTable">
            <thead>
                <tr>
                    <th>Nama Karyawan</th>
                    <th>Email &amp; ID Akun</th>
                    <th>Peran (Role)</th>
                    <th>Status Akun</th>
                    <th>Terakhir Login</th>
                    <th style="text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($akunList as $akun): ?>
                    <?php
                    $idAkun = (int) $akun['id_akun'];
                    $nama = namaTampil($akun);
                    $roleInfo = $roles[$akun['role']] ?? ['hak' => (string) $akun['hak_akses'], 'class' => 'pill-gray', 'filter' => 'lain'];
                    $aktif = (int) $akun['status_aktif'] === 1;
                    $tidakBisaHapus = $lastOwnerId === $idAkun;
                    ?>
                    <tr data-role="<?= e($roleInfo['filter']); ?>" data-status="<?= $aktif ? 'aktif' : 'nonaktif'; ?>" class="<?= $aktif ? '' : 'row-muted'; ?>">
                        <td>
                            <div class="cell-flex">
                                <span class="avatar-mini"><?= e(inisialNama($nama)); ?></span>
                                <div>
                                    <div class="cell-title" style="margin-top: 0;"><?= e($nama); ?></div>
                                    <div class="cell-sub">Pengguna sistem</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div><?= e($akun['email']); ?></div>
                            <div class="cell-sub" style="font-family: monospace;">EMP-<?= str_pad((string) $idAkun, 3, '0', STR_PAD_LEFT); ?></div>
                        </td>
                        <td><span class="pill <?= e($roleInfo['class']); ?>">• <?= e($akun['role']); ?></span></td>
                        <td><span class="pill <?= $aktif ? 'pill-green' : 'pill-gray'; ?>">• <?= $aktif ? 'Aktif' : 'Nonaktif'; ?></span></td>
                        <td><?= e(formatLoginTerakhir($akun['last_login_time'] !== null ? (string) $akun['last_login_time'] : null)); ?></td>
                        <td style="text-align: center;">
                            <div class="action-group">
                                <button type="button" class="icon-btn" title="Ubah akun" aria-label="Ubah akun <?= e($nama); ?>" onclick="openStaffModal(<?= $idAkun; ?>)">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M12 20h9"></path>
                                        <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                                    </svg>
                                </button>
                                <form method="POST" action="kelola_akun.php" class="inline-form" onsubmit="return confirm('Hapus akun ini? Akun akan dihapus permanen dari sistem.');">
                                    <?= csrfField(); ?>
                                    <input type="hidden" name="action" value="hapus">
                                    <input type="hidden" name="id_akun" value="<?= $idAkun; ?>">
                                    <button type="submit" class="icon-btn danger" title="<?= $tidakBisaHapus ? 'Pemilik Toko terakhir tidak boleh dihapus' : 'Hapus akun'; ?>" aria-label="Hapus akun <?= e($nama); ?>" <?= $tidakBisaHapus ? 'disabled' : ''; ?>>
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M3 6h18"></path>
                                            <path d="M8 6V4h8v2"></path>
                                            <path d="m19 6-1 14H6L5 6"></path>
                                            <path d="M10 11v6"></path>
                                            <path d="M14 11v6"></path>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <tr id="emptyRow" style="<?= count($akunList) === 0 ? '' : 'display: none;'; ?>">
                    <td colspan="6">
                        <div class="empty-state">
                            <strong>Tidak ada akun yang cocok</strong>
                            <span>Ubah kata kunci pencarian atau tambahkan akun baru.</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="table-footer">
        <div id="tableInfo">Menampilkan <?= count($akunList); ?> dari <?= count($akunList); ?> akun terdaftar</div>
        <div class="muted-note">Kata sandi akun baru disimpan dalam bentuk hash bcrypt</div>
    </div>
</div>

<!-- MODAL TAMBAH (UI-03) / UBAH (UI-04) -->
<div class="modal-backdrop" id="staffModal" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="staffModalTitle">
        <div class="modal-head">
            <div>
                <h2 id="staffModalTitle">Menambah Akun &amp; Hak Akses [UI-03]</h2>
                <p id="staffModalSub">Lengkapi data karyawan. Hak akses mengikuti peran yang dipilih.</p>
            </div>
            <button type="button" class="icon-btn" onclick="closeStaffModal()" aria-label="Tutup dialog">&times;</button>
        </div>

        <form method="POST" action="kelola_akun.php" id="staffForm">
            <?= csrfField(); ?>
            <input type="hidden" name="action" id="formAction" value="tambah">
            <input type="hidden" name="id_akun" id="formIdAkun" value="">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="nama_lengkap">Nama Lengkap Pegawai <span class="req">*</span></label>
                    <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control" maxlength="100" placeholder="Contoh: Rian Anggara" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Alamat Surel Login <span class="req">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" maxlength="255" placeholder="rian.a@omnistok.id" required>
                    <div class="form-hint">Email harus unik dan dipakai sebagai identitas login.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Kata Sandi Akses <span class="req" id="passwordReq">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" maxlength="100" placeholder="Minimal 6 karakter" autocomplete="new-password">
                    <div class="form-hint" id="passwordHint">Wajib diisi untuk akun baru.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="role">Peran Organisasi (Role) <span class="req">*</span></label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="Kasir">Kasir (Terminal POS Front-Desk)</option>
                        <option value="Petugas Stok">Petugas Stok (Staff Gudang &amp; Logistik)</option>
                        <option value="Pemilik Toko">Pemilik Toko (Super Administrator)</option>
                    </select>
                </div>

                <div class="form-group">
                    <input type="hidden" name="status_aktif" value="0">
                    <label class="check-row" for="status_aktif">
                        <input type="checkbox" id="status_aktif" name="status_aktif" value="1" checked>
                        <span>Akun aktif dan dapat digunakan</span>
                    </label>
                </div>
            </div>

            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" onclick="closeStaffModal()">Batal</button>
                <button type="submit" class="btn btn-primary" id="staffSubmit">Simpan Data Akun</button>
            </div>
        </form>
    </div>
</div>

<script>
const AKUN_DATA = <?= json_encode($akunData, $jsonFlags) ?: '{}'; ?>;

(function () {
    'use strict';

    const modal = document.getElementById('staffModal');
    const form = document.getElementById('staffForm');
    const formAction = document.getElementById('formAction');
    const formId = document.getElementById('formIdAkun');
    const modalTitle = document.getElementById('staffModalTitle');
    const modalSub = document.getElementById('staffModalSub');
    const submitButton = document.getElementById('staffSubmit');
    const passwordInput = document.getElementById('password');
    const passwordReq = document.getElementById('passwordReq');
    const passwordHint = document.getElementById('passwordHint');
    const searchInput = document.getElementById('staffSearch');
    const tableInfo = document.getElementById('tableInfo');
    const emptyRow = document.getElementById('emptyRow');
    const rows = Array.from(document.querySelectorAll('#staffTable tbody tr[data-role]'));
    let activeFilter = 'all';

    function resetToAddMode() {
        form.reset();
        formAction.value = 'tambah';
        formId.value = '';
        modalTitle.textContent = 'Menambah Akun & Hak Akses [UI-03]';
        modalSub.textContent = 'Lengkapi data karyawan. Hak akses mengikuti peran yang dipilih.';
        submitButton.textContent = 'Simpan Data Akun';
        passwordInput.required = true;
        passwordReq.style.display = '';
        passwordHint.textContent = 'Wajib diisi untuk akun baru.';
        document.getElementById('status_aktif').checked = true;
    }

    function openStaffModal(id) {
        resetToAddMode();

        if (id !== undefined && id !== null && Object.prototype.hasOwnProperty.call(AKUN_DATA, String(id))) {
            const data = AKUN_DATA[String(id)];

            formAction.value = 'ubah';
            formId.value = String(data.id_akun);
            document.getElementById('nama_lengkap').value = data.nama_lengkap;
            document.getElementById('email').value = data.email;
            document.getElementById('role').value = data.role;
            document.getElementById('status_aktif').checked = Number(data.status_aktif) === 1;

            modalTitle.textContent = 'Mengubah Akun & Hak Akses [UI-04]';
            modalSub.textContent = 'Perbarui data akun ' + data.email + '. Kosongkan kata sandi bila tidak diubah.';
            submitButton.textContent = 'Simpan Perubahan';
            passwordInput.required = false;
            passwordReq.style.display = 'none';
            passwordHint.textContent = 'Kosongkan bila kata sandi tidak ingin diganti.';
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        window.setTimeout(function () {
            document.getElementById('nama_lengkap').focus();
        }, 60);
    }

    function closeStaffModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    }

    function applyFilters() {
        const query = searchInput.value.trim().toLowerCase();
        let visible = 0;

        rows.forEach(function (row) {
            const role = row.dataset.role;
            const status = row.dataset.status;
            const matchesFilter = activeFilter === 'all'
                || (activeFilter === 'inactive' ? status === 'nonaktif' : role === activeFilter);
            const matchesSearch = query === '' || row.textContent.toLowerCase().includes(query);
            const show = matchesFilter && matchesSearch;

            row.style.display = show ? '' : 'none';
            if (show) {
                visible += 1;
            }
        });

        tableInfo.textContent = 'Menampilkan ' + visible + ' dari ' + rows.length + ' akun terdaftar';
        emptyRow.style.display = visible === 0 ? '' : 'none';

        document.querySelectorAll('[data-filter]').forEach(function (button) {
            const isActive = button.dataset.filter === activeFilter;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function setStatusFilter(filter) {
        activeFilter = filter;
        applyFilters();
        document.getElementById('staffSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    searchInput.addEventListener('input', applyFilters);

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeStaffModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeStaffModal();
        }
    });

    window.openStaffModal = openStaffModal;
    window.closeStaffModal = closeStaffModal;
    window.setStatusFilter = setStatusFilter;

    applyFilters();
})();
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
