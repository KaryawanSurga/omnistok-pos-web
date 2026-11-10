<?php
/**
 * View Antarmuka: Peringatan Stok Menipis [UI-09] & Peringatan Barang Kadaluwarsa [UI-10]
 * Penulis: Fauzi Al Wahid (FW - 255150400111016)
 * Referensi UC: UC-05 & UC-06
 * Referensi UI SDD: UI-09 & UI-10
 * Hak Akses: Pemilik Toko (owner) & Petugas Stok (gudang)
 */

require_once __DIR__ . '/config/auth.php';
auth_require_page(basename(__FILE__));

require_once __DIR__ . '/controllers/PeringatanStokController.php';

$controller = new PeringatanStokController();
$stokMenipisData = $controller->get_peringatan_stok_menipis();
$kadaluwarsaData = $controller->get_peringatan_kadaluwarsa();

$activeTab = isset($_GET['tab']) && $_GET['tab'] === 'kadaluwarsa' ? 'kadaluwarsa' : 'menipis';

$activePage = 'peringatan_stok';
$pageTitle = 'Peringatan Stok & Kadaluwarsa';
$moduleBreadcrumb = 'INVENTORY & REPORTS / NOTIFIKASI PERINGATAN DINI';
$currentUser = auth_user();
$userName = !empty($currentUser['nama_lengkap']) ? $currentUser['nama_lengkap'] : 'Fauzi Al Wahid';
$userRole = auth_role_labels()[auth_role_key()] ?? 'Pemilik Toko (FW)';
$userInitials = 'FW';

require_once __DIR__ . '/layout/header.php';
?>

<div class="page-title-box">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 class="page-title">Pusat Peringatan Dini Persediaan </h1>
            <p class="page-subtitle">Monitoring otomatis stok menipis di bawah ambang batas dan kedaluwarsa batch produk.</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="penerimaan_barang.php" class="btn btn-primary" style="font-size: 0.85rem;">
                + Tambah Stok
            </a>
            <a href="penyesuaian_stok.php" class="btn btn-secondary" style="font-size: 0.85rem;">
                ≚ Penyesuaian Stok
            </a>
        </div>
    </div>
</div>

<!-- NAVIGASI TAB MODUL FW -->
<div style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
    <a href="peringatan_stok.php?tab=menipis" 
       class="btn <?= ($activeTab === 'menipis') ? 'btn-primary' : 'btn-outline'; ?>"
       style="border-radius: 20px; padding: 6px 18px; font-weight: 600;">
        Peringatan Stok Menipis
        <span class="pill <?= ($stokMenipisData['total_peringatan'] > 0) ? 'pill-red' : 'pill-green'; ?>" style="margin-left: 6px;">
            <?= $stokMenipisData['total_peringatan']; ?>
        </span>
    </a>
    <a href="peringatan_stok.php?tab=kadaluwarsa" 
       class="btn <?= ($activeTab === 'kadaluwarsa') ? 'btn-primary' : 'btn-outline'; ?>"
       style="border-radius: 20px; padding: 6px 18px; font-weight: 600;">
        Peringatan Barang Kadaluwarsa
        <span class="pill <?= ($kadaluwarsaData['total_expired'] > 0) ? 'pill-red' : 'pill-orange'; ?>" style="margin-left: 6px;">
            <?= $kadaluwarsaData['total_expired'] + $kadaluwarsaData['total_warning']; ?>
        </span>
    </a>
</div>

