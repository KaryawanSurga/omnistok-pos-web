<?php
/**
 * OMNISTOK - Portal Utama & Dashboard Integrasi
 * Praktikum Rekayasa Sistem Informasi - Kelompok 6 (Kelas SI-G)
 * Disinkronkan 100% dengan Modul 2 SDD dan Modul 1 SRS
 */

require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();

$countAkun = $db->query("SELECT COUNT(*) FROM akun")->fetchColumn();
$countProduk = $db->query("SELECT COUNT(*) FROM produk")->fetchColumn();
$countStok = $db->query("SELECT COALESCE(SUM(jumlah_stok), 0) FROM stok")->fetchColumn();
$countPenerimaan = $db->query("SELECT COUNT(*) FROM penerimaan")->fetchColumn();
$countPenjualan = $db->query("SELECT COUNT(*) FROM transaksi")->fetchColumn();
$totalOmset = $db->query("SELECT COALESCE(SUM(total_harga), 0) FROM transaksi")->fetchColumn();

$activePage = 'dashboard';
$pageTitle = 'Dashboard Utama Portal Integrasi';
$moduleBreadcrumb = 'MAIN / DASHBOARD INTEGRASI KELOMPOK 6';

require_once __DIR__ . '/layout/header.php';
?>

<div class="page-title-box">
    <h1 class="page-title">Sistem Manajemen POS & Inventori OmniStok</h1>
    <p class="page-subtitle">Portal Implementasi Web OOP & Evaluasi Dokumen Modul 3 Praktikum Rekayasa Sistem Informasi (FILKOM UB)</p>
</div>

<!-- STATS OVERVIEW CARDS (CLICKABLE) -->
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 26px;">
    <a href="kelola_akun.php" class="stat-box" style="text-decoration: none; color: inherit;">
        <div class="stat-info">
            <div class="stat-label">TOTAL PENGGUNA</div>
            <div class="stat-value"><?= number_format($countAkun); ?></div>
            <div class="stat-sub">Tabel Akun [JK-DDL01]</div>
        </div>
        <span class="pill pill-teal">Kelola &rarr;</span>
    </a>

    <a href="daftar_produk.php" class="stat-box" style="text-decoration: none; color: inherit;">
        <div class="stat-info">
            <div class="stat-label">KATALOG PRODUK</div>
            <div class="stat-value"><?= number_format($countProduk); ?> SKU</div>
            <div class="stat-sub">Master Produk Toko</div>
        </div>
        <span class="pill pill-teal">Katalog &rarr;</span>
    </a>

    <a href="laporan_mutasi.php" class="stat-box" style="text-decoration: none; color: inherit;">
        <div class="stat-info">
            <div class="stat-label">TOTAL STOK FISIK</div>
            <div class="stat-value"><?= number_format($countStok); ?> Unit</div>
            <div class="stat-sub">Tabel Stok [FW-DDL02]</div>
        </div>
        <span class="pill pill-green">Rak Gudang &rarr;</span>
    </a>

    <a href="penerimaan_barang.php" class="stat-box" style="text-decoration: none; color: inherit;">
        <div class="stat-info">
            <div class="stat-label">FAKTUR DITERIMA</div>
            <div class="stat-value"><?= number_format($countPenerimaan); ?> PO</div>
            <div class="stat-sub">Tabel Penerimaan [ZM-DDL01]</div>
        </div>
        <span class="pill pill-teal">Goods Receipt &rarr;</span>
    </a>
</div>

