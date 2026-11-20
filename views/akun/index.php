<?php
/**
 * View: Kelola Akun & Hak Akses [UI-02/03/04]
 * Dipanggil oleh halaman root setelah controller menyiapkan data.
 */
require dirname(__DIR__, 2) . '/layout/header.php';
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

<?php require dirname(__DIR__, 2) . '/layout/footer.php'; ?>