<?php if ($activeTab === 'menipis'): ?>
    <!-- ========================================== -->
    <!-- TAB 1: PERINGATAN STOK MENIPIS [UI-09]     -->
    <!-- ========================================== -->

    <!-- METRIK KARTU STOK MENIPIS -->
    <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 22px;">
        <div class="stat-box" style="border-top: 3px solid var(--danger);">
            <div class="stat-info">
                <div class="stat-label">TOTAL PRODUK KRITIS</div>
                <div class="stat-value" style="color: var(--danger);"><?= $stokMenipisData['total_peringatan']; ?> SKU</div>
                <div class="stat-sub">Stok &le; Batas Minimum Toko</div>
            </div>
            <span class="pill pill-red">Perlu Restock</span>
        </div>

        <div class="stat-box" style="border-top: 3px solid #7f1d1d;">
            <div class="stat-info">
                <div class="stat-label">STOK HABIS (0 UNIT)</div>
                <div class="stat-value" style="color: #b91c1c;"><?= $stokMenipisData['total_habis']; ?> SKU</div>
                <div class="stat-sub">Kuantitas Fisik = 0</div>
            </div>
            <span class="pill pill-red">Habis</span>
        </div>

        <div class="stat-box" style="border-top: 3px solid var(--warning);">
            <div class="stat-info">
                <div class="stat-label">MENDEKATI MINIMUM</div>
                <div class="stat-value" style="color: var(--warning);"><?= $stokMenipisData['total_menipis']; ?> SKU</div>
                <div class="stat-sub">Segera Lakukan Pengadaan</div>
            </div>
            <span class="pill pill-orange">Menipis</span>
        </div>
    </div>

    <!-- TABEL DAFTAR STOK MENIPIS PERSIS UI-09 -->
    <div class="card-box">
        <div class="card-box-header">
            <div>
                <h3 class="card-box-title">Daftar Produk Di Bawah Batas Ambang Minimum [UI-09]</h3>
                <span style="font-size: 0.78rem; color: var(--gray-400);">
                    Daftar SKU yang memerlukan restock ulang atau konfirmasi opname fisik.
                </span>
            </div>
            <span class="pill pill-teal"><?= $stokMenipisData['total_peringatan']; ?> Produk Teridentifikasi</span>
        </div>

        <div class="table-wrap">
            <table class="sketch-table">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>NAMA PRODUK</th>
                        <th>KATEGORI</th>
                        <th style="text-align: right;">HARGA JUAL</th>
                        <th style="text-align: center;">SISA STOK</th>
                        <th style="text-align: center;">BATAS MINIMUM</th>
                        <th style="text-align: center;">DEFISIT</th>
                        <th>STATUS INDIKATOR</th>
                        <th style="text-align: center;">AKSI REKOMENDASI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stokMenipisData['items'])): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: var(--success); padding: 30px;">
                                <strong>Seluruh Stok Aman</strong> - Tidak ada produk dengan jumlah stok di bawah batas minimum saat ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($stokMenipisData['items'] as $item): ?>
                            <?php $defisit = max(0, $item['batas_minimum'] - $item['total_stok']); ?>
                            <tr>
                                <td class="sku-badge">PRD-<?= str_pad($item['id_produk'], 3, '0', STR_PAD_LEFT); ?></td>
                                <td><strong><?= htmlspecialchars($item['nama_produk']); ?></strong></td>
                                <td><?= htmlspecialchars($item['kategori']); ?></td>
                                <td style="text-align: right;">Rp <?= number_format($item['harga_jual'], 0, ',', '.'); ?></td>
                                <td style="text-align: center;">
                                    <strong style="color: <?= ($item['total_stok'] == 0) ? '#b91c1c' : '#c2410c'; ?>;">
                                        <?= $item['total_stok']; ?> unit
                                    </strong>
                                </td>
                                <td style="text-align: center;"><?= $item['batas_minimum']; ?> unit</td>
                                <td style="text-align: center; color: var(--danger); font-weight: bold;">
                                    -<?= $defisit; ?> unit
                                </td>
                                <td>
                                    <?php if ($item['status_stok'] === 'HABIS'): ?>
                                        <span class="pill pill-red">Habis (0)</span>
                                    <?php else: ?>
                                        <span class="pill pill-orange">Menipis</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <a href="penerimaan_barang.php" class="btn btn-primary btn-sm" style="font-size: 0.76rem; padding: 4px 10px;">
                                        + Restock PO
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="padding: 12px 16px; background: #f8fafc; border-top: 1px solid #e2e8f0; font-size: 0.76rem; color: var(--gray-500); display: flex; justify-content: space-between; align-items: center;">
            <span>Ambang batas minimum dapat disesuaikan pada modul Master Produk.</span>
            <span>Hak Akses: Pemilik Toko &amp; Petugas Stok</span>
        </div>
    </div>

