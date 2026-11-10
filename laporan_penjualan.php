<?php
/**
 * View Antarmuka: Laporan Penjualan & Laba Kotor [UI-05]
 * Penulis: Fauzi Al Wahid (FW - 255150400111016)
 * Referensi UI SDD: UI-05 (Melihat Laporan Penjualan dan Laba)
 * Disinkronkan 100% dengan Sketsa UI Modul 2
 */

require_once __DIR__ . '/config/auth.php';
auth_require_page(basename(__FILE__));

require_once __DIR__ . '/controllers/LaporanPenjualanController.php';

$controller = new LaporanPenjualanController();
$defaultPeriode = $controller->buka_laporan_penjualan();

// Default 30 hari agar seluruh transaksi riil awal otomatis terlihat saat pertama dibuka
$tgl_mulai = isset($_GET['tgl_mulai']) ? trim($_GET['tgl_mulai']) : date('Y-m-d', strtotime('-30 days'));
$tgl_akhir = isset($_GET['tgl_akhir']) ? trim($_GET['tgl_akhir']) : date('Y-m-d');

// Fitur Ekspor ke PDF (1 tombol terintegrasi di filter form)
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $validasi = $controller->validasi_periode($tgl_mulai, $tgl_akhir);
    if ($validasi['valid']) {
        $controller->unduh_pdf($tgl_mulai, $tgl_akhir);
        exit;
    }
}

// Ambil data laporan
$laporanData = $controller->get_laporan_penjualan($tgl_mulai, $tgl_akhir);
$isValid = $laporanData['status'];
$pesanPeringatan = $laporanData['message'];

$ringkasan = $laporanData['ringkasan'];
$trenHarian = $laporanData['tren_harian'];
$detailProduk = $laporanData['detail_produk'];

$activePage = 'laporan_penjualan';
$pageTitle = 'Laporan Penjualan & Laba';
$moduleBreadcrumb = 'REPORTS / SALES & PROFIT ANALYTICS';
$userName = 'Fauzi Al Wahid';
$userRole = 'Pemilik Toko (FW)';
$userInitials = 'FW';

require_once __DIR__ . '/layout/header.php';
?>

<style>
@media print {
    .app-sidebar, .app-header, .card-box form, .btn, .no-print {
        display: none !important;
    }
    .app-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    .card-box {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
    }
}
</style>

<div class="page-title-box">
    <h1 class="page-title">Laporan Penjualan &amp; Laba</h1>
    <p class="page-subtitle">Pantau penjualan bersih, HPP historis, serta laba kotor toko.</p>
</div>

<!-- PESAN PERINGATAN VALIDASI PERIODE -->
<?php if (!$isValid): ?>
    <div class="alert alert-danger" style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
        <span style="font-size: 1.1rem; font-weight: bold; background: #fee2e2; border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; color: #b91c1c;">!</span>
        <div>
            <strong>Periode Tanggal Tidak Valid!</strong>
            <div style="font-size: 0.88rem; margin-top: 2px;"><?= htmlspecialchars($pesanPeringatan); ?></div>
        </div>
    </div>
<?php elseif (empty($detailProduk)): ?>
    <div class="alert alert-warning" style="background: #fffbeb; border: 1px solid #fef3c7; color: #92400e; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
        <span style="font-size: 1.1rem; font-weight: bold; background: #fef3c7; border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; color: #b45309;">i</span>
        <div>
            <strong>Tidak Ada Data Transaksi</strong>
            <div style="font-size: 0.88rem; margin-top: 2px;">Tidak ditemukan data penjualan pada rentang <?= htmlspecialchars($tgl_mulai); ?> s/d <?= htmlspecialchars($tgl_akhir); ?>. Silakan pilih rentang tanggal lain.</div>
        </div>
    </div>
<?php endif; ?>

