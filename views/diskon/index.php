<?php
/**
 * View: Kelola Diskon & Promo [UI-06/07/08]
 * Dipanggil oleh halaman root setelah controller menyiapkan data.
 */
require dirname(__DIR__, 2) . '/layout/header.php';
?>


<div class="page-title-box">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 14px;">
        <div>
            <h1 class="page-title">Kelola Diskon &amp; Promo</h1>
            <p class="page-subtitle">Konfigurasi program diskon otomatis POS, potongan harga bertingkat, dan kuota pemakaian pelanggan.</p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="setStatusFilter('expired', true)">
                ▤ Riwayat Diskon
            </button>
            <button type="button" class="btn btn-primary btn-sm" onclick="openPromoModal()">
                + Buat Promo Baru
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

<!-- 4 KARTU RINGKASAN UI-06 -->
<div class="stats-grid">
    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">Promo Aktif Berjalan</div>
            <div class="stat-value"><?= $promoAktifBerjalan; ?> Program</div>
            <div class="stat-sub">+<?= $promoBaruMingguIni; ?> baru minggu ini</div>
        </div>
        <div class="stat-icon teal" aria-hidden="true">%</div>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">Potongan Hari Ini</div>
            <div class="stat-value"><?= formatRupiah($potonganHariIni); ?></div>
            <div class="stat-sub"><?= $transaksiPromoHariIni; ?> transaksi memakai promo</div>
        </div>
        <div class="stat-icon blue" aria-hidden="true">🏷</div>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">Transaksi Pakai Promo</div>
            <div class="stat-value"><?= $transaksiPromoHariIni; ?> Struk</div>
            <div class="stat-sub">Rasio: <?= e($rasioLabel); ?>% dari <?= $transaksiHariIni; ?> transaksi</div>
        </div>
        <div class="stat-icon green" aria-hidden="true">🧾</div>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">Berakhir Minggu Ini</div>
            <div class="stat-value"><?= $promoBerakhirMingguIni; ?> Promo</div>
            <div class="stat-sub"><?= e($namaPromoBerakhir !== false ? $namaPromoBerakhir : 'Tidak ada promo yang berakhir'); ?></div>
        </div>
        <div class="stat-icon amber" aria-hidden="true">⏰</div>
    </div>
</div>

<!-- FILTER & PENCARIAN -->
<div class="card-box filter-bar">
    <div class="filter-group" role="group" aria-label="Filter status promo">
        <button type="button" class="filter-chip is-active" data-filter="all" onclick="setStatusFilter('all')" aria-pressed="true">
            Semua Promo (<?= $statusCount['all']; ?>)
        </button>
        <button type="button" class="filter-chip" data-filter="active" onclick="setStatusFilter('active')" aria-pressed="false">
            Aktif Berjalan (<?= $statusCount['active']; ?>)
        </button>
        <button type="button" class="filter-chip" data-filter="scheduled" onclick="setStatusFilter('scheduled')" aria-pressed="false">
            Dijadwalkan (<?= $statusCount['scheduled']; ?>)
        </button>
        <button type="button" class="filter-chip" data-filter="expired" onclick="setStatusFilter('expired')" aria-pressed="false">
            Riwayat (<?= $riwayatCount; ?>)
        </button>
    </div>
    <div class="search-box">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="11" cy="11" r="7"></circle>
            <path d="m20 20-3.5-3.5"></path>
        </svg>
        <input type="search" id="promoSearch" class="form-control" placeholder="Cari nama promo / kode promo..." autocomplete="off">
    </div>
</div>

