<?php
/**
 * View Antarmuka: Laporan Mutasi Stok Persediaan
 * Penulis: Fauzi Al Wahid (FW - 255150400111016)
 * Referensi UI SDD: UI-01 (Halaman Laporan Mutasi Stok)
 * Kode UI: FW-IM-001
 * Disinkronkan 100% dengan Sketsa UI Modul 2
 */

require_once __DIR__ . '/controllers/MutasiStokController.php';

$controller = new MutasiStokController();
$defaultPeriode = $controller->buka_laporan_mutasi();

$tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : $defaultPeriode['tgl_mulai'];
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : $defaultPeriode['tgl_akhir'];

$hasilMutasi = $controller->get_laporan_mutasi($tgl_mulai, $tgl_akhir);
$ringkasanStok = $controller->get_ringkasan_stok();

$totalMasuk = 0;
$totalKeluar = 0;
$totalPenyesuaian = 0;

if ($hasilMutasi['status']) {
    foreach ($hasilMutasi['data'] as $m) {
        if ($m['jenis_mutasi'] === 'Masuk') {
            $totalMasuk += abs($m['jumlah']);
        } elseif ($m['jenis_mutasi'] === 'Keluar') {
            $totalKeluar += abs($m['jumlah']);
        } else {
            $totalPenyesuaian += abs($m['jumlah']);
        }
    }
}

$activePage = 'laporan_mutasi';
$pageTitle = 'Laporan Mutasi Stok';
$moduleBreadcrumb = 'REPORTS / LAPORAN MUTASI STOK PERSEDIAAN';
$userName = 'Fauzi Al Wahid';
$userRole = 'Pemilik Toko (FW)';
$userInitials = 'FW';

require_once __DIR__ . '/layout/header.php';
?>

<div class="page-title-box">
    <h1 class="page-title">Laporan Mutasi Stok Persediaan [UI-01]</h1>
    <p class="page-subtitle">Penelusuran Arus Keluar-Masuk Barang Toko (Penulis: Fauzi Al Wahid - FW)</p>
</div>

<!-- FILTER PERIODE FORM -->
<div class="card-box" style="padding: 16px 20px;">
    <form method="GET" action="laporan_mutasi.php" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
        <div class="form-group" style="flex: 1; min-width: 180px;">
            <label for="tgl_mulai">Tanggal Mulai:</label>
            <input type="date" id="tgl_mulai" name="tgl_mulai" class="form-control" value="<?= htmlspecialchars($tgl_mulai); ?>" required>
        </div>
        <div class="form-group" style="flex: 1; min-width: 180px;">
            <label for="tgl_akhir">Tanggal Akhir:</label>
            <input type="date" id="tgl_akhir" name="tgl_akhir" class="form-control" value="<?= htmlspecialchars($tgl_akhir); ?>" required>
        </div>
        <button type="submit" class="btn btn-primary" style="height: 40px;">
            Tampilkan Laporan Mutasi
        </button>
    </form>
</div>

<!-- METRIK MUTASI -->
<div class="stats-grid">
    <div class="stat-box" style="border-top: 3px solid var(--success);">
        <div class="stat-info">
            <div class="stat-label">BARANG MASUK (PENERIMAAN)</div>
            <div class="stat-value" style="color: var(--success);">+<?= $totalMasuk; ?> Unit</div>
            <div class="stat-sub">Periode Terpilih</div>
        </div>
        <span class="pill pill-green">Masuk</span>
    </div>

    <div class="stat-box" style="border-top: 3px solid var(--danger);">
        <div class="stat-info">
            <div class="stat-label">BARANG KELUAR (PENJUALAN)</div>
            <div class="stat-value" style="color: var(--danger);">-<?= $totalKeluar; ?> Unit</div>
            <div class="stat-sub">Periode Terpilih</div>
        </div>
        <span class="pill pill-red">Keluar</span>
    </div>

    <div class="stat-box" style="border-top: 3px solid var(--warning);">
        <div class="stat-info">
            <div class="stat-label">PENYESUAIAN / OPNAME</div>
            <div class="stat-value" style="color: var(--warning);"><?= $totalPenyesuaian; ?> Unit</div>
            <div class="stat-sub">Periode Terpilih</div>
        </div>
        <span class="pill pill-orange">Koreksi</span>
    </div>
</div>

<!-- TABEL LOG MUTASI -->
<div class="card-box">
    <div class="card-box-header">
        <h3 class="card-box-title">Rincian Log Riwayat Mutasi Stok</h3>
        <span class="pill pill-teal"><?= $hasilMutasi['total_data']; ?> Catatan</span>
    </div>

    <div class="table-wrap">
        <table class="sketch-table">
            <thead>
                <tr>
                    <th>ID LOG</th>
                    <th>WAKTU KEJADIAN</th>
                    <th>NAMA PRODUK</th>
                    <th>KATEGORI</th>
                    <th>JENIS MUTASI</th>
                    <th>KUANTITAS</th>
                    <th>ALASAN / RUJUKAN</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($hasilMutasi['data'])): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--gray-400);">Tidak ada catatan mutasi stok pada periode ini.</td></tr>
                <?php else: ?>
                    <?php foreach ($hasilMutasi['data'] as $mut): ?>
                        <tr>
                            <td>#<?= $mut['id_mutasi']; ?></td>
                            <td><?= $mut['tanggal_mutasi']; ?></td>
                            <td><strong><?= htmlspecialchars($mut['nama_produk']); ?></strong></td>
                            <td><?= htmlspecialchars($mut['kategori']); ?></td>
                            <td>
                                <?php if ($mut['jenis_mutasi'] === 'Masuk'): ?>
                                    <span class="pill pill-green">Masuk</span>
                                <?php elseif ($mut['jenis_mutasi'] === 'Keluar'): ?>
                                    <span class="pill pill-red">Keluar</span>
                                <?php else: ?>
                                    <span class="pill pill-orange">Penyesuaian</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= ($mut['jumlah'] > 0 ? '+' : '') . $mut['jumlah']; ?> unit</strong></td>
                            <td><?= htmlspecialchars($mut['alasan']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MONITORING POSISI STOK AKTUAL -->
<div class="card-box">
    <div class="card-box-header">
        <h3 class="card-box-title">Monitoring Status Stok Fisik Gudang Real-Time</h3>
        <span class="pill pill-teal">Batas Minimum Peringatan</span>
    </div>

    <div class="table-wrap">
        <table class="sketch-table">
            <thead>
                <tr>
                    <th>KODE</th>
                    <th>NAMA PRODUK</th>
                    <th>KATEGORI</th>
                    <th>HARGA JUAL</th>
                    <th>SISA STOK</th>
                    <th>MINIMUM</th>
                    <th>STATUS INDIKATOR</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ringkasanStok as $stk): ?>
                    <tr>
                        <td>PRD-00<?= $stk['id_produk']; ?></td>
                        <td><strong><?= htmlspecialchars($stk['nama_produk']); ?></strong></td>
                        <td><?= htmlspecialchars($stk['kategori']); ?></td>
                        <td>Rp <?= number_format($stk['harga_jual'], 0, ',', '.'); ?></td>
                        <td><strong><?= $stk['total_stok']; ?> unit</strong></td>
                        <td><?= $stk['batas_minimum']; ?> unit</td>
                        <td>
                            <?php if ($stk['status_stok'] === 'MENIPIS'): ?>
                                <span class="pill pill-red">⚠️ Stok Menipis</span>
                            <?php else: ?>
                                <span class="pill pill-green">✓ Aman</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