<?php else: ?>
    <!-- ========================================== -->
    <!-- TAB 2: PERINGATAN KADALUWARSA [UI-10]      -->
    <!-- ========================================== -->

    <!-- METRIK KARTU KADALUWARSA -->
    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 22px;">
        <div class="stat-box" style="border-top: 3px solid var(--danger);">
            <div class="stat-info">
                <div class="stat-label">SUDAH KEDALUWARSA</div>
                <div class="stat-value" style="color: var(--danger);"><?= $kadaluwarsaData['total_expired']; ?> Batch</div>
                <div class="stat-sub">Tarik dari Rak Penjualan</div>
            </div>
            <span class="pill pill-red">Karantina</span>
        </div>

        <div class="stat-box" style="border-top: 3px solid var(--warning);">
            <div class="stat-info">
                <div class="stat-label">MENDEKATI EXP (&le; 90 HARI)</div>
                <div class="stat-value" style="color: var(--warning);"><?= $kadaluwarsaData['total_warning']; ?> Batch</div>
                <div class="stat-sub">Pertimbangkan Diskon Promo</div>
            </div>
            <span class="pill pill-orange">Waspada</span>
        </div>

        <div class="stat-box" style="border-top: 3px solid var(--success);">
            <div class="stat-info">
                <div class="stat-label">BATCH AMAN (&gt; 90 HARI)</div>
                <div class="stat-value" style="color: var(--success);"><?= $kadaluwarsaData['total_safe']; ?> Batch</div>
                <div class="stat-sub">Masa Simpan Masih Panjang</div>
            </div>
            <span class="pill pill-green">Aman</span>
        </div>

        <div class="stat-box">
            <div class="stat-info">
                <div class="stat-label">TOTAL BATCH TERDATA</div>
                <div class="stat-value"><?= $kadaluwarsaData['total_batch']; ?> Batch</div>
                <div class="stat-sub">Pengawasan Mutu Gudang</div>
            </div>
            <span class="pill pill-teal">Total Batch</span>
        </div>
    </div>

    <!-- TABEL DAFTAR KADALUWARSA PERSIS UI-10 -->
    <div class="card-box">
        <div class="card-box-header">
            <div>
                <h3 class="card-box-title">Daftar Pengawasan Kedaluwarsa Batch Produk</h3>
                <span style="font-size: 0.78rem; color: var(--gray-400);">
                    Pemantauan tanggal kedaluwarsa per batch produk untuk mencegah kerugian dan menjaga kualitas produk.
                </span>
            </div>
            <span class="pill pill-teal"><?= $kadaluwarsaData['total_batch']; ?> Batch Terdaftar</span>
        </div>

        <div class="table-wrap">
            <table class="sketch-table">
                <thead>
                    <tr>
                        <th>ID BATCH</th>
                        <th>NAMA PRODUK</th>
                        <th>KATEGORI</th>
                        <th>LOKASI GUDANG</th>
                        <th style="text-align: center;">TANGGAL KADALUWARSA</th>
                        <th style="text-align: center;">SISA STOK</th>
                        <th>STATUS &amp; HITUNG MUNDUR</th>
                        <th style="text-align: center;">AKSI REKOMENDASI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($kadaluwarsaData['items'])): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--gray-400); padding: 30px;">
                                Belum ada data batch produk yang terdaftar dalam sistem.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($kadaluwarsaData['items'] as $b): ?>
                            <tr>
                                <td><strong>#BCH-<?= $b['id_batch']; ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($b['nama_produk']); ?></strong>
                                    <div style="font-size: 0.72rem; color: var(--gray-400);">PRD-<?= str_pad($b['id_produk'], 3, '0', STR_PAD_LEFT); ?></div>
                                </td>
                                <td><?= htmlspecialchars($b['kategori']); ?></td>
                                <td><?= htmlspecialchars($b['lokasi_stok']); ?></td>
                                <td style="text-align: center; font-weight: 600;">
                                    <?= date('d/m/Y', strtotime($b['tanggal_kadaluwarsa'])); ?>
                                </td>
                                <td style="text-align: center;">
                                    <strong><?= $b['sisa_stok']; ?> unit</strong>
                                </td>
                                <td>
                                    <span class="pill <?= $b['pill_class']; ?>">
                                        <?= $b['label_exp']; ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($b['status_exp'] === 'KADALUWARSA' || $b['status_exp'] === 'HARI_INI'): ?>
                                        <a href="retur_barang.php" class="btn btn-secondary btn-sm" style="font-size: 0.74rem; padding: 4px 8px; color: #b91c1c;">
                                            ↩ Retur / Karantina
                                        </a>
                                    <?php elseif ($b['status_exp'] === 'MENDEKATI'): ?>
                                        <a href="kelola_diskon.php" class="btn btn-outline btn-sm" style="font-size: 0.74rem; padding: 4px 8px;">
                                            % Promo Diskon
                                        </a>
                                    <?php else: ?>
                                        <span style="font-size: 0.78rem; color: var(--success); font-weight: 600;">Siap Jual</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="padding: 12px 16px; background: #f8fafc; border-top: 1px solid #e2e8f0; font-size: 0.76rem; color: var(--gray-500); display: flex; justify-content: space-between; align-items: center;">
            <span>Sistem memberikan peringatan dini otomatis ketika masa simpan batch &le; 90 hari.</span>
            <span>Hak Akses: Pemilik Toko &amp; Petugas Stok</span>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