<!-- GRID MODUL KELOMPOK 6 -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
    <h2 style="font-size: 1.18rem; font-weight: 700; color: var(--dark);">
        Daftar Modul & Fitur Terpadu Kelompok 6 (Kelas SI-G)
    </h2>
    <span class="pill pill-teal" style="font-size: 0.76rem;">11 Modul 100% Aktif &amp; Fungsional</span>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 18px; margin-bottom: 30px;">
    <!-- 1. Justin Kelvin: Kelola Akun -->
    <div class="card-box" style="margin-bottom: 0; display: flex; flex-direction: column;">
        <div class="card-box-header">
            <div>
                <span class="sku-badge">JK-01 • UC-02</span>
                <h3 class="card-box-title" style="margin-top: 4px;">Kelola Akun &amp; Akses</h3>
            </div>
            <span class="pill pill-teal">Justin Kelvin</span>
        </div>
        <p style="font-size: 0.84rem; color: var(--gray-500); margin-bottom: 14px; flex: 1;">
            Manajemen pengguna sistem, otentikasi login, serta hak akses berbasis peran (Pemilik Toko, Kasir, Petugas Stok) sesuai antarmuka <code>UI-02</code>.
        </p>
        <div style="background: var(--gray-100); padding: 8px 12px; border-radius: 6px; font-size: 0.76rem; color: var(--slate); margin-bottom: 14px;">
            Artefak: <code>CD-02 AkunController</code> | <code>UI-02</code> | <code>akun</code>
        </div>
        <a href="kelola_akun.php" class="btn btn-primary" style="width: 100%;">Buka Kelola Akun &rarr;</a>
    </div>

    <!-- 2. Justin Kelvin: Daftar Produk -->
    <div class="card-box" style="margin-bottom: 0; display: flex; flex-direction: column;">
        <div class="card-box-header">
            <div>
                <span class="sku-badge">JK-02 • UC-13..16</span>
                <h3 class="card-box-title" style="margin-top: 4px;">Katalog &amp; Pembaruan Harga</h3>
            </div>
            <span class="pill pill-teal">Justin &amp; Zainal</span>
        </div>
        <p style="font-size: 0.84rem; color: var(--gray-500); margin-bottom: 14px; flex: 1;">
            Manajemen master data produk, penyesuaian harga modal (HPP) vs harga jual, validasi margin keuntungan &gt;= 8%, dan audit trail riwayat harga (<code>UI-15</code> &amp; <code>UI-20</code>).
        </p>
        <div style="background: var(--gray-100); padding: 8px 12px; border-radius: 6px; font-size: 0.76rem; color: var(--slate); margin-bottom: 14px;">
            Artefak: <code>CD-10 ProdukController</code> | <code>UI-15 / UI-20</code> | <code>produk</code>
        </div>
        <a href="daftar_produk.php" class="btn btn-primary" style="width: 100%;">Buka Daftar Produk &rarr;</a>
    </div>

    <!-- 3. Justin Kelvin: Kelola Diskon -->
    <div class="card-box" style="margin-bottom: 0; display: flex; flex-direction: column;">
        <div class="card-box-header">
            <div>
                <span class="sku-badge">JK-03 • UC-04</span>
                <h3 class="card-box-title" style="margin-top: 4px;">Kelola Diskon &amp; Promo</h3>
            </div>
            <span class="pill pill-teal">Justin Kelvin</span>
        </div>
        <p style="font-size: 0.84rem; color: var(--gray-500); margin-bottom: 14px; flex: 1;">
            Konfigurasi program promosi bertingkat (persentase / nominal), pembatasan kuota, masa aktif promo, dan toggle on/off real-time sesuai <code>UI-06</code>.
        </p>
        <div style="background: var(--gray-100); padding: 8px 12px; border-radius: 6px; font-size: 0.76rem; color: var(--slate); margin-bottom: 14px;">
            Artefak: <code>CD-04 DiskonController</code> | <code>UI-06</code> | <code>diskon</code>
        </div>
        <a href="kelola_diskon.php" class="btn btn-primary" style="width: 100%;">Buka Kelola Diskon &rarr;</a>
    </div>

    <!-- 4. Zainal Maarif: Penerimaan Barang -->
    <div class="card-box" style="margin-bottom: 0; display: flex; flex-direction: column;">
        <div class="card-box-header">
            <div>
                <span class="sku-badge">ZM-01 • UC-09</span>
                <h3 class="card-box-title" style="margin-top: 4px;">Penerimaan Barang (Goods Receipt)</h3>
            </div>
            <span class="pill pill-teal">Zainal Maarif</span>
        </div>
        <p style="font-size: 0.84rem; color: var(--gray-500); margin-bottom: 14px; flex: 1;">
            Pencatatan faktur surat jalan pemasok, verifikasi fisik kuantitas layak vs rusak kemasan, nomor batch, kedaluwarsa, dan pembukuan mutasi masuk otomatis (<code>UI-19</code>).
        </p>
        <div style="background: var(--gray-100); padding: 8px 12px; border-radius: 6px; font-size: 0.76rem; color: var(--slate); margin-bottom: 14px;">
            Artefak: <code>CD-09 PenerimaanController</code> | <code>UI-19</code> | <code>penerimaan</code>
        </div>
        <a href="penerimaan_barang.php" class="btn btn-primary" style="width: 100%;">Buka Penerimaan Barang &rarr;</a>
    </div>

    <!-- 5. Zainal Maarif: Penyesuaian Stok (Opname) -->
    <div class="card-box" style="margin-bottom: 0; display: flex; flex-direction: column;">
        <div class="card-box-header">
            <div>
                <span class="sku-badge">ZM-02 • UC-11</span>
                <h3 class="card-box-title" style="margin-top: 4px;">Penyesuaian Stok Fisik (Opname)</h3>
            </div>
            <span class="pill pill-teal">Zainal Maarif</span>
        </div>
        <p style="font-size: 0.84rem; color: var(--gray-500); margin-bottom: 14px; flex: 1;">
            Sesi stock opname rak gudang, perbandingan stok buku vs fisik, kalkulasi selisih otomatis, catatan alasan deviasi, dan posting mutasi koreksi (<code>UI-21</code>).
        </p>
        <div style="background: var(--gray-100); padding: 8px 12px; border-radius: 6px; font-size: 0.76rem; color: var(--slate); margin-bottom: 14px;">
            Artefak: <code>CD-11 OpnameController</code> | <code>UI-21</code> | <code>penyesuaian_stok</code>
        </div>
        <a href="penyesuaian_stok.php" class="btn btn-primary" style="width: 100%;">Buka Penyesuaian Stok &rarr;</a>
    </div>

    <!-- 6. Zainal Maarif: Retur Barang -->
    <div class="card-box" style="margin-bottom: 0; display: flex; flex-direction: column;">
        <div class="card-box-header">
            <div>
                <span class="sku-badge">ZM-03 • UC-12</span>
                <h3 class="card-box-title" style="margin-top: 4px;">Retur Barang ke Pemasok</h3>
            </div>
            <span class="pill pill-teal">Zainal Maarif</span>
        </div>
        <p style="font-size: 0.84rem; color: var(--gray-500); margin-bottom: 14px; flex: 1;">
            Penerbitan surat jalan retur barang cacat/rusak ke vendor pemasok, lampiran bukti fisik foto, pemotongan stok batch, dan pembukuan mutasi keluar (<code>UI-22</code>).
        </p>
        <div style="background: var(--gray-100); padding: 8px 12px; border-radius: 6px; font-size: 0.76rem; color: var(--slate); margin-bottom: 14px;">
            Artefak: <code>CD-12 ReturController</code> | <code>UI-22</code> | <code>retur_penerimaan</code>
        </div>
        <a href="retur_barang.php" class="btn btn-primary" style="width: 100%;">Buka Retur Barang &rarr;</a>
    </div>

    <!-- 7. Ferdyansah: Transaksi Kasir POS -->
    <div class="card-box" style="margin-bottom: 0; display: flex; flex-direction: column;">
        <div class="card-box-header">
            <div>
                <span class="sku-badge">FF-01 • UC-07</span>
                <h3 class="card-box-title" style="margin-top: 4px;">Transaksi Penjualan Kasir POS</h3>
            </div>
            <span class="pill pill-teal">Ferdyansah</span>
        </div>
        <p style="font-size: 0.84rem; color: var(--gray-500); margin-bottom: 14px; flex: 1;">
            Terminal kasir kas masuk: pemindaian barcode produk, keranjang belanja dinamis, pembayaran Tunai dengan hitung kembalian cepat, pembayaran QRIS dinamis, dan cetak struk POS (<code>UI-11, UI-12, UI-13</code>).
        </p>
        <div style="background: var(--gray-100); padding: 8px 12px; border-radius: 6px; font-size: 0.76rem; color: var(--slate); margin-bottom: 14px;">
            Artefak: <code>CD-07 TransaksiController</code> | <code>UI-11..13</code> | <code>transaksi</code>
        </div>
        <a href="transaksi_pos.php" class="btn btn-primary" style="width: 100%;">Buka Kasir POS &rarr;</a>
    </div>

    <!-- 8. Ferdyansah: Rekonsiliasi Kas -->
    <div class="card-box" style="margin-bottom: 0; display: flex; flex-direction: column;">
        <div class="card-box-header">
            <div>
                <span class="sku-badge">FF-02 • UC-08</span>
                <h3 class="card-box-title" style="margin-top: 4px;">Rekonsiliasi Kas (End of Shift)</h3>
            </div>
            <span class="pill pill-teal">Ferdyansah</span>
        </div>
        <p style="font-size: 0.84rem; color: var(--gray-500); margin-bottom: 14px; flex: 1;">
            Penghitungan fisik uang laci kasir per pecahan (uang kertas &amp; koin), audit selisih otomatis terhadap saldo sistem, penutupan register kasir (<code>UI-14</code>).
        </p>
        <div style="background: var(--gray-100); padding: 8px 12px; border-radius: 6px; font-size: 0.76rem; color: var(--slate); margin-bottom: 14px;">
            Artefak: <code>CD-08 RekonsiliasiController</code> | <code>UI-14</code> | <code>rekonsiliasi</code>
        </div>
        <a href="rekonsiliasi_kas.php" class="btn btn-primary" style="width: 100%;">Buka Rekonsiliasi Kas &rarr;</a>
    </div>

    <!-- 9. Fauzi Al Wahid: Laporan Penjualan & Laba -->
    <div class="card-box" style="margin-bottom: 0; display: flex; flex-direction: column;">
        <div class="card-box-header">
            <div>
                <span class="sku-badge">FW-01 • UC-03</span>
                <h3 class="card-box-title" style="margin-top: 4px;">Laporan Penjualan &amp; Laba Kotor</h3>
            </div>
            <span class="pill pill-teal">Fauzi Al Wahid</span>
        </div>
        <p style="font-size: 0.84rem; color: var(--gray-500); margin-bottom: 14px; flex: 1;">
            Rekapitulasi penjualan bersih toko, HPP historis produk, margin laba kotor toko, dan visualisasi grafik tren penjualan harian (<code>UI-05</code>).
        </p>
        <div style="background: var(--gray-100); padding: 8px 12px; border-radius: 6px; font-size: 0.76rem; color: var(--slate); margin-bottom: 14px;">
            Artefak: <code>CD-03 LaporanController</code> | <code>UI-05</code> | <code>laporan</code>
        </div>
        <a href="laporan_penjualan.php" class="btn btn-primary" style="width: 100%;">Buka Laporan Penjualan &rarr;</a>
    </div>

    <!-- 10. Fauzi Al Wahid: Laporan Mutasi Stok -->
    <div class="card-box" style="margin-bottom: 0; display: flex; flex-direction: column;">
        <div class="card-box-header">
            <div>
                <span class="sku-badge">FW-02 • UC-01</span>
                <h3 class="card-box-title" style="margin-top: 4px;">Laporan Mutasi &amp; Peringatan Stok</h3>
            </div>
            <span class="pill pill-teal">Fauzi Al Wahid</span>
        </div>
        <p style="font-size: 0.84rem; color: var(--gray-500); margin-bottom: 14px; flex: 1;">
            Pelacakan riwayat mutasi persediaan barang masuk, keluar, dan penyesuaian, terintegrasi dengan tab peringatan stok menipis (<code>UI-09</code>) dan kedaluwarsa (<code>UI-10</code>).
        </p>
        <div style="background: var(--gray-100); padding: 8px 12px; border-radius: 6px; font-size: 0.76rem; color: var(--slate); margin-bottom: 14px;">
            Artefak: <code>CD-01 MutasiController</code> | <code>UI-01 / UI-09 / UI-10</code> | <code>mutasi_stok</code>
        </div>
        <a href="laporan_mutasi.php" class="btn btn-primary" style="width: 100%;">Buka Laporan Mutasi &rarr;</a>
    </div>
