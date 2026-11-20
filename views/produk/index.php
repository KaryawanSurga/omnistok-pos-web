<?php
/**
 * View: Daftar Produk & Pembaruan Harga [UI-15/16/20]
 * Dipanggil oleh halaman root setelah controller menyiapkan data.
 */
require dirname(__DIR__, 2) . '/layout/header.php';
?>


<!-- TAB NAVIGASI -->
<div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; border-bottom: 1px solid var(--gray-200); padding-bottom: 12px;">
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <a href="daftar_produk.php?tab=katalog" class="btn <?= ($activeTab === 'katalog') ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
            ▤ Katalog &amp; Master Produk [UI-15]
        </a>
        <a href="daftar_produk.php?tab=harga<?= $selectedProdId > 0 ? '&id=' . $selectedProdId : ''; ?>" class="btn <?= ($activeTab === 'harga') ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
            ≚ Pembaruan Harga &amp; Margin [UI-20]
        </a>
    </div>

    <?php if ($activeTab === 'katalog'): ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="openProductModal()">
            + Tambah Produk Baru
        </button>
    <?php endif; ?>
</div>

<?php if ($flash !== null): ?>
    <?php $alertType = in_array($flash['type'], ['success', 'danger', 'info', 'warning'], true) ? $flash['type'] : 'info'; ?>
    <div class="alert alert-<?= e($alertType); ?>" role="alert">
        <span><?= e($flash['message']); ?></span>
        <button type="button" class="alert-close" aria-label="Tutup pesan" onclick="this.parentElement.remove();">&times;</button>
    </div>
<?php endif; ?>