<!-- TABEL DAFTAR DISKON -->
<div class="card-box" id="promoSection">
    <div class="table-wrap">
        <table class="sketch-table" id="promoTable">
            <thead>
                <tr>
                    <th>Kode &amp; Nama Promo</th>
                    <th>Tipe</th>
                    <th>Nilai Diskon</th>
                    <th>Cakupan Produk</th>
                    <th>Periode Aktif</th>
                    <th>Penggunaan</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: center;">Aktifkan</th>
                    <th style="text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($diskonList as $diskon): ?>
                    <?php
                    $idDiskon = (int) $diskon['id_diskon'];
                    $status = promoStatus($diskon);
                    $kodePromo = (string) (($diskon['kode_diskon'] ?? '') ?: 'DSK-' . str_pad((string) $idDiskon, 3, '0', STR_PAD_LEFT));
                    $maksDiskon = $diskon['maks_diskon'] !== null ? (float) $diskon['maks_diskon'] : null;
                    $kuota = $diskon['kuota'] !== null ? (int) $diskon['kuota'] : null;
                    $terpakai = (int) $diskon['terpakai'];
                    $rowMuted = in_array($status['key'], ['inactive', 'expired'], true);
                    ?>
                    <tr data-status="<?= e($status['key']); ?>" class="<?= $rowMuted ? 'row-muted' : ''; ?>">
                        <td>
                            <span class="pill pill-teal code-pill"><?= e($kodePromo); ?></span>
                            <div class="cell-title"><?= e($diskon['nama_diskon']); ?></div>
                        </td>
                        <td><span class="cell-sub" style="margin-top: 0; font-size: 0.76rem; color: var(--gray-500);">% Persentase</span></td>
                        <td>
                            <strong class="discount-value"><?= formatPersen($diskon['presentase_diskon']); ?>%</strong>
                            <div class="cell-sub"><?= $maksDiskon !== null ? 'Maks ' . formatRupiah($maksDiskon) : 'Tanpa batas maks'; ?></div>
                        </td>
                        <td>
                            <span class="pill pill-gray"><?= e($diskon['nama_produk']); ?></span>
                            <div class="cell-sub"><?= e($diskon['kode_produk']); ?> · <?= e($diskon['kategori']); ?></div>
                        </td>
                        <td>
                            <div><?= formatTanggalIndo($diskon['tanggal_mulai']); ?> – <?= formatTanggalIndo($diskon['tanggal_selesai']); ?></div>
                            <div class="period-note <?= e($status['noteClass']); ?>"><?= e($status['note']); ?></div>
                        </td>
                        <td>
                            <strong style="color: var(--slate);"><?= $terpakai; ?>x</strong>
                            <div class="cell-sub"><?= $kuota !== null ? 'dari ' . $kuota . ' kuota' : 'Tanpa kuota'; ?></div>
                        </td>
                        <td style="text-align: center;">
                            <span class="pill <?= e($status['class']); ?>">• <?= e($status['label']); ?></span>
                        </td>
                        <td style="text-align: center;">
                            <form method="POST" action="kelola_diskon.php" class="inline-form">
                                <?= csrfField(); ?>
                                <input type="hidden" name="action" value="toggle_diskon">
                                <input type="hidden" name="id_diskon" value="<?= $idDiskon; ?>">
                                <input type="hidden" name="status_aktif" value="0">
                                <label class="switch">
                                    <input type="checkbox" name="status_aktif" value="1" <?= (int) $diskon['status_aktif'] === 1 ? 'checked' : ''; ?> onchange="this.form.submit()" aria-label="Aktifkan atau nonaktifkan promo <?= e($diskon['nama_diskon']); ?>">
                                    <span class="switch-slider"></span>
                                </label>
                            </form>
                        </td>
                        <td style="text-align: center;">
                            <div class="action-group">
                                <button type="button" class="icon-btn" title="Ubah promo" aria-label="Ubah promo <?= e($diskon['nama_diskon']); ?>" onclick="openPromoModal(<?= $idDiskon; ?>)">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M12 20h9"></path>
                                        <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                                    </svg>
                                </button>
                                <form method="POST" action="kelola_diskon.php" class="inline-form" onsubmit="return confirm('Hapus promo ini? Data diskon akan dihapus permanen.');">
                                    <?= csrfField(); ?>
                                    <input type="hidden" name="action" value="hapus_diskon">
                                    <input type="hidden" name="id_diskon" value="<?= $idDiskon; ?>">
                                    <button type="submit" class="icon-btn danger" title="Hapus promo" aria-label="Hapus promo <?= e($diskon['nama_diskon']); ?>">
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

                <tr id="emptyRow" style="<?= count($diskonList) === 0 ? '' : 'display: none;'; ?>">
                    <td colspan="9">
                        <div class="empty-state">
                            <strong>Tidak ada promo yang cocok</strong>
                            <span>Ubah kata kunci pencarian atau buat program promo baru.</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="table-footer">
        <div id="tableInfo">Menampilkan <?= count($diskonList); ?> dari <?= count($diskonList); ?> total konfigurasi diskon</div>
        <div class="muted-note">Prioritas potongan: urutan tertinggi ke terendah</div>
    </div>
</div>