<!-- FILTER PERIODE FORM PERSIS UI-05 DENGAN 1 TOMBOL UNDUH PDF -->
<div class="card-box" style="padding: 16px 20px; margin-bottom: 22px;">
    <form method="GET" action="laporan_penjualan.php" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
        <div class="form-group" style="flex: 1; min-width: 170px;">
            <label for="tgl_mulai" class="form-label">Tanggal mulai</label>
            <input type="date" id="tgl_mulai" name="tgl_mulai" class="form-control" value="<?= htmlspecialchars($tgl_mulai); ?>" required>
        </div>
        <div class="form-group" style="flex: 1; min-width: 170px;">
            <label for="tgl_akhir" class="form-label">Tanggal akhir</label>
            <input type="date" id="tgl_akhir" name="tgl_akhir" class="form-control" value="<?= htmlspecialchars($tgl_akhir); ?>" required>
        </div>
        <button type="submit" class="btn btn-primary" style="height: 40px; padding: 0 20px;">
            <span style="font-size: 0.9rem;">Y</span> Tampilkan
        </button>
        <a href="laporan_penjualan.php?tgl_mulai=<?= urlencode($tgl_mulai); ?>&tgl_akhir=<?= urlencode($tgl_akhir); ?>&export=pdf" class="btn btn-secondary" style="height: 40px; display: inline-flex; align-items: center; gap: 6px;">
            Unduh PDF
        </a>
    </form>
</div>

<!-- 4 SUMMARY CARDS PERSIS UI-05 -->
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 24px;">
    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">PENJUALAN BERSIH</div>
            <div class="stat-value">Rp <?= number_format($ringkasan['penjualan_bersih'], 0, ',', '.'); ?></div>
            <div class="stat-sub">• Setelah diskon</div>
        </div>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">TOTAL HPP</div>
            <div class="stat-value">Rp <?= number_format($ringkasan['total_hpp'], 0, ',', '.'); ?></div>
            <div class="stat-sub">• Biaya perolehan historis</div>
        </div>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">LABA KOTOR</div>
            <div class="stat-value" style="color: var(--primary);">Rp <?= number_format($ringkasan['laba_kotor'], 0, ',', '.'); ?></div>
            <div class="stat-sub">• Penjualan dikurangi HPP</div>
        </div>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">MARGIN LABA KOTOR</div>
            <div class="stat-value" style="color: #0284c7;"><?= number_format($ringkasan['margin'], 2, ',', '.'); ?>%</div>
            <div class="stat-sub">• Laba kotor / penjualan</div>
        </div>
    </div>
</div>