<?php if ($activeTab === 'katalog'): ?>
    <!-- ==========================================
         KATALOG & MASTER DATA PRODUK (UI-15/16)
         ========================================== -->
    <div class="page-title-box">
        <h1 class="page-title">Daftar Produk</h1>
        <p class="page-subtitle">Katalog master produk, pemantauan batas stok rak fisik, dan pengelolaan data produk.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-info">
                <div class="stat-label">Total Produk</div>
                <div class="stat-value"><?= $totalProduk; ?> SKU</div>
                <div class="stat-sub">Master produk aktif<?= $totalArsip > 0 ? ' • ' . $totalArsip . ' nonaktif' : ''; ?></div>
            </div>
            <div class="stat-icon teal" aria-hidden="true">▤</div>
        </div>

        <div class="stat-box">
            <div class="stat-info">
                <div class="stat-label">Kategori Aktif</div>
                <div class="stat-value"><?= count($kategoriList); ?> Grup</div>
                <div class="stat-sub"><?= e(implode(', ', array_keys($kategoriList))); ?></div>
            </div>
            <div class="stat-icon blue" aria-hidden="true">⊞</div>
        </div>

        <div class="stat-box">
            <div class="stat-info">
                <div class="stat-label">Stok Menipis</div>
                <div class="stat-value"><?= $stokMenipis; ?> Produk</div>
                <div class="stat-sub">Stok &le; batas minimum</div>
            </div>
            <div class="stat-icon amber" aria-hidden="true">⚠</div>
        </div>

        <div class="stat-box">
            <div class="stat-info">
                <div class="stat-label">Nilai Persediaan</div>
                <div class="stat-value"><?= formatRupiah($nilaiPersediaan); ?></div>
                <div class="stat-sub">Total stok x harga modal</div>
            </div>
            <div class="stat-icon green" aria-hidden="true">Rp</div>
        </div>
    </div>

    <div class="card-box filter-bar">
        <div class="filter-group" role="group" aria-label="Filter kategori produk">
            <button type="button" class="filter-chip is-active" data-kategori="" data-low="0" onclick="setKategoriFilter('', false)" aria-pressed="true">
                Semua (<?= $totalProduk; ?>)
            </button>
            <button type="button" class="filter-chip" data-kategori="" data-low="1" onclick="setKategoriFilter('', true)" aria-pressed="false">
                Stok Menipis (<?= $stokMenipis; ?>)
            </button>
            <?php if ($totalArsip > 0): ?>
                <button type="button" class="filter-chip" data-arsip="1" onclick="setArsipFilter(true)" aria-pressed="false">
                    Nonaktif (<?= $totalArsip; ?>)
                </button>
            <?php endif; ?>
            <?php foreach ($kategoriList as $kategori => $jumlah): ?>
                <button type="button" class="filter-chip" data-kategori="<?= e($kategori); ?>" data-low="0" onclick="setKategoriFilter(<?= e(json_encode($kategori, $jsonFlags)); ?>, false)" aria-pressed="false">
                    <?= e($kategori); ?> (<?= $jumlah; ?>)
                </button>
            <?php endforeach; ?>
        </div>
        <div class="search-box">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="11" cy="11" r="7"></circle>
                <path d="m20 20-3.5-3.5"></path>
            </svg>
            <input type="search" id="katalogSearch" class="form-control" placeholder="Cari nama produk / kode SKU..." autocomplete="off">
        </div>
    </div>

    <div class="card-box" id="katalogSection">
        <div class="table-wrap">
            <table class="sketch-table table-produk" id="katalogTable">
                <thead>
                    <tr>
                        <th>SKU / Barcode</th>
                        <th>Produk</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produkList as $prod): ?>
                        <?php
                        $stokVal = (int) $prod['jumlah_stok'];
                        $batasVal = (int) $prod['batas_minimum'];
                        $stokClass = $stokVal <= 0 ? 'is-danger' : ($stokVal <= $batasVal ? 'is-warning' : '');
                        ?>
                        <tr data-kategori="<?= e($prod['kategori']); ?>" data-stok="<?= $prod['stok_menipis'] ? 'low' : 'normal'; ?>" data-aktif="<?= $prod['aktif'] ? '1' : '0'; ?>" class="<?= $prod['aktif'] ? '' : 'row-muted'; ?>">
                            <td>
                                <div class="sku-badge"><?= e($prod['kode_tampil']); ?></div>
                                <div class="cell-sub" style="font-family: monospace;">|||| 8991234<?= (int) $prod['id_produk']; ?></div>
                            </td>
                            <td>
                                <div class="cell-title" style="margin-top: 0;">
                                    <?= e($prod['nama_produk']); ?>
                                    <?php if (!$prod['aktif']): ?>
                                        <span class="pill pill-gray" style="margin-left: 6px;">Nonaktif</span>
                                    <?php endif; ?>
                                </div>
                                <div class="cell-desc" title="<?= e($prod['deskripsi']); ?>"><?= e($prod['deskripsi'] !== '' ? $prod['deskripsi'] : 'Tanpa deskripsi'); ?></div>
                            </td>
                            <td><span class="pill pill-teal"><?= e($prod['kategori']); ?></span></td>
                            <td>
                                <strong class="discount-value"><?= formatRupiah((float) $prod['harga_jual']); ?></strong>
                                <div class="cell-sub">Modal <?= formatRupiah((float) $prod['harga_modal']); ?> · Margin <?= formatAngka((float) $prod['margin']); ?>%</div>
                            </td>
                            <td>
                                <strong class="<?= $stokClass === '' ? '' : $stokClass; ?>" style="<?= $stokClass === 'is-danger' ? 'color: var(--danger);' : ($stokClass === 'is-warning' ? 'color: var(--warning);' : 'color: var(--dark);'); ?>">
                                    <?= $stokVal; ?> pcs
                                </strong>
                                <div class="cell-sub">Min. <?= $batasVal; ?> pcs</div>
                            </td>
                            <td style="text-align: center;">
                                <div class="action-group">
                                    <button type="button" class="icon-btn" title="Ubah produk" aria-label="Ubah produk <?= e($prod['nama_produk']); ?>" onclick="openProductModal(<?= (int) $prod['id_produk']; ?>)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M12 20h9"></path>
                                            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                                        </svg>
                                    </button>
                                    <?php if ($prod['aktif']): ?>
                                        <form method="POST" action="daftar_produk.php?tab=katalog" class="inline-form" onsubmit="return confirm('Hapus produk ini? Jika produk memiliki riwayat transaksi, produk akan dinonaktifkan (diarsipkan) agar laporan lama tetap utuh.');">
                                            <?= csrfField(); ?>
                                            <input type="hidden" name="action" value="hapus_produk">
                                            <input type="hidden" name="id_produk" value="<?= (int) $prod['id_produk']; ?>">
                                            <button type="submit" class="icon-btn danger" title="Hapus produk" aria-label="Hapus produk <?= e($prod['nama_produk']); ?>">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M3 6h18"></path>
                                                    <path d="M8 6V4h8v2"></path>
                                                    <path d="m19 6-1 14H6L5 6"></path>
                                                    <path d="M10 11v6"></path>
                                                    <path d="M14 11v6"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="daftar_produk.php?tab=katalog" class="inline-form" onsubmit="return confirm('Aktifkan kembali produk ini agar tampil di katalog dan POS?');">
                                            <?= csrfField(); ?>
                                            <input type="hidden" name="action" value="aktifkan_produk">
                                            <input type="hidden" name="id_produk" value="<?= (int) $prod['id_produk']; ?>">
                                            <button type="submit" class="icon-btn" title="Aktifkan kembali produk" aria-label="Aktifkan kembali produk <?= e($prod['nama_produk']); ?>" style="color: var(--success, #16a34a);">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M3 12a9 9 0 1 0 3-6.7"></path>
                                                    <path d="M3 3v6h6"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <tr id="emptyRow" style="<?= count($produkList) === 0 ? '' : 'display: none;'; ?>">
                        <td colspan="6">
                            <div class="empty-state">
                                <strong>Tidak ada produk yang cocok</strong>
                                <span>Ubah kata kunci/filter atau tambahkan produk baru.</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <div id="tableInfo">Menampilkan <?= $totalProduk; ?> dari <?= $totalProduk; ?> produk aktif</div>
            <div class="muted-note">Produk nonaktif/arsip tidak tampil di katalog aktif maupun POS</div>
        </div>
    </div>

    <!-- MODAL TAMBAH (UI-16) / UBAH PRODUK -->
    <div class="modal-backdrop" id="productModal" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="productModalTitle">
            <div class="modal-head">
                <div>
                    <h2 id="productModalTitle">+ Tambah Produk Baru [UI-16]</h2>
                    <p id="productModalSub">Lengkapi master data produk dan saldo stok awal.</p>
                </div>
                <button type="button" class="icon-btn" onclick="closeProductModal()" aria-label="Tutup dialog">&times;</button>
            </div>

            <form method="POST" action="daftar_produk.php?tab=katalog" id="productForm">
                <?= csrfField(); ?>
                <input type="hidden" name="action" id="productFormAction" value="tambah_produk">
                <input type="hidden" name="id_produk" id="productFormId" value="">

                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="nama_produk">Nama Lengkap Produk <span class="req">*</span></label>
                        <input type="text" id="nama_produk" name="nama_produk" class="form-control" maxlength="150" placeholder="Contoh: Roti Keju Cokelat 150g" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="kategori">Kategori <span class="req">*</span></label>
                        <input type="text" id="kategori" name="kategori" class="form-control" maxlength="50" list="kategoriOptions" placeholder="Contoh: Sembako" required>
                        <datalist id="kategoriOptions">
                            <?php foreach (array_keys($kategoriList) as $kategori): ?>
                                <option value="<?= e($kategori); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="deskripsi">Deskripsi Produk</label>
                        <input type="text" id="deskripsi" name="deskripsi" class="form-control" maxlength="1000" placeholder="Keterangan singkat produk (opsional)">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="harga_modal">Harga Modal (HPP) <span class="req">*</span></label>
                            <div class="input-suffix">
                                <input type="number" id="harga_modal" name="harga_modal" class="form-control" min="0.01" step="any" placeholder="8000" required>
                                <span>Rp</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="harga_jual">Harga Jual <span class="req">*</span></label>
                            <div class="input-suffix">
                                <input type="number" id="harga_jual" name="harga_jual" class="form-control" min="0.01" step="any" placeholder="10000" required>
                                <span>Rp</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="stok" id="stokLabel">Stok Awal Fisik <span class="req">*</span></label>
                            <input type="number" id="stok" name="stok" class="form-control" min="0" step="1" value="25" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="batas_minimum">Batas Minimum Stok</label>
                            <input type="number" id="batas_minimum" name="batas_minimum" class="form-control" min="0" step="1" value="5" required>
                        </div>
                    </div>

                    <div class="promo-preview" id="productPreview" aria-live="polite">
                        <span aria-hidden="true">💡</span>
                        <span class="preview-muted">Isi harga modal dan harga jual untuk melihat margin.</span>
                    </div>
                </div>

                <div class="modal-foot">
                    <button type="button" class="btn btn-secondary" onclick="closeProductModal()">Batal</button>
                    <button type="submit" class="btn btn-primary" id="productSubmit">Simpan Data Produk</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    const PRODUK_DATA = <?= json_encode($produkData, $jsonFlags) ?: '{}'; ?>;

    (function () {
        'use strict';

        const modal = document.getElementById('productModal');
        const form = document.getElementById('productForm');
        const formAction = document.getElementById('productFormAction');
        const formId = document.getElementById('productFormId');
        const modalTitle = document.getElementById('productModalTitle');
        const modalSub = document.getElementById('productModalSub');
        const submitButton = document.getElementById('productSubmit');
        const namaInput = document.getElementById('nama_produk');
        const kategoriInput = document.getElementById('kategori');
        const deskripsiInput = document.getElementById('deskripsi');
        const modalInput = document.getElementById('harga_modal');
        const jualInput = document.getElementById('harga_jual');
        const stokInput = document.getElementById('stok');
        const stokLabel = document.getElementById('stokLabel');
        const batasInput = document.getElementById('batas_minimum');
        const preview = document.getElementById('productPreview');
        const searchInput = document.getElementById('katalogSearch');
        const tableInfo = document.getElementById('tableInfo');
        const emptyRow = document.getElementById('emptyRow');
        const rows = Array.from(document.querySelectorAll('#katalogTable tbody tr[data-kategori]'));
        let activeKategori = '';
        let activeLow = false;
        let activeArsip = false;

        function rupiah(value) {
            return 'Rp ' + Math.round(Number(value) || 0).toLocaleString('id-ID');
        }

        function updatePreview() {
            const modalValue = parseFloat(modalInput.value);
            const jualValue = parseFloat(jualInput.value);

            if (!(modalValue > 0) || !(jualValue > 0)) {
                preview.innerHTML = '<span aria-hidden="true">💡</span><span class="preview-muted">Isi harga modal dan harga jual untuk melihat margin.</span>';
                return;
            }

            if (jualValue < modalValue) {
                preview.innerHTML = '<span aria-hidden="true">⚠️</span><span style="color: var(--danger);">Harga jual lebih rendah dari harga modal. Simpan akan ditolak.</span>';
                return;
            }

            const margin = ((jualValue - modalValue) / jualValue) * 100;
            preview.innerHTML = '<span aria-hidden="true">💡</span><span>Margin <strong>' + margin.toFixed(2) + '%</strong>' +
                '<span class="preview-muted"> · Laba ' + rupiah(jualValue - modalValue) + ' / unit</span></span>';
        }

        function resetToAddMode() {
            form.reset();
            formAction.value = 'tambah_produk';
            formId.value = '';
            modalTitle.textContent = '+ Tambah Produk Baru [UI-16]';
            modalSub.textContent = 'Lengkapi master data produk dan saldo stok awal.';
            submitButton.textContent = 'Simpan Data Produk';
            stokLabel.innerHTML = 'Stok Awal Fisik <span class="req">*</span>';
            stokInput.value = '25';
            batasInput.value = '5';
            updatePreview();
        }

        function openProductModal(id) {
            resetToAddMode();

            if (id !== undefined && id !== null && Object.prototype.hasOwnProperty.call(PRODUK_DATA, String(id))) {
                const data = PRODUK_DATA[String(id)];

                formAction.value = 'ubah_produk';
                formId.value = String(data.id_produk);
                namaInput.value = data.nama_produk;
                kategoriInput.value = data.kategori;
                deskripsiInput.value = data.deskripsi;
                modalInput.value = String(data.harga_modal);
                jualInput.value = String(data.harga_jual);
                stokInput.value = String(data.jumlah_stok);
                batasInput.value = String(data.batas_minimum);

                modalTitle.textContent = 'Ubah Produk ' + data.kode;
                modalSub.textContent = 'Perbarui master data produk. Perubahan stok akan dicatat sebagai mutasi.';
                submitButton.textContent = 'Simpan Perubahan';
                stokLabel.innerHTML = 'Stok Fisik Saat Ini <span class="req">*</span>';
                updatePreview();
            }

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
            window.setTimeout(function () {
                namaInput.focus();
            }, 60);
        }

        function closeProductModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        }

        function applyFilters() {
            const query = searchInput.value.trim().toLowerCase();
            const kategori = activeKategori.toLowerCase();
            let visible = 0;

            rows.forEach(function (row) {
                const matchesKategori = kategori === '' || row.dataset.kategori.toLowerCase() === kategori;
                const matchesLow = !activeLow || row.dataset.stok === 'low';
                const matchesStatus = activeArsip ? row.dataset.aktif === '0' : row.dataset.aktif === '1';
                const matchesSearch = query === '' || row.textContent.toLowerCase().includes(query);
                const show = matchesKategori && matchesLow && matchesStatus && matchesSearch;

                row.style.display = show ? '' : 'none';
                if (show) {
                    visible += 1;
                }
            });

            const totalBaris = rows.filter(function (row) {
                return activeArsip ? row.dataset.aktif === '0' : row.dataset.aktif === '1';
            }).length;

            tableInfo.textContent = 'Menampilkan ' + visible + ' dari ' + totalBaris + ' produk' + (activeArsip ? ' nonaktif' : '');
            emptyRow.style.display = visible === 0 ? '' : 'none';

            document.querySelectorAll('[data-kategori][data-low]').forEach(function (button) {
                const isActive = button.dataset.kategori === activeKategori
                    && (button.dataset.low === '1') === activeLow
                    && !activeArsip;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });

            document.querySelectorAll('[data-arsip]').forEach(function (button) {
                button.classList.toggle('is-active', activeArsip);
                button.setAttribute('aria-pressed', activeArsip ? 'true' : 'false');
            });
        }

        function setKategoriFilter(kategori, low) {
            activeKategori = kategori || '';
            activeLow = Boolean(low);
            activeArsip = false;
            applyFilters();
        }

        function setArsipFilter(arsip) {
            activeKategori = '';
            activeLow = false;
            activeArsip = Boolean(arsip);
            applyFilters();
        }

        modalInput.addEventListener('input', updatePreview);
        jualInput.addEventListener('input', updatePreview);
        searchInput.addEventListener('input', applyFilters);

        form.addEventListener('submit', function (event) {
            const modalValue = parseFloat(modalInput.value);
            const jualValue = parseFloat(jualInput.value);
            if (modalValue > 0 && jualValue > 0 && jualValue < modalValue) {
                event.preventDefault();
                window.alert('Harga jual tidak boleh lebih rendah dari harga modal.');
                jualInput.focus();
            }
        });

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeProductModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                closeProductModal();
            }
        });

        window.openProductModal = openProductModal;
        window.closeProductModal = closeProductModal;
        window.setKategoriFilter = setKategoriFilter;
        window.setArsipFilter = setArsipFilter;

        applyFilters();
    })();
    </script>

