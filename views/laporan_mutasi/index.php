<?php
/**
 * View: Laporan Mutasi Stok Persediaan
 * Dipanggil oleh entry point `laporan_mutasi.php` setelah controller menyiapkan data.
 */
require dirname(__DIR__, 2) . '/layout/header.php';
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
    <h1 class="page-title">Laporan Mutasi Stok Persediaan</h1>
    <p class="page-subtitle">Penelusuran dan pemantauan arus keluar-masuk barang persediaan toko.</p>
</div>

<!-- FILTER PERIODE FORM [tampilkan_form_periode()] -->
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
        <?php if ($isValid && !empty($hasilMutasi['data'])): ?>
            <a href="laporan_mutasi.php?tgl_mulai=<?= urlencode($tgl_mulai); ?>&tgl_akhir=<?= urlencode($tgl_akhir); ?>&export=pdf" class="btn btn-secondary" style="height: 40px; display: inline-flex; align-items: center; gap: 6px;">
                Unduh PDF
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- PERCABANGAN ALT SEQUENCE DIAGRAM SD-01 -->
<?php if (!$isValid): ?>
    <!-- ALT 1: [periode tidak valid] -> tampilkan_pesan_kesalahan(pesan) -->
    <div class="alert alert-danger" style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; justify-content: flex-start; gap: 12px;">
        <span style="font-size: 1.1rem; font-weight: bold; background: #fee2e2; border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; color: #b91c1c; flex-shrink: 0;">!</span>
        <div>
            <strong>Periode Tanggal Tidak Valid!</strong>
            <div style="font-size: 0.88rem; margin-top: 2px;"><?= htmlspecialchars($pesanPeringatan); ?></div>
        </div>
    </div>
<?php else: ?>
    <!-- ALT 2: [periode valid] -->
    <?php if (empty($hasilMutasi['data'])): ?>
        <!-- SUB-ALT 2A: [data mutasi kosong] -> tampilkan_pesan_data_kosong() -->
        <div class="alert alert-warning" style="background: #fffbeb; border: 1px solid #fef3c7; color: #92400e; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; justify-content: flex-start; gap: 12px;">
            <span style="font-size: 1.1rem; font-weight: bold; background: #fef3c7; border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; color: #b45309; flex-shrink: 0;">i</span>
            <div>
                <strong>Data Mutasi Stok Tidak Tersedia</strong>
                <div style="font-size: 0.88rem; margin-top: 2px;">Data mutasi stok tidak tersedia pada periode yang dipilih (<?= htmlspecialchars($tgl_mulai); ?> s/d <?= htmlspecialchars($tgl_akhir); ?>). Silakan pilih rentang tanggal lain.</div>
            </div>
        </div>
    <?php else: ?>
        <!-- SUB-ALT 2B: [data mutasi tersedia] -> tampilkan_rincian_mutasi(data_mutasi) -->
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
                <span class="pill pill-teal"><?= $hasilMutasi['total_data'] ?? 0; ?> Catatan</span>
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
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

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
                                <span class="pill pill-red">Stok Menipis</span>
                            <?php else: ?>
                                <span class="pill pill-green">Aman</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layout/footer.php'; ?>