<!-- TREND CHART & SUMMARY SIDE BY SIDE PERSIS UI-05 -->
<div style="display: grid; grid-template-columns: 2.2fr 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- LEFT: SVG LINE GRAPH -->
    <div class="card-box" style="margin-bottom: 0;">
        <div class="card-box-header" style="border-bottom: none; margin-bottom: 10px;">
            <div>
                <h3 class="card-box-title">Tren Penjualan &amp; Laba Kotor</h3>
                <span style="font-size: 0.74rem; color: var(--gray-400);">
                    <?= date('d M Y', strtotime($tgl_mulai)); ?> s/d <?= date('d M Y', strtotime($tgl_akhir)); ?> • Dalam ribuan rupiah
                </span>
            </div>
            <div style="display: flex; gap: 14px; font-size: 0.78rem;">
                <span style="display: flex; align-items: center; gap: 6px;">
                    <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #0f766e;"></span> Penjualan
                </span>
                <span style="display: flex; align-items: center; gap: 6px;">
                    <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #0284c7;"></span> Laba kotor
                </span>
            </div>
        </div>

        <?php
        // Perhitungan titik koordinat SVG Tren
        $chartPoints = $trenHarian;
        $maxVal = 1000;
        foreach ($chartPoints as $p) {
            if ($p['penjualan'] > $maxVal) $maxVal = $p['penjualan'];
            if ($p['laba'] > $maxVal) $maxVal = $p['laba'];
        }
        $yMax = ceil($maxVal / 100000) * 100000;
        if ($yMax < 50000) $yMax = 100000;

        $pointCount = count($chartPoints);
        $xStart = 60;
        $xEnd = 670;
        $yBase = 210;
        $yTop = 30;
        $plotH = $yBase - $yTop;

        $penjualanPoints = [];
        $labaPoints = [];
        $svgPointsMeta = [];

        for ($i = 0; $i < $pointCount; $i++) {
            $px = ($pointCount > 1) 
                ? $xStart + ($i * (($xEnd - $xStart) / ($pointCount - 1)))
                : ($xStart + $xEnd) / 2;

            $pyPenjualan = $yBase - (($chartPoints[$i]['penjualan'] / $yMax) * $plotH);
            $pyLaba      = $yBase - (($chartPoints[$i]['laba'] / $yMax) * $plotH);

            $penjualanPoints[] = round($px, 1) . ',' . round($pyPenjualan, 1);
            $labaPoints[]      = round($px, 1) . ',' . round($pyLaba, 1);

            $svgPointsMeta[] = [
                'x' => round($px, 1),
                'yP' => round($pyPenjualan, 1),
                'yL' => round($pyLaba, 1),
                'label' => $chartPoints[$i]['label'],
                'valP' => $chartPoints[$i]['penjualan'],
                'valL' => $chartPoints[$i]['laba'],
            ];
        }
        $pointsPenjualanStr = implode(' ', $penjualanPoints);
        $pointsLabaStr      = implode(' ', $labaPoints);
        ?>

        <!-- AUTHENTIC SVG LINE CHART MATCHING WIREFRAME -->
        <div style="width: 100%; height: 260px; position: relative;">
            <svg viewBox="0 0 700 245" style="width: 100%; height: 100%; overflow: visible;">
                <!-- Grid Lines -->
                <line x1="50" y1="30" x2="680" y2="30" stroke="#f1f5f9" stroke-width="1" />
                <line x1="50" y1="75" x2="680" y2="75" stroke="#f1f5f9" stroke-width="1" />
                <line x1="50" y1="120" x2="680" y2="120" stroke="#f1f5f9" stroke-width="1" />
                <line x1="50" y1="165" x2="680" y2="165" stroke="#f1f5f9" stroke-width="1" />
                <line x1="50" y1="210" x2="680" y2="210" stroke="#cbd5e1" stroke-width="1.5" />

                <!-- Y Axis Labels -->
                <text x="45" y="34" font-size="10" fill="#94a3b8" text-anchor="end"><?= round($yMax / 1000); ?></text>
                <text x="45" y="79" font-size="10" fill="#94a3b8" text-anchor="end"><?= round(($yMax * 0.75) / 1000); ?></text>
                <text x="45" y="124" font-size="10" fill="#94a3b8" text-anchor="end"><?= round(($yMax * 0.50) / 1000); ?></text>
                <text x="45" y="169" font-size="10" fill="#94a3b8" text-anchor="end"><?= round(($yMax * 0.25) / 1000); ?></text>
                <text x="45" y="214" font-size="10" fill="#94a3b8" text-anchor="end">0</text>

                <!-- X Axis Labels (Dates) -->
                <?php 
                $labelStep = ($pointCount > 8) ? ceil($pointCount / 7) : 1;
                foreach ($svgPointsMeta as $idx => $pt): 
                    if ($idx % $labelStep === 0 || $idx === $pointCount - 1):
                ?>
                    <text x="<?= $pt['x']; ?>" y="228" font-size="10" fill="#64748b" text-anchor="middle"><?= $pt['label']; ?></text>
                <?php 
                    endif;
                endforeach; 
                ?>

                <?php if ($pointCount > 0): ?>
                    <!-- Penjualan Line (Teal #0f766e) -->
                    <polyline fill="none" stroke="#0f766e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                        points="<?= $pointsPenjualanStr; ?>" />
                    <?php foreach ($svgPointsMeta as $pt): ?>
                        <circle cx="<?= $pt['x']; ?>" cy="<?= $pt['yP']; ?>" r="4" fill="#0f766e">
                            <title><?= $pt['label']; ?>: Penjualan Rp <?= number_format($pt['valP'], 0, ',', '.'); ?></title>
                        </circle>
                    <?php endforeach; ?>

                    <!-- Laba Kotor Line (Blue #0284c7) -->
                    <polyline fill="none" stroke="#0284c7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                        points="<?= $pointsLabaStr; ?>" />
                    <?php foreach ($svgPointsMeta as $pt): ?>
                        <circle cx="<?= $pt['x']; ?>" cy="<?= $pt['yL']; ?>" r="3.5" fill="#0284c7">
                            <title><?= $pt['label']; ?>: Laba Rp <?= number_format($pt['valL'], 0, ',', '.'); ?></title>
                        </circle>
                    <?php endforeach; ?>
                <?php endif; ?>
            </svg>
        </div>
    </div>

    <!-- RIGHT: RINGKASAN PERIODE -->
    <div class="card-box" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
        <div>
            <div class="card-box-header">
                <h3 class="card-box-title">Ringkasan Periode</h3>
                <span class="pill pill-teal"><?= $ringkasan['jumlah_hari']; ?> Hari</span>
            </div>

            <div style="margin-bottom: 18px;">
                <div style="font-size: 1.8rem; font-weight: 800; color: var(--dark);"><?= $ringkasan['total_qty']; ?> unit</div>
                <div style="font-size: 0.76rem; color: var(--gray-400);">Jumlah produk terjual</div>
            </div>

            <div style="margin-bottom: 18px;">
                <div style="font-size: 1.8rem; font-weight: 800; color: var(--dark);"><?= $ringkasan['total_sku']; ?> SKU</div>
                <div style="font-size: 0.76rem; color: var(--gray-400);">Produk yang terjual</div>
            </div>

            <div style="margin-bottom: 18px;">
                <div style="font-size: 1.8rem; font-weight: 800; color: var(--dark);"><?= $ringkasan['jumlah_hari']; ?> hari</div>
                <div style="font-size: 0.76rem; color: var(--gray-400);">Periode laporan</div>
            </div>
        </div>

        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 10px 14px; font-size: 0.74rem; color: #166534; display: flex; align-items: center; gap: 8px;">
            <span>✓</span> Data tersinkron otomatis dari POS
        </div>
    </div>
</div>

<!-- RINCIAN PENJUALAN PER PRODUK PANEL PERSIS UI-05 -->
<div class="card-box">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 16px;">
        <div>
            <h3 class="card-box-title">Rincian penjualan per produk</h3>
            <p style="font-size: 0.78rem; color: var(--gray-500); margin-top: 2px;">
                Lihat jumlah terjual, penjualan, HPP, dan laba setiap produk.
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;" class="no-print">
            <button type="button" class="btn btn-primary btn-sm" onclick="alert('Membuka modal detail pergerakan SKU');">
                ▤ Lihat Detail per Produk
            </button>
            <span style="font-size: 0.74rem; color: var(--gray-400);">Periode detail mengikuti ringkasan.</span>
        </div>
    </div>

    <div class="table-wrap">
        <table class="sketch-table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>NAMA PRODUK</th>
                    <th>KATEGORI</th>
                    <th style="text-align: center;">QTY TERJUAL</th>
                    <th style="text-align: right;">PENJUALAN (RP)</th>
                    <th style="text-align: right;">HPP TOTAL (RP)</th>
                    <th style="text-align: right;">LABA KOTOR (RP)</th>
                    <th style="text-align: center;">MARGIN</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($detailProduk)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--gray-400); padding: 24px;">
                            Tidak ada data transaksi penjualan pada periode terpilih (<?= htmlspecialchars($tgl_mulai); ?> s/d <?= htmlspecialchars($tgl_akhir); ?>).
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($detailProduk as $row): ?>
                        <tr>
                            <td class="sku-badge">PRD-<?= str_pad($row['id_produk'], 3, '0', STR_PAD_LEFT); ?></td>
                            <td><strong><?= htmlspecialchars($row['nama_produk']); ?></strong></td>
                            <td><?= htmlspecialchars($row['kategori'] ?? '-'); ?></td>
                            <td style="text-align: center;"><strong><?= $row['total_qty']; ?> unit</strong></td>
                            <td style="text-align: right;">Rp <?= number_format($row['total_penjualan'], 0, ',', '.'); ?></td>
                            <td style="text-align: right;">Rp <?= number_format($row['total_hpp'], 0, ',', '.'); ?></td>
                            <td style="text-align: right; color: var(--primary); font-weight: bold;">Rp <?= number_format($row['laba_kotor'], 0, ',', '.'); ?></td>
                            <td style="text-align: center;"><span class="pill pill-green"><?= number_format($row['margin'], 2, ',', '.'); ?>%</span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div style="font-size: 0.72rem; color: var(--gray-400); text-align: center; padding-top: 10px;">
        Data teragregasi secara riil berdasarkan catatan penjualan dan harga pokok perolehan (HPP).
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