<!-- MODAL TAMBAH (UI-07) / UBAH (UI-08) -->
<div class="modal-backdrop" id="promoModal" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="promoModalTitle">
        <div class="modal-head">
            <div>
                <h2 id="promoModalTitle">+ Buat Skema Promosi Baru [UI-07]</h2>
                <p id="promoModalSub">Lengkapi detail promo di bawah ini. Kode promo dibuat otomatis.</p>
            </div>
            <button type="button" class="icon-btn" onclick="closePromoModal()" aria-label="Tutup dialog">&times;</button>
        </div>

        <form method="POST" action="kelola_diskon.php" id="promoForm">
            <?= csrfField(); ?>
            <input type="hidden" name="action" id="formAction" value="tambah_diskon">
            <input type="hidden" name="id_diskon" id="formIdDiskon" value="">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="nama_diskon">Nama Program Promosi <span class="req">*</span></label>
                    <input type="text" id="nama_diskon" name="nama_diskon" class="form-control" maxlength="50" placeholder="Contoh: Diskon Gajian Sembako 15%" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="id_produk">Produk Sasaran Promo <span class="req">*</span></label>
                    <select id="id_produk" name="id_produk" class="form-control" required>
                        <option value="" disabled selected>— Pilih produk sasaran —</option>
                        <?php foreach ($produkPerKategori as $kategori => $items): ?>
                            <optgroup label="<?= e($kategori); ?>">
                                <?php foreach ($items as $produk): ?>
                                    <option value="<?= (int) $produk['id_produk']; ?>">
                                        <?= e($produk['kode_tampil']); ?> · <?= e($produk['nama_produk']); ?> (<?= formatRupiah((float) $produk['harga_jual']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="presentase_diskon">Persentase Diskon <span class="req">*</span></label>
                        <div class="input-suffix">
                            <input type="number" id="presentase_diskon" name="presentase_diskon" class="form-control" step="0.5" min="1" max="90" placeholder="15" required>
                            <span>%</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="maks_diskon">Maks. Potongan (Rp)</label>
                        <input type="number" id="maks_diskon" name="maks_diskon" class="form-control" min="0" step="any" placeholder="Kosongkan = tanpa batas">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="kuota">Kuota Pemakaian</label>
                    <input type="number" id="kuota" name="kuota" class="form-control" min="0" step="1" placeholder="Kosongkan atau 0 = tanpa kuota">
                    <div class="form-hint">Jumlah maksimal promo dapat dipakai. Kosongkan bila tidak dibatasi.</div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="tanggal_mulai">Tanggal Mulai <span class="req">*</span></label>
                        <input type="date" id="tanggal_mulai" name="tanggal_mulai" class="form-control" value="<?= e($defaultMulai); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="tanggal_selesai">Tanggal Berakhir <span class="req">*</span></label>
                        <input type="date" id="tanggal_selesai" name="tanggal_selesai" class="form-control" value="<?= e($defaultSelesai); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <input type="hidden" name="status_aktif" value="0">
                    <label class="check-row" for="status_aktif">
                        <input type="checkbox" id="status_aktif" name="status_aktif" value="1" checked>
                        <span>Aktifkan promo setelah disimpan</span>
                    </label>
                </div>

                <div class="promo-preview" id="promoPreview" aria-live="polite">
                    <span aria-hidden="true">💡</span>
                    <span class="preview-muted">Pilih produk dan isi persentase untuk melihat simulasi harga.</span>
                </div>
            </div>

            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" onclick="closePromoModal()">Batal</button>
                <button type="submit" class="btn btn-primary" id="promoSubmit">Simpan Program Diskon</button>
            </div>
        </form>
    </div>
</div>

<script>
const PROMO_DATA = <?= json_encode($promoData, $jsonFlags) ?: '{}'; ?>;
const HARGA_PRODUK = <?= json_encode($hargaProduk, $jsonFlags) ?: '{}'; ?>;
const TODAY_ISO = <?= json_encode($defaultMulai, $jsonFlags); ?>;

(function () {
    'use strict';

    const modal = document.getElementById('promoModal');
    const form = document.getElementById('promoForm');
    const formAction = document.getElementById('formAction');
    const formId = document.getElementById('formIdDiskon');
    const modalTitle = document.getElementById('promoModalTitle');
    const modalSub = document.getElementById('promoModalSub');
    const submitButton = document.getElementById('promoSubmit');
    const productSelect = document.getElementById('id_produk');
    const percentInput = document.getElementById('presentase_diskon');
    const maxInput = document.getElementById('maks_diskon');
    const quotaInput = document.getElementById('kuota');
    const startInput = document.getElementById('tanggal_mulai');
    const endInput = document.getElementById('tanggal_selesai');
    const statusInput = document.getElementById('status_aktif');
    const preview = document.getElementById('promoPreview');
    const searchInput = document.getElementById('promoSearch');
    const tableInfo = document.getElementById('tableInfo');
    const emptyRow = document.getElementById('emptyRow');
    const rows = Array.from(document.querySelectorAll('#promoTable tbody tr[data-status]'));
    let activeFilter = 'all';

    function rupiah(value) {
        return 'Rp ' + Math.round(Number(value) || 0).toLocaleString('id-ID');
    }

    function updatePreview() {
        const productId = productSelect.value;
        const percent = parseFloat(percentInput.value);
        const price = Number(HARGA_PRODUK[productId]);

        if (!productId || !HARGA_PRODUK[productId] || !(percent > 0)) {
            preview.innerHTML = '<span aria-hidden="true">💡</span><span class="preview-muted">Pilih produk dan isi persentase untuk melihat simulasi harga.</span>';
            return;
        }

        const max = parseFloat(maxInput.value);
        const cap = !Number.isNaN(max) && max > 0 ? max : Infinity;
        const cut = Math.min(price * percent / 100, cap);
        const finalPrice = Math.max(price - cut, 0);

        preview.innerHTML = '<span aria-hidden="true">💡</span><span>Harga ' + rupiah(price) +
            ' → <strong>' + rupiah(finalPrice) + '</strong> ' +
            '<span class="preview-muted">(hemat ' + rupiah(cut) + ' / item)</span></span>';
    }

    function resetToAddMode() {
        form.reset();
        formAction.value = 'tambah_diskon';
        formId.value = '';
        modalTitle.textContent = '+ Buat Skema Promosi Baru [UI-07]';
        modalSub.textContent = 'Lengkapi detail promo di bawah ini. Kode promo dibuat otomatis.';
        submitButton.textContent = 'Simpan Program Diskon';
        startInput.min = TODAY_ISO;
        endInput.min = TODAY_ISO;
        startInput.value = TODAY_ISO;
        statusInput.checked = true;
        updatePreview();
    }

    function openPromoModal(id) {
        resetToAddMode();

        if (id !== undefined && id !== null && Object.prototype.hasOwnProperty.call(PROMO_DATA, String(id))) {
            const data = PROMO_DATA[String(id)];

            formAction.value = 'ubah_diskon';
            formId.value = String(data.id_diskon);
            document.getElementById('nama_diskon').value = data.nama_diskon;
            productSelect.value = String(data.id_produk);
            percentInput.value = String(data.presentase_diskon);
            maxInput.value = data.maks_diskon === '' ? '' : String(data.maks_diskon);
            quotaInput.value = data.kuota === '' ? '' : String(data.kuota);
            startInput.value = data.tanggal_mulai;
            endInput.value = data.tanggal_selesai;
            statusInput.checked = Number(data.status_aktif) === 1;

            startInput.removeAttribute('min');
            endInput.removeAttribute('min');
            endInput.min = data.tanggal_mulai;

            modalTitle.textContent = 'Ubah Skema Promosi [UI-08]';
            modalSub.textContent = 'Perbarui data promo ' + data.kode_diskon + '. Perubahan langsung tersimpan setelah tombol simpan ditekan.';
            submitButton.textContent = 'Simpan Perubahan';
            updatePreview();
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        window.setTimeout(function () {
            document.getElementById('nama_diskon').focus();
        }, 60);
    }

    function closePromoModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    }

    function applyFilters() {
        const query = searchInput.value.trim().toLowerCase();
        let visible = 0;

        rows.forEach(function (row) {
            const status = row.dataset.status;
            const matchesFilter = activeFilter === 'all'
                || (activeFilter === 'expired'
                    ? (status === 'expired' || status === 'inactive')
                    : status === activeFilter);
            const matchesSearch = query === '' || row.textContent.toLowerCase().includes(query);
            const show = matchesFilter && matchesSearch;

            row.style.display = show ? '' : 'none';
            if (show) {
                visible += 1;
            }
        });

        tableInfo.textContent = 'Menampilkan ' + visible + ' dari ' + rows.length + ' total konfigurasi diskon';
        emptyRow.style.display = visible === 0 ? '' : 'none';

        document.querySelectorAll('[data-filter]').forEach(function (button) {
            const isActive = button.dataset.filter === activeFilter;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function setStatusFilter(filter, scrollToTable) {
        activeFilter = filter;
        applyFilters();

        if (scrollToTable) {
            document.getElementById('promoSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    productSelect.addEventListener('change', updatePreview);
    percentInput.addEventListener('input', updatePreview);
    maxInput.addEventListener('input', updatePreview);
    searchInput.addEventListener('input', applyFilters);

    startInput.addEventListener('change', function () {
        if (startInput.value) {
            endInput.min = startInput.value;
            if (endInput.value && endInput.value < startInput.value) {
                endInput.value = startInput.value;
            }
        }
    });

    form.addEventListener('submit', function (event) {
        if (startInput.value && endInput.value && endInput.value < startInput.value) {
            event.preventDefault();
            window.alert('Tanggal berakhir tidak boleh lebih awal dari tanggal mulai.');
            endInput.focus();
        }
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closePromoModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closePromoModal();
        }
    });

    window.openPromoModal = openPromoModal;
    window.closePromoModal = closePromoModal;
    window.setStatusFilter = setStatusFilter;

    applyFilters();
})();
</script>

<?php require dirname(__DIR__, 2) . '/layout/footer.php'; ?>
