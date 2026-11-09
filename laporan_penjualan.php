<?php
/**
 * View Antarmuka: Laporan Penjualan & Laba Kotor [UI-05]
 * Penulis: Fauzi Al Wahid (FW - 255150400111016)
 * Referensi UI SDD: UI-05 (Melihat Laporan Penjualan dan Laba)
 * Disinkronkan 100% dengan Sketsa UI Modul 2
 */

require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();

$tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : '2026-10-14';
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : '2026-10-20';

// Ambil transaksi riil dari database
$transaksiList = $db->query("
    SELECT t.id_transaksi, t.tanggal_transaksi, t.total_harga,
           COUNT(dp.id_detail_penjualan) as total_sku,
           COALESCE(SUM(dp.jumlah), 1) as total_qty,
           COALESCE(SUM(dp.hpp * dp.jumlah), t.total_harga * 0.78) as total_hpp,
           (t.total_harga - COALESCE(SUM(dp.hpp * dp.jumlah), t.total_harga * 0.78)) as laba_kotor
    FROM transaksi t
    LEFT JOIN detail_penjualan dp ON t.id_transaksi = dp.id_transaksi
    GROUP BY t.id_transaksi
    ORDER BY t.tanggal_transaksi DESC
")->fetchAll();

$penjualanBersih = 1860000;
$totalHpp = 1480000;
$labaKotor = $penjualanBersih - $totalHpp; // Rp 380.000
$marginLaba = round(($labaKotor / $penjualanBersih) * 100, 2); // 20.43%

$activePage = 'laporan_penjualan';
$pageTitle = 'Laporan Penjualan & Laba';
$moduleBreadcrumb = 'REPORTS / SALES & PROFIT ANALYTICS';
$userName = 'Fauzi Al Wahid';
$userRole = 'Pemilik Toko (Akses Penuh)';
$userInitials = 'FW';

require_once __DIR__ . '/layout/header.php';
?>

<div class="page-title-box">
    <h1 class="page-title">Laporan Penjualan &amp; Laba</h1>
    <p class="page-subtitle">Pantau penjualan bersih, HPP historis, serta laba kotor toko.</p>
</div>

<!-- FILTER PERIODE FORM PERSIS UI-05 -->
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
    </form>
</div>

<!-- 4 SUMMARY CARDS PERSIS UI-05 -->
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 24px;">
    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">PENJUALAN BERSIH</div>
            <div class="stat-value">Rp <?= number_format($penjualanBersih, 0, ',', '.'); ?></div>
            <div class="stat-sub">• Setelah diskon</div>
        </div>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">TOTAL HPP</div>
            <div class="stat-value">Rp <?= number_format($totalHpp, 0, ',', '.'); ?></div>
            <div class="stat-sub">• Biaya perolehan historis</div>
        </div>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">LABA KOTOR</div>
            <div class="stat-value" style="color: var(--primary);">Rp <?= number_format($labaKotor, 0, ',', '.'); ?></div>
            <div class="stat-sub">• Penjualan dikurangi HPP</div>
        </div>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">MARGIN LABA KOTOR</div>
            <div class="stat-value" style="color: #0284c7;"><?= number_format($marginLaba, 2, ',', '.'); ?>%</div>
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
                <span style="font-size: 0.74rem; color: var(--gray-400);">14-20 Oktober 2026 • Dalam ribuan rupiah</span>
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

        <!-- AUTHENTIC SVG LINE CHART MATCHING WIREFRAME -->
        <div style="width: 100%; height: 260px; position: relative;">
            <svg viewBox="0 0 700 240" style="width: 100%; height: 100%; overflow: visible;">
                <!-- Grid Lines -->
                <line x1="50" y1="30" x2="680" y2="30" stroke="#f1f5f9" stroke-width="1" />
                <line x1="50" y1="80" x2="680" y2="80" stroke="#f1f5f9" stroke-width="1" />
                <line x1="50" y1="130" x2="680" y2="130" stroke="#f1f5f9" stroke-width="1" />
                <line x1="50" y1="180" x2="680" y2="180" stroke="#f1f5f9" stroke-width="1" />
                <line x1="50" y1="210" x2="680" y2="210" stroke="#cbd5e1" stroke-width="1.5" />

                <!-- Y Axis Labels -->
                <text x="35" y="34" font-size="11" fill="#94a3b8" text-anchor="end">400</text>
                <text x="35" y="84" font-size="11" fill="#94a3b8" text-anchor="end">300</text>
                <text x="35" y="134" font-size="11" fill="#94a3b8" text-anchor="end">200</text>
                <text x="35" y="184" font-size="11" fill="#94a3b8" text-anchor="end">100</text>
                <text x="35" y="214" font-size="11" fill="#94a3b8" text-anchor="end">0</text>

                <!-- X Axis Labels (Dates) -->
                <!-- x coords: 80, 180, 280, 380, 480, 580, 660 -->
                <text x="80" y="230" font-size="11" fill="#64748b" text-anchor="middle">14 Okt</text>
                <text x="180" y="230" font-size="11" fill="#64748b" text-anchor="middle">15 Okt</text>
                <text x="280" y="230" font-size="11" fill="#64748b" text-anchor="middle">16 Okt</text>
                <text x="380" y="230" font-size="11" fill="#64748b" text-anchor="middle">17 Okt</text>
                <text x="480" y="230" font-size="11" fill="#64748b" text-anchor="middle">18 Okt</text>
                <text x="580" y="230" font-size="11" fill="#64748b" text-anchor="middle">19 Okt</text>
                <text x="660" y="230" font-size="11" fill="#64748b" text-anchor="middle">20 Okt</text>

                <!-- Penjualan Line (Teal #0f766e) -->
                <!-- Points: (80, 145), (180, 105), (280, 115), (380, 75), (480, 85), (580, 65), (660, 55) -->
                <polyline fill="none" stroke="#0f766e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                    points="80,145 180,105 280,115 380,75 480,85 580,65 660,55" />
                <circle cx="80" cy="145" r="4.5" fill="#0f766e" />
                <circle cx="180" cy="105" r="4.5" fill="#0f766e" />
                <circle cx="280" cy="115" r="4.5" fill="#0f766e" />
                <circle cx="380" cy="75" r="4.5" fill="#0f766e" />
                <circle cx="480" cy="85" r="4.5" fill="#0f766e" />
                <circle cx="580" cy="65" r="4.5" fill="#0f766e" />
                <circle cx="660" cy="55" r="4.5" fill="#0f766e" />

                <!-- Laba Kotor Line (Blue #0284c7) -->
                <!-- Points: (80, 195), (180, 190), (280, 192), (380, 185), (480, 188), (580, 178), (660, 180) -->
                <polyline fill="none" stroke="#0284c7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                    points="80,195 180,190 280,192 380,185 480,188 580,178 660,180" />
                <circle cx="80" cy="195" r="4" fill="#0284c7" />
                <circle cx="180" cy="190" r="4" fill="#0284c7" />
                <circle cx="280" cy="192" r="4" fill="#0284c7" />
                <circle cx="380" cy="185" r="4" fill="#0284c7" />
                <circle cx="480" cy="188" r="4" fill="#0284c7" />
                <circle cx="580" cy="178" r="4" fill="#0284c7" />
                <circle cx="660" cy="180" r="4" fill="#0284c7" />
            </svg>
        </div>
    </div>

    <!-- RIGHT: RINGKASAN PERIODE -->
    <div class="card-box" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
        <div>
            <div class="card-box-header">
                <h3 class="card-box-title">Ringkasan Periode</h3>
                <span class="pill pill-teal">7 Hari</span>
            </div>

            <div style="margin-bottom: 18px;">
                <div style="font-size: 1.8rem; font-weight: 800; color: var(--dark);">55 unit</div>
                <div style="font-size: 0.76rem; color: var(--gray-400);">Jumlah produk terjual</div>
            </div>

            <div style="margin-bottom: 18px;">
                <div style="font-size: 1.8rem; font-weight: 800; color: var(--dark);">4 SKU</div>
                <div style="font-size: 0.76rem; color: var(--gray-400);">Produk yang terjual</div>
            </div>

            <div style="margin-bottom: 18px;">
                <div style="font-size: 1.8rem; font-weight: 800; color: var(--dark);">7 hari</div>
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
        <div style="display: flex; gap: 8px; align-items: center;">
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
                    <th style="text-align: center;">QTY TERJUAL</th>
                    <th style="text-align: right;">PENJUALAN (RP)</th>
                    <th style="text-align: right;">HPP TOTAL (RP)</th>
                    <th style="text-align: right;">LABA KOTOR (RP)</th>
                    <th style="text-align: center;">MARGIN</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="sku-badge">PRD-001</td>
                    <td><strong>Beras Rojolele 5 kg</strong></td>
                    <td style="text-align: center;">18 unit</td>
                    <td style="text-align: right;">Rp 1.170.000</td>
                    <td style="text-align: right;">Rp 1.044.000</td>
                    <td style="text-align: right; color: var(--primary); font-weight: bold;">Rp 126.000</td>
                    <td style="text-align: center;"><span class="pill pill-green">10.77%</span></td>
                </tr>
                <tr>
                    <td class="sku-badge">PRD-002</td>
                    <td><strong>Minyak Goreng 2 L</strong></td>
                    <td style="text-align: center;">15 pouch</td>
                    <td style="text-align: right;">Rp 450.000</td>
                    <td style="text-align: right;">Rp 390.000</td>
                    <td style="text-align: right; color: var(--primary); font-weight: bold;">Rp 60.000</td>
                    <td style="text-align: center;"><span class="pill pill-green">13.33%</span></td>
                </tr>
                <tr>
                    <td class="sku-badge">PRD-003</td>
                    <td><strong>Gula Pasir Kristal 1 kg</strong></td>
                    <td style="text-align: center;">12 unit</td>
                    <td style="text-align: right;">Rp 180.000</td>
                    <td style="text-align: right;">Rp 150.000</td>
                    <td style="text-align: right; color: var(--primary); font-weight: bold;">Rp 30.000</td>
                    <td style="text-align: center;"><span class="pill pill-green">16.67%</span></td>
                </tr>
                <tr>
                    <td class="sku-badge">PRD-004</td>
                    <td><strong>Kopi Susu Aren Botol</strong></td>
                    <td style="text-align: center;">10 botol</td>
                    <td style="text-align: right;">Rp 60.000</td>
                    <td style="text-align: right;">Rp 40.000</td>
                    <td style="text-align: right; color: var(--primary); font-weight: bold;">Rp 20.000</td>
                    <td style="text-align: center;"><span class="pill pill-green">33.33%</span></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div style="font-size: 0.72rem; color: var(--gray-400); text-align: center; padding-top: 10px;">
        Data contoh untuk rancangan UI • Nilai bukan data operasional toko
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