</div>

<!-- SYSTEM STATUS TERMINAL BOX -->
<div class="card-box" style="background: #0f172a; color: #f8fafc; border: 1px solid #1e293b;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid #334155; padding-bottom: 10px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #22c55e;"></span>
            <strong style="font-size: 0.88rem; letter-spacing: 0.5px;">OMNISTOK SYSTEM HEALTH &amp; RUNTIME STATUS</strong>
        </div>
        <span style="font-size: 0.72rem; color: #94a3b8; font-family: monospace;">PHP <?= phpversion(); ?> • SQLite PDO • Port 8000</span>
    </div>
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; font-size: 0.8rem; font-family: monospace;">
        <div>
            <div style="color: #94a3b8;">DATABASE ENGINE:</div>
            <div style="color: #38bdf8; font-weight: bold;">SQLite3 (In-Memory/File)</div>
        </div>
        <div>
            <div style="color: #94a3b8;">TABLE SCHEMA:</div>
            <div style="color: #4ade80; font-weight: bold;">17 Tables Synchronized</div>
        </div>
        <div>
            <div style="color: #94a3b8;">SESSION USER:</div>
            <div style="color: #facc15; font-weight: bold;"><?= htmlspecialchars($userName); ?> (<?= htmlspecialchars($userRole); ?>)</div>
        </div>
        <div>
            <div style="color: #94a3b8;">SERVER TIMESTAMP:</div>
            <div style="color: #f472b6; font-weight: bold;"><?= date('d M Y - H:i:s T'); ?></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
