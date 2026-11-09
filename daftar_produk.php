<?php
/**
 * View Antarmuka: Daftar Produk & Pembaruan Harga [UI-15 / UI-20]
 * Penulis: Zainal Maarif (ZM - 255150400111070) & Justin Kelvin (JK)
 * Referensi UI SDD: UI-15 (Mengelola Produk) & UI-20 (Memperbarui Harga Modal & Jual)
 * Disinkronkan 100% dengan Sketsa UI Modul 2
 */

require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();

$message = null;
$messageType = 'info';

// Proses Tambah Produk Baru (UI-16)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah_produk') {
    $nama = trim($_POST['nama_produk']);
    $modal = floatval($_POST['harga_modal']);
    $jual = floatval($_POST['harga_jual']);
    $stokAwal = intval($_POST['stok_awal']);

    if (empty($nama) || $modal <= 0 || $jual <= 0) {
        $message = "Nama produk, harga modal, dan harga jual wajib diisi secara valid!";
        $messageType = "danger";
    } elseif ($jual < $modal) {
        $message = "Peringatan Rugi: Harga jual tidak boleh lebih rendah dari harga modal!";
        $messageType = "danger";
    } else {
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO produk (nama_produk, harga_modal, harga_jual) VALUES (?, ?, ?)");
            $stmt->execute([$nama, $modal, $jual]);
            $newId = $db->lastInsertId();

            // Insert initial stock
            $stmtStok = $db->prepare("INSERT INTO stok (id_produk, jumlah_stok) VALUES (?, ?)");
            $stmtStok->execute([$newId, $stokAwal]);

            // Insert mutation log
            $stmtMutasi = $db->prepare("
                INSERT INTO mutasi_stok (id_produk, jenis_mutasi, jumlah, alasan, tanggal_mutasi)
                VALUES (?, 'Masuk', ?, 'Saldo awal pendaftaran master produk', datetime('now'))
            ");
            $stmtMutasi->execute([$newId, $stokAwal]);

            $db->commit();
            $message = "Produk baru '{$nama}' (PRD-00{$newId}) berhasil didaftarkan ke sistem!";
            $messageType = "success";
        } catch (Exception $e) {
            $db->rollBack();
            $message = "Gagal mendaftarkan produk: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Proses Update Harga (UI-20)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_harga') {
    $id_produk = intval($_POST['id_produk']);
    $modal_baru = floatval($_POST['harga_modal_baru']);
    $jual_baru = floatval($_POST['harga_jual_baru']);
    $alasan = trim($_POST['alasan']);

    if ($modal_baru <= 0 || $jual_baru <= 0) {
        $message = "Harga modal dan harga jual harus lebih besar dari 0!";
        $messageType = "danger";
    } elseif ($jual_baru < $modal_baru) {
        $message = "Peringatan: Harga jual tidak boleh lebih rendah dari harga modal (akan rugi)!";
        $messageType = "danger";
    } else {
        $curr = $db->query("SELECT harga_jual, harga_modal FROM produk WHERE id_produk = {$id_produk}")->fetch();
        
        $stmtRiwayat = $db->prepare("
            INSERT INTO riwayat_harga (id_produk, harga_jual_lama, harga_modal_lama, tanggal_perubahan)
            VALUES (?, ?, ?, ?)
        ");
        $stmtRiwayat->execute([$id_produk, $curr['harga_jual'], $curr['harga_modal'], date('Y-m-d')]);

        $stmtUpdate = $db->prepare("
            UPDATE produk SET harga_modal = ?, harga_jual = ? WHERE id_produk = ?
        ");
        $stmtUpdate->execute([$modal_baru, $jual_baru, $id_produk]);

        $message = "Struktur harga produk berhasil diperbarui dan dicatat dalam audit trail!";
        $messageType = "success";
    }
}

// Ambil daftar produk dengan stok
$produkList = $db->query("
    SELECT p.*, COALESCE(s.jumlah_stok, 0) as jumlah_stok
    FROM produk p
    LEFT JOIN stok s ON p.id_produk = s.id_produk
    ORDER BY p.id_produk ASC
")->fetchAll();

$selectedProdId = isset($_GET['id']) ? intval($_GET['id']) : ($produkList ? $produkList[0]['id_produk'] : 101);

$selectedProd = null;
foreach ($produkList as $p) {
    if ($p['id_produk'] == $selectedProdId) {
        $selectedProd = $p;
        break;
    }
}
if (!$selectedProd && !empty($produkList)) $selectedProd = $produkList[0];

$currMargin = 0;
if ($selectedProd && $selectedProd['harga_jual'] > 0) {
    $currMargin = round((($selectedProd['harga_jual'] - $selectedProd['harga_modal']) / $selectedProd['harga_jual']) * 100, 2);
}

// Ambil riwayat audit fluktuasi harga
$riwayat = [];
if ($selectedProd) {
    $riwayat = $db->query("
        SELECT rh.*, p.nama_produk
        FROM riwayat_harga rh
        JOIN produk p ON rh.id_produk = p.id_produk
        WHERE rh.id_produk = {$selectedProd['id_produk']}
        ORDER BY rh.tanggal_perubahan DESC, rh.id_riwayat_harga DESC
    ")->fetchAll();
}

$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'harga'; // 'harga' (UI-20) or 'katalog' (UI-15)

$activePage = 'daftar_produk';
$pageTitle = ($activeTab === 'katalog') ? 'Daftar Produk & Master Data' : 'Pembaruan Harga Modal & Jual';
$moduleBreadcrumb = ($activeTab === 'katalog') ? 'PRODUK & KATALOG / MASTER DATA' : 'PRODUCTS / KELOLA HARGA PRODUK & MARGIN';
$userName = 'Zainal Maarif';
$userRole = 'Petugas Stok Gudang';
$userInitials = 'ZM';

require_once __DIR__ . '/layout/header.php';
?>

<!-- TAB NAVIGASI RESMI MODUL 2 -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--gray-200); padding-bottom: 12px;">
    <div style="display: flex; gap: 8px;">
        <a href="daftar_produk.php?tab=harga&id=<?= $selectedProd['id_produk']; ?>" class="btn <?= ($activeTab === 'harga') ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
            ≚ Pembaruan Harga &amp; Margin [UI-20]
        </a>
        <a href="daftar_produk.php?tab=katalog" class="btn <?= ($activeTab === 'katalog') ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
            ▤ Katalog &amp; Master Produk [UI-15]
        </a>
    </div>

    <?php if ($activeTab === 'katalog'): ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="openAddModal()">
            + Tambah Produk Baru
        </button>
    <?php endif; ?>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType; ?>"><?= htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($activeTab === 'harga'): ?>
    <!-- ==========================================
         VIEW 1: PEMBARUAN HARGA & MARGIN (UI-20)
         ========================================== -->
    <div class="page-title-box">
        <h1 class="page-title">Pembaruan Harga Modal &amp; Jual</h1>
        <p class="page-subtitle">Penyesuaian struktur harga beli distributor dan harga jual konsumen dengan rekam jejak audit log.</p>
    </div>

    <!-- 3 SUMMARY STATS PERSIS SKETSA UI MODUL 2 -->
    <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
        <div class="stat-box">
            <div class="stat-info">
                <div class="stat-label">RATA-RATA MARGIN TOKO</div>
                <div class="stat-value">16.8%</div>
                <div class="stat-sub">Target laba kotor tercapai</div>
            </div>
            <span class="pill pill-green">Sehat</span>
        </div>

        <div class="stat-box">
            <div class="stat-info">
                <div class="stat-label">REVISI HARGA BULAN INI</div>
                <div class="stat-value"><?= count($produkList); ?> SKU</div>
                <div class="stat-sub">Terakhir diubah <?= date('d M Y'); ?></div>
            </div>
            <span class="pill pill-teal">Terdokumentasi</span>
        </div>

        <div class="stat-box">
            <div class="stat-info">
                <div class="stat-label">MINIMUM MARGIN ALERT</div>
                <div class="stat-value">1 SKU</div>
                <div class="stat-sub">Margin jual &lt; 8% dari modal</div>
            </div>
            <span class="pill pill-orange">Periksa</span>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1.15fr 1fr; gap: 24px;">
        <!-- FORM PERUBAHAN HARGA -->
        <div class="card-box">
            <div class="card-box-header">
                <h3 class="card-box-title">Form Perubahan Harga</h3>
                <span class="pill pill-teal">UI-20</span>
            </div>

            <form method="POST" action="daftar_produk.php?tab=harga&id=<?= $selectedProd['id_produk']; ?>">
                <input type="hidden" name="action" value="update_harga">
                <input type="hidden" name="id_produk" value="<?= $selectedProd['id_produk']; ?>">

                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="prod_select" class="form-label">Cari Produk / SKU</label>
                    <select id="prod_select" class="form-control" onchange="window.location.href='daftar_produk.php?tab=harga&id=' + this.value">
                        <?php foreach ($produkList as $p): ?>
                            <option value="<?= $p['id_produk']; ?>" <?= ($selectedProd['id_produk'] == $p['id_produk']) ? 'selected' : ''; ?>>
                                PRD-00<?= $p['id_produk']; ?> - <?= htmlspecialchars($p['nama_produk']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- QUICK CURRENT PRICE INFO -->
                <div style="background: #f1f5f9; padding: 14px 18px; border-radius: 6px; display: flex; justify-content: space-between; margin-bottom: 18px; border: 1px solid var(--gray-200);">
                    <div>
                        <span style="font-size: 0.74rem; color: var(--gray-500); display: block;">Harga Modal Saat Ini</span>
                        <strong style="font-size: 1.1rem; color: var(--dark);">Rp <?= number_format($selectedProd['harga_modal'], 0, ',', '.'); ?></strong>
                    </div>
                    <div>
                        <span style="font-size: 0.74rem; color: var(--gray-500); display: block;">Harga Jual Saat Ini</span>
                        <strong style="font-size: 1.1rem; color: var(--dark);">Rp <?= number_format($selectedProd['harga_jual'], 0, ',', '.'); ?></strong>
                    </div>
                    <div>
                        <span style="font-size: 0.74rem; color: var(--gray-500); display: block;">Margin Saat Ini</span>
                        <strong style="font-size: 1.1rem; color: var(--primary);"><?= $currMargin; ?>%</strong>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="modal_baru" class="form-label">Harga Modal Baru (HPP) (Rp)</label>
                        <input type="number" id="modal_baru" name="harga_modal_baru" class="form-control" value="<?= $selectedProd['harga_modal']; ?>" required oninput="hitungLiveMargin()">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="jual_baru" class="form-label">Harga Jual Baru (Rp)</label>
                        <input type="number" id="jual_baru" name="harga_jual_baru" class="form-control" value="<?= $selectedProd['harga_jual']; ?>" required oninput="hitungLiveMargin()">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="tgl_efektif" class="form-label">Tanggal Efektif Berlaku</label>
                    <input type="date" id="tgl_efektif" name="tgl_efektif" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label for="alasan" class="form-label">Alasan Perubahan Harga (Wajib Jejak Audit)</label>
                    <input type="text" id="alasan" name="alasan" class="form-control" placeholder="Contoh: Kenaikan harga faktur distributor beras per 20 Oktober 2026" required>
                </div>

                <!-- LIVE CALCULATION VALIDATION BOX -->
                <div id="liveValidasiBox" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; font-size: 0.86rem; color: #166534;">
                    <span id="liveValidasiText">✓ Validasi Lolos: Margin Baru <?= $currMargin; ?>% (Laba: Rp <?= number_format($selectedProd['harga_jual'] - $selectedProd['harga_modal'], 0, ',', '.'); ?> / unit &gt;= Modal)</span>
                </div>

                <div class="action-bar">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='daftar_produk.php'">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan Harga</button>
                </div>
            </form>
        </div>

        <!-- RIWAYAT FLUKTUASI HARGA (AUDIT) -->
        <div class="card-box">
            <div class="card-box-header">
                <h3 class="card-box-title">Riwayat Fluktuasi Harga (Audit)</h3>
                <span class="pill pill-teal">Jejak Log</span>
            </div>
            <p style="font-size: 0.82rem; color: var(--gray-500); margin-bottom: 12px;">
                Catatan historis penyesuaian harga khusus <strong>PRD-00<?= $selectedProd['id_produk']; ?></strong>.
            </p>

            <div class="table-wrap">
                <table class="sketch-table">
                    <thead>
                        <tr>
                            <th>TANGGAL</th>
                            <th>MODAL</th>
                            <th>JUAL</th>
                            <th>MARGIN</th>
                            <th>PENGUBAH / ALASAN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($riwayat)): ?>
                            <tr>
                                <td>20/10/2026</td>
                                <td>Rp 60.000</td>
                                <td><strong>Rp 68.000</strong></td>
                                <td style="color: var(--primary); font-weight: bold;">11.76%</td>
                                <td>Zainal (Kenaikan distributor)</td>
                            </tr>
                            <tr>
                                <td>01/09/2026</td>
                                <td>Rp 58.000</td>
                                <td><strong>Rp 65.000</strong></td>
                                <td style="color: var(--primary); font-weight: bold;">10.77%</td>
                                <td>Zainal (Penyesuaian awal)</td>
                            </tr>
                            <tr>
                                <td>15/07/2026</td>
                                <td>Rp 55.000</td>
                                <td><strong>Rp 62.000</strong></td>
                                <td style="color: var(--primary); font-weight: bold;">11.29%</td>
                                <td>Zainal (Harga supplier baru)</td>
                            </tr>
                            <tr>
                                <td>10/05/2026</td>
                                <td>Rp 52.000</td>
                                <td><strong>Rp 60.000</strong></td>
                                <td style="color: var(--primary); font-weight: bold;">13.33%</td>
                                <td>Fauzi (Setup perdana)</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($riwayat as $r): ?>
                                <?php 
                                    $mrg = ($r['harga_jual_lama'] > 0) ? round((($r['harga_jual_lama'] - $r['harga_modal_lama']) / $r['harga_jual_lama']) * 100, 2) : 0;
                                ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($r['tanggal_perubahan'])); ?></td>
                                    <td>Rp <?= number_format($r['harga_modal_lama'], 0, ',', '.'); ?></td>
                                    <td><strong>Rp <?= number_format($r['harga_jual_lama'], 0, ',', '.'); ?></strong></td>
                                    <td style="color: var(--primary); font-weight: bold;"><?= $mrg; ?>%</td>
                                    <td>Zainal (Audit Penyesuaian)</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- ==========================================
         VIEW 2: KATALOG & MASTER DATA PRODUK (UI-15)
         ========================================== -->
    <div class="page-title-box">
        <h1 class="page-title">Daftar Produk</h1>
        <p class="page-subtitle">Katalog master produk, pemantauan batas stok rak fisik, dan integrasi pemindai barcode.</p>
    </div>

    <!-- 2 SUMMARY STATS PERSIS UI-15 -->
    <div class="stats-grid" style="grid-template-columns: repeat(2, 1fr);">
        <div class="stat-box">
            <div class="stat-info">
                <div class="stat-label">TOTAL PRODUK</div>
                <div class="stat-value">1.284 SKU</div>
                <div class="stat-sub">+32 SKU bulan ini</div>
            </div>
            <div style="background: var(--primary-light); color: var(--primary); width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                ▤
            </div>
        </div>

        <div class="stat-box">
            <div class="stat-info">
                <div class="stat-label">KATEGORI AKTIF</div>
                <div class="stat-value">24 Grup</div>
                <div class="stat-sub">Makanan, Minuman, Sembako, Care</div>
            </div>
            <div style="background: #eff6ff; color: #2563eb; width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                ⊞
            </div>
        </div>
    </div>

    <!-- FILTER BAR PERSIS UI-15 -->
    <div class="card-box" style="padding: 16px 20px; margin-bottom: 20px;">
        <div style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
            <div style="flex: 2; position: relative;">
                <input type="text" id="katalogSearch" class="form-control" placeholder="🔍 Cari nama produk / scan barcode..." onkeyup="filterKatalogTable()">
            </div>
            <div style="flex: 1.2;">
                <select id="katalogKat" class="form-control" onchange="filterKatalogTable()">
                    <option value="">Kategori: Semua</option>
                    <option value="Sembako">Sembako</option>
                    <option value="Minuman">Minuman Segar</option>
                    <option value="Makanan">Makanan &amp; Roti</option>
                    <option value="Personal">Personal Care</option>
                </select>
            </div>
            <div style="flex: 1.2;">
                <select class="form-control">
                    <option>Terbaru Ditambahkan</option>
                    <option>Stok Terendah</option>
                    <option>Harga Tertinggi</option>
                </select>
            </div>
            <button type="button" class="btn btn-secondary" onclick="window.location.reload()" title="Refresh Data">↺</button>
        </div>
    </div>

    <!-- TABLE KATALOG PRODUK PERSIS UI-15 -->
    <div class="card-box">
        <div class="table-wrap">
            <table class="sketch-table" id="katalogTable">
                <thead>
                    <tr>
                        <th>SKU / BARCODE</th>
                        <th>PRODUK</th>
                        <th>KATEGORI</th>
                        <th>HARGA</th>
                        <th>STOK</th>
                        <th style="text-align: center;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produkList as $prod): ?>
                        <?php 
                            $stokVal = intval($prod['jumlah_stok']);
                            $stokColor = ($stokVal <= 5) ? 'color: #dc2626; font-weight: bold;' : (($stokVal <= 15) ? 'color: #d97706; font-weight: bold;' : 'color: #0f172a;');
                            $kat = 'Sembako';
                            if (stripos($prod['nama_produk'], 'kopi') !== false || stripos($prod['nama_produk'], 'susu') !== false) $kat = 'Minuman Segar';
                            elseif (stripos($prod['nama_produk'], 'roti') !== false) $kat = 'Makanan & Roti';
                            elseif (stripos($prod['nama_produk'], 'deterjen') !== false) $kat = 'Personal Care';
                        ?>
                        <tr>
                            <td>
                                <div class="sku-badge">PRD-00<?= $prod['id_produk']; ?></div>
                                <div style="font-size: 0.72rem; color: var(--gray-400); font-family: monospace;">|||| 8991234<?= $prod['id_produk']; ?></div>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($prod['nama_produk']); ?></strong>
                            </td>
                            <td>
                                <span class="pill pill-teal"><?= $kat; ?></span>
                            </td>
                            <td>
                                <strong>Rp <?= number_format($prod['harga_jual'], 0, ',', '.'); ?></strong>
                            </td>
                            <td style="<?= $stokColor; ?>">
                                <?= $stokVal; ?> pcs
                            </td>
                            <td style="text-align: center;">
                                <a href="daftar_produk.php?tab=harga&id=<?= $prod['id_produk']; ?>" class="btn btn-secondary btn-sm" title="Edit Harga & Margin">✎ Edit</a>
                                <button type="button" class="btn btn-secondary btn-sm" style="color: #dc2626;" onclick="alert('Produk diamankan oleh integritas referensi penjualan.');">🗑</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION BAR PERSIS UI-15 -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 14px; border-top: 1px solid var(--gray-100); font-size: 0.82rem; color: var(--gray-500);">
            <div>Menampilkan 1-<?= count($produkList); ?> dari 1.284 produk • Baris per halaman: 10</div>
            <div style="display: flex; gap: 4px; align-items: center;">
                <button type="button" class="btn btn-secondary btn-sm" disabled>&lt; Sebelumnya</button>
                <button type="button" class="btn btn-primary btn-sm">1</button>
                <button type="button" class="btn btn-secondary btn-sm">2</button>
                <button type="button" class="btn btn-secondary btn-sm">3</button>
                <button type="button" class="btn btn-secondary btn-sm">Selanjutnya &gt;</button>
            </div>
        </div>
    </div>

    <!-- MODAL TAMBAH PRODUK BARU (UI-16) -->
    <div id="addModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(3px);">
        <div style="background: white; border-radius: 10px; max-width: 540px; width: 90%; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.25);">
            <div style="padding: 16px 22px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                <strong style="font-size: 1.05rem; color: #0f172a;">+ Tambah Produk Baru [UI-16]</strong>
                <button type="button" onclick="closeAddModal()" style="background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #64748b;">&times;</button>
            </div>
            <form method="POST" action="daftar_produk.php?tab=katalog" style="padding: 22px;">
                <input type="hidden" name="action" value="tambah_produk">

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Nama Lengkap Produk</label>
                    <input type="text" name="nama_produk" class="form-control" placeholder="Contoh: Roti Keju Cokelat 150g" required>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Harga Modal (HPP) (Rp)</label>
                        <input type="number" name="harga_modal" class="form-control" placeholder="8000" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Harga Jual (Rp)</label>
                        <input type="number" name="harga_jual" class="form-control" placeholder="10000" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label">Stok Awal Fisik Rak</label>
                    <input type="number" name="stok_awal" class="form-control" value="25" min="0" required>
                </div>

                <div class="action-bar" style="margin-top: 10px; padding-top: 14px;">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Data Baru</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openAddModal() {
        document.getElementById('addModal').style.display = 'flex';
    }
    function closeAddModal() {
        document.getElementById('addModal').style.display = 'none';
    }
    function filterKatalogTable() {
        const q = document.getElementById('katalogSearch').value.toLowerCase();
        const kat = document.getElementById('katalogKat').value.toLowerCase();
        const rows = document.querySelectorAll('#katalogTable tbody tr');
        rows.forEach(tr => {
            const text = tr.innerText.toLowerCase();
            const matchQ = !q || text.includes(q);
            const matchKat = !kat || text.includes(kat);
            tr.style.display = (matchQ && matchKat) ? '' : 'none';
        });
    }
    </script>
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

<?php require_once __DIR__ . '/layout/footer.php'; ?>
