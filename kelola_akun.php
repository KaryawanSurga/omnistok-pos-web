<?php
/**
 * View Antarmuka: Kelola Data Akun & Hak Akses [UI-02]
 * Penulis: Justin Kelvin (JK - 255150407111098)
 * Referensi UI SDD: UI-02 (Mengelola Akun & Hak Akses), UI-03 (Menambah Akun), UI-04 (Mengubah Akun)
 * Disinkronkan 100% dengan Sketsa UI Modul 2
 */

require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();

$message = null;
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'tambah') {
        $email = trim($_POST['email']);
        $pass = trim($_POST['password']);
        $role = trim($_POST['role']);
        $hakAkses = ($role === 'Pemilik Toko') ? 'SUPER_ADMIN' : (($role === 'Kasir') ? 'KASIR_POS' : 'PETUGAS_GUDANG');

        try {
            $stmt = $db->prepare("INSERT INTO akun (email, password, hak_akses, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$email, $pass, $hakAkses, $role]);
            $message = "Akun karyawan baru '{$email}' ({$role}) berhasil didaftarkan ke sistem!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Gagal mendaftarkan akun: " . $e->getMessage();
            $messageType = "danger";
        }
    } elseif ($_POST['action'] === 'hapus') {
        $idAkun = intval($_POST['id_akun']);
        try {
            $stmt = $db->prepare("DELETE FROM akun WHERE id_akun = ?");
            $stmt->execute([$idAkun]);
            $message = "Akun ID #{$idAkun} berhasil dinonaktifkan/dihapus.";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Gagal menghapus akun: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

$daftarAkun = $db->query("SELECT * FROM akun ORDER BY id_akun ASC")->fetchAll();

$activePage = 'kelola_akun';
$pageTitle = 'Kelola Akun & Hak Akses Pengguna';
$moduleBreadcrumb = 'USER & SETTINGS / HAK AKSES PENGGUNA';
$userName = 'Ferdyansyah';
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
        <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="alert('Konsol izin Role & Hak Akses: Super Admin, Kasir POS, dan Petugas Stok.');">
                👥 Kelola Role &amp; Hak Akses
            </button>
            <button type="button" class="btn btn-primary btn-sm" onclick="openStaffModal()">
                + Tambah Karyawan Baru
            </button>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType; ?>"><?= htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- 2 SUMMARY CARDS PERSIS UI-02 -->
<div class="stats-grid" style="grid-template-columns: repeat(2, 1fr); margin-bottom: 24px;">
    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">TOTAL AKUN TERDAFTAR</div>
            <div class="stat-value">8 Pengguna</div>
            <div class="stat-sub">3 Peran: Owner, Kasir, Petugas Stok</div>
        </div>
        <div style="background: var(--primary-light); color: var(--primary); width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            👥
        </div>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">KASIR AKTIF SAAT INI</div>
            <div class="stat-value">2 Terminal</div>
            <div class="stat-sub">Register POS 01 &amp; Register POS 02</div>
        </div>
        <div style="background: #e0f2fe; color: #0284c7; width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            🖥️
        </div>
    </div>
</div>

<!-- FILTER BAR & SEARCH PERSIS UI-02 -->
<div class="card-box" style="padding: 14px 20px; margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button type="button" class="btn btn-primary btn-sm" onclick="filterRole('all')">Semua Akun (8)</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="filterRole('kasir')">Kasir (4)</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="filterRole('petugas stok')">Petugas Stok (3)</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="filterRole('pemilik toko')">Pemilik Toko (1)</button>
        </div>
        <div style="min-width: 280px;">
            <input type="text" id="staffSearch" class="form-control" placeholder="🔍 Cari nama, ID, email..." onkeyup="filterStaffTable()">
        </div>
    </div>
</div>

<!-- DATA TABLE PENGGUNA PERSIS UI-02 -->
<div class="card-box">
    <div class="table-wrap">
        <table class="sketch-table" id="staffTable">
            <thead>
                <tr>
                    <th>NAMA KARYAWAN</th>
                    <th>EMAIL &amp; ID AKUN</th>
                    <th>PERAN (ROLE)</th>
                    <th>STATUS AKUN</th>
                    <th>TERAKHIR LOGIN</th>
                    <th style="text-align: center;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <!-- ROW 1 -->
                <tr>
                    <td><strong>Budi Santoso</strong></td>
                    <td>
                        <div style="color: var(--dark); font-size: 0.85rem;">budi.s@omnistok.id</div>
                        <div style="font-size: 0.72rem; color: var(--gray-400); font-family: monospace;">EMP-001</div>
                    </td>
                    <td><span class="pill pill-teal">• Kasir</span></td>
                    <td><span class="pill pill-green">• Aktif Bekerja</span></td>
                    <td style="font-size: 0.82rem; color: var(--gray-500);">Hari ini, 07:45</td>
                    <td style="text-align: center;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="alert('Ubah Akun UI-04')">✎</button>
                        <button type="button" class="btn btn-secondary btn-sm" style="color: #dc2626;" onclick="alert('Akun kasir aktif')">🗑</button>
                    </td>
                </tr>

                <!-- ROW 2 -->
                <tr>
                    <td><strong>Doni Prasetyo</strong></td>
                    <td>
                        <div style="color: var(--dark); font-size: 0.85rem;">doni.prasetyo@omnistok.id</div>
                        <div style="font-size: 0.72rem; color: var(--gray-400); font-family: monospace;">EMP-002</div>
                    </td>
                    <td><span class="pill pill-teal">• Petugas Stok</span></td>
                    <td><span class="pill pill-green">• Aktif Bekerja</span></td>
                    <td style="font-size: 0.82rem; color: var(--gray-500);">Hari ini, 06:12</td>
                    <td style="text-align: center;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="alert('Ubah Akun UI-04')">✎</button>
                        <button type="button" class="btn btn-secondary btn-sm" style="color: #dc2626;" onclick="alert('Akun dilindungi')">🗑</button>
                    </td>
                </tr>

                <!-- ROW 3 -->
                <tr>
                    <td><strong>Ferdyansyah</strong></td>
                    <td>
                        <div style="color: var(--dark); font-size: 0.85rem;">ferdyansyah@omnistok.id</div>
                        <div style="font-size: 0.72rem; color: var(--gray-400); font-family: monospace;">EMP-003</div>
                    </td>
                    <td><span class="pill pill-teal" style="background: #fdf2f8; color: #db2777; border-color: #fbcfe8;">• Pemilik Toko</span></td>
                    <td><span class="pill pill-green">• Aktif Bekerja</span></td>
                    <td style="font-size: 0.82rem; color: var(--gray-500);">Hari ini, 08:02</td>
                    <td style="text-align: center;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="alert('Ubah Akun UI-04')">✎</button>
                        <button type="button" class="btn btn-secondary btn-sm" style="color: #dc2626;" disabled>🗑</button>
                    </td>
                </tr>

                <!-- ROW 4 -->
                <tr>
                    <td><strong>Filbert Bryan</strong></td>
                    <td>
                        <div style="color: var(--dark); font-size: 0.85rem;">filbert.b@omnistok.id</div>
                        <div style="font-size: 0.72rem; color: var(--gray-400); font-family: monospace;">EMP-004</div>
                    </td>
                    <td><span class="pill pill-teal">• Kasir</span></td>
                    <td><span class="pill pill-amber">• Cuti</span></td>
                    <td style="font-size: 0.82rem; color: var(--gray-500);">Kemarin, 19:30</td>
                    <td style="text-align: center;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="alert('Ubah Akun UI-04')">✎</button>
                        <button type="button" class="btn btn-secondary btn-sm" style="color: #dc2626;" onclick="alert('Akun cuti dinonaktifkan sementara.')">🗑</button>
                    </td>
                </tr>

                <!-- ROW 5 -->
                <tr>
                    <td><strong>Valeriano</strong></td>
                    <td>
                        <div style="color: var(--dark); font-size: 0.85rem;">valeriano@omnistok.id</div>
                        <div style="font-size: 0.72rem; color: var(--gray-400); font-family: monospace;">EMP-005</div>
                    </td>
                    <td><span class="pill pill-teal">• Petugas Stok</span></td>
                    <td><span class="pill pill-green">• Aktif Bekerja</span></td>
                    <td style="font-size: 0.82rem; color: var(--gray-500);">Hari ini, 08:15</td>
                    <td style="text-align: center;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="alert('Ubah Akun UI-04')">✎</button>
                        <button type="button" class="btn btn-secondary btn-sm" style="color: #dc2626;" onclick="alert('Akun terverifikasi')">🗑</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION BAR PERSIS UI-02 -->
    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 14px; border-top: 1px solid var(--gray-100); font-size: 0.82rem; color: var(--gray-500);">
        <div>Menampilkan 5 dari 8 pengguna terdaftar</div>
        <div style="display: flex; gap: 4px; align-items: center;">
            <button type="button" class="btn btn-primary btn-sm">1</button>
            <button type="button" class="btn btn-secondary btn-sm">2</button>
            <button type="button" class="btn btn-secondary btn-sm">&gt;</button>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH KARYAWAN BARU (UI-03) -->
<div id="staffModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(3px);">
    <div style="background: white; border-radius: 10px; max-width: 520px; width: 90%; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.25);">
        <div style="padding: 16px 22px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
            <strong style="font-size: 1.05rem; color: #0f172a;">Menambah Akun &amp; Hak Akses [UI-03]</strong>
            <button type="button" onclick="closeStaffModal()" style="background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #64748b;">&times;</button>
        </div>
        <form method="POST" action="kelola_akun.php" style="padding: 22px;">
            <input type="hidden" name="action" value="tambah">

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label">Nomor Identifikasi Staf (Read-only)</label>
                <input type="text" class="form-control" value="EMP-00<?= count($daftarAkun) + 1; ?>" readonly style="background: #f1f5f9; color: #64748b;">
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label">Nama Lengkap Pegawai</label>
                <input type="text" name="nama_pegawai" class="form-control" placeholder="Contoh: Rian Anggara" required>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label">Alamat Surel Login</label>
                <input type="email" name="email" class="form-control" placeholder="rian.a@omnistok.id" required>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label">Kata Sandi Akses</label>
                <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label">Peran Organisasi (Role)</label>
                <select name="role" class="form-control" required>
                    <option value="Kasir">Kasir (Terminal POS Front-Desk)</option>
                    <option value="Petugas Stok">Petugas Stok (Staff Gudang &amp; Logistik)</option>
                    <option value="Pemilik Toko">Pemilik Toko (Super Administrator)</option>
                </select>
            </div>

            <div class="action-bar" style="margin-top: 10px; padding-top: 14px;">
                <button type="button" class="btn btn-secondary" onclick="closeStaffModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Data Akun</button>
            </div>
        </form>
    </div>
</div>

<script>
function openStaffModal() {
    document.getElementById('staffModal').style.display = 'flex';
}
function closeStaffModal() {
    document.getElementById('staffModal').style.display = 'none';
}
function filterStaffTable() {
    const q = document.getElementById('staffSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#staffTable tbody tr');
    rows.forEach(tr => {
        tr.style.display = (!q || tr.innerText.toLowerCase().includes(q)) ? '' : 'none';
    });
}
function filterRole(role) {
    const rows = document.querySelectorAll('#staffTable tbody tr');
    rows.forEach(tr => {
        if (role === 'all') tr.style.display = '';
        else tr.style.display = tr.innerText.toLowerCase().includes(role) ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