<?php else: ?>
    <!-- ==========================================
         PEMBARUAN HARGA & MARGIN (UI-20)
         ========================================== -->
    <div class="page-title-box">
        <h1 class="page-title">Pembaruan Harga Modal &amp; Jual</h1>
        <p class="page-subtitle">Penyesuaian struktur harga beli distributor dan harga jual konsumen dengan rekam jejak audit log.</p>
    </div>

    <div class="stats-grid stats-3">
        <div class="stat-box">
            <div class="stat-info">
                <div class="stat-label">Rata-Rata Margin Toko</div>
                <div class="stat-value"><?= formatAngka($rataMargin); ?>%</div>
                <div class="stat-sub">Dihitung dari <?= $produkMargin; ?> produk</div>
            </div>
            <span class="pill pill-green">Sehat</span>
        </div>

        <div class="stat-box">
            <div class="stat-info">
                <div class="stat-label">Produk Dipantau</div>
                <div class="stat-value"><?= $totalProduk; ?> SKU</div>
                <div class="stat-sub">Master produk aktif</div>
            </div>
            <span class="pill pill-teal">Terdokumentasi</span>
        </div>

        <div class="stat-box">
            <div class="stat-info">
                <div class="stat-label">Minimum Margin Alert</div>
                <div class="stat-value"><?= $marginRendah; ?> SKU</div>
                <div class="stat-sub">Margin jual &lt; 8%</div>
            </div>
            <span class="pill pill-orange">Periksa</span>
        </div>
    </div>

    <?php if ($selectedProd === null): ?>
        <div class="card-box">
            <div class="empty-state">
                <strong>Belum ada produk</strong>
                <span>Tambahkan produk terlebih dahulu melalui tab Katalog &amp; Master Produk.</span>
            </div>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: 1.15fr 1fr; gap: 24px;">
            <div class="card-box">
                <div class="card-box-header">
                    <h3 class="card-box-title">Form Perubahan Harga</h3>
                    <span class="pill pill-teal">UI-20</span>
                </div>

                <form method="POST" action="daftar_produk.php?tab=harga&id=<?= $selectedProdId; ?>">
                    <?= csrfField(); ?>
                    <input type="hidden" name="action" value="update_harga">
                    <input type="hidden" name="id_produk" value="<?= $selectedProdId; ?>">

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="prod_select" class="form-label">Cari Produk / SKU</label>
                        <select id="prod_select" class="form-control" onchange="window.location.href='daftar_produk.php?tab=harga&id=' + this.value">
                            <?php foreach ($produkList as $p): ?>
                                <option value="<?= (int) $p['id_produk']; ?>" <?= ($selectedProdId === (int) $p['id_produk']) ? 'selected' : ''; ?>>
                                    <?= e($p['kode_tampil']); ?> - <?= e($p['nama_produk']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="background: #f1f5f9; padding: 14px 18px; border-radius: 6px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 18px; border: 1px solid var(--gray-200);">
                        <div>
                            <span style="font-size: 0.74rem; color: var(--gray-500); display: block;">Harga Modal Saat Ini</span>
                            <strong style="font-size: 1.1rem; color: var(--dark);"><?= formatRupiah((float) $selectedProd['harga_modal']); ?></strong>
                        </div>
                        <div>
                            <span style="font-size: 0.74rem; color: var(--gray-500); display: block;">Harga Jual Saat Ini</span>
                            <strong style="font-size: 1.1rem; color: var(--dark);"><?= formatRupiah((float) $selectedProd['harga_jual']); ?></strong>
                        </div>
                        <div>
                            <span style="font-size: 0.74rem; color: var(--gray-500); display: block;">Margin Saat Ini</span>
                            <strong style="font-size: 1.1rem; color: var(--primary);"><?= formatAngka($currMargin); ?>%</strong>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group" style="flex: 1;">
                            <label for="modal_baru" class="form-label">Harga Modal Baru (HPP) (Rp)</label>
                            <input type="number" id="modal_baru" name="harga_modal_baru" class="form-control" min="0.01" step="any" value="<?= e($selectedProd['harga_modal']); ?>" required oninput="hitungLiveMargin()">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="jual_baru" class="form-label">Harga Jual Baru (Rp)</label>
                            <input type="number" id="jual_baru" name="harga_jual_baru" class="form-control" min="0.01" step="any" value="<?= e($selectedProd['harga_jual']); ?>" required oninput="hitungLiveMargin()">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="tgl_efektif" class="form-label">Tanggal Efektif Berlaku</label>
                        <input type="date" id="tgl_efektif" name="tgl_efektif" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 18px;">
                        <label for="alasan" class="form-label">Alasan Perubahan Harga</label>
                        <input type="text" id="alasan" name="alasan" class="form-control" maxlength="200" placeholder="Contoh: Kenaikan harga faktur distributor" required>
                    </div>

                    <div id="liveValidasiBox" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; font-size: 0.86rem; color: #166534;">
                        <span id="liveValidasiText">✓ Validasi Lolos: Margin saat ini <?= formatAngka($currMargin); ?>%</span>
                    </div>

                    <div class="action-bar">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='daftar_produk.php?tab=katalog'">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan Harga</button>
                    </div>
                </form>
            </div>

            <div class="card-box">
                <div class="card-box-header">
                    <h3 class="card-box-title">Riwayat Fluktuasi Harga (Audit)</h3>
                    <span class="pill pill-teal">Jejak Log</span>
                </div>
                <p style="font-size: 0.82rem; color: var(--gray-500); margin-bottom: 12px;">
                    Catatan historis penyesuaian harga khusus <strong><?= e($selectedProd['kode_tampil']); ?></strong>.
                </p>

                <div class="table-wrap">
                    <table class="sketch-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Modal</th>
                                <th>Jual</th>
                                <th>Margin</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($riwayat === []): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state" style="padding: 28px 12px;">
                                            <strong>Belum ada riwayat perubahan harga</strong>
                                            <span>Riwayat akan muncul setelah harga produk diubah.</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($riwayat as $r): ?>
                                    <?php $mrg = hitungMargin((float) $r['harga_jual_lama'], (float) $r['harga_modal_lama']); ?>
                                    <tr>
                                        <td><?= e(formatTanggalIndo((string) $r['tanggal_perubahan'])); ?></td>
                                        <td><?= formatRupiah((float) $r['harga_modal_lama']); ?></td>
                                        <td><strong><?= formatRupiah((float) $r['harga_jual_lama']); ?></strong></td>
                                        <td style="color: var(--primary); font-weight: bold;"><?= formatAngka($mrg); ?>%</td>
                                        <td>Audit penyesuaian harga</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
function hitungLiveMargin() {
    const modal = parseFloat(document.getElementById('modal_baru').value) || 0;
    const jual = parseFloat(document.getElementById('jual_baru').value) || 0;
    const box = document.getElementById('liveValidasiBox');
    const txt = document.getElementById('liveValidasiText');

    if (!box || !txt) return;

    if (jual <= 0 || modal <= 0) {
        box.style.background = '#fef2f2';
        box.style.borderColor = '#fca5a5';
        txt.style.color = '#991b1b';
        txt.innerHTML = '⚠️ Nominal harga modal dan harga jual harus lebih dari Rp 0!';
    } else if (jual < modal) {
        box.style.background = '#fef2f2';
        box.style.borderColor = '#fca5a5';
        txt.style.color = '#991b1b';
        txt.innerHTML = '⚠️ PERINGATAN RUGI: Harga jual lebih rendah dari harga modal!';
    } else {
        const laba = jual - modal;
        const margin = ((laba / jual) * 100).toFixed(2);
        box.style.background = '#f0fdf4';
        box.style.borderColor = '#bbf7d0';
        txt.style.color = '#166534';
        txt.innerHTML = '✓ Validasi Lolos: Margin Baru ' + margin + '% (Laba: Rp ' + laba.toLocaleString('id-ID') + ' / unit >= Modal)';
    }
}
</script>

<?php require dirname(__DIR__, 2) . '/layout/footer.php'; ?>
