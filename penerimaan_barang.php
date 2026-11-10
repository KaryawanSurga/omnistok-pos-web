<?php
/**
 * View Antarmuka: Pencatatan Penerimaan Barang Fisik
 * Penulis: Zainal Maarif (ZM - 255150400111070)
 * Referensi UI SDD: UI-19 (Halaman Pencatatan Penerimaan Barang)
 * Kode UI: ZM-IM-001
 * Disinkronkan 100% dengan Sketsa UI Modul 2
 */

require_once __DIR__ . '/config/auth.php';
auth_require_login();

require_once __DIR__ . '/controllers/PenerimaanController.php';

$controller = new PenerimaanController();
$message = null;
$messageType = 'info';

// Proses Submit Penerimaan Barang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'simpan_penerimaan') {
    $id_pesanan = intval($_POST['id_pesanan']);
    $id_pemasok = intval($_POST['id_pemasok']);
    $nomor_nota = trim($_POST['nomor_nota']);
    $tgl_penerimaan = !empty($_POST['tanggal_penerimaan']) ? $_POST['tanggal_penerimaan'] : date('Y-m-d');

    $items = [];
    if (isset($_POST['produk_id']) && is_array($_POST['produk_id'])) {
        foreach ($_POST['produk_id'] as $idx => $prodId) {
            $qty = intval($_POST['jumlah_diterima'][$idx]);
            $exp = $_POST['tgl_exp'][$idx];
            if ($qty > 0) {
                $items[] = [
                    'id_produk' => $prodId,
                    'jumlah_diterima' => $qty,
                    'tgl_kadaluwarsa' => $exp
                ];
            }
        }
    }

    $res = $controller->prosesPenerimaan([
        'id_pesanan' => $id_pesanan,
        'id_pemasok' => $id_pemasok,
        'nomor_nota' => $nomor_nota,
        'tanggal_penerimaan' => $tgl_penerimaan,
        'items' => $items
    ]);

    $message = $res['message'];
    $messageType = $res['status'] ? 'success' : 'danger';
}

$daftarPesanan = $controller->ambilDaftarPesanan();
$selectedPO = isset($_GET['po_id']) ? intval($_GET['po_id']) : (!empty($daftarPesanan) ? $daftarPesanan[0]['id_pesanan'] : null);
$detailPO = $selectedPO ? $controller->ambilDetailPesanan($selectedPO) : [];
$riwayatPenerimaan = $controller->ambilRiwayatPenerimaan();

$activePage = 'penerimaan_barang';
$pageTitle = 'Pencatatan Penerimaan Barang';
$moduleBreadcrumb = 'INVENTORY / PENERIMAAN BARANG (GOODS RECEIPT)';
$userName = 'Zainal Maarif';
$userRole = 'Petugas Stok Gudang';
$userInitials = 'ZM';

require_once __DIR__ . '/layout/header.php';
?>

<div class="page-title-box">
    <h1 class="page-title">Penerimaan Barang dari Pemasok</h1>
    <p class="page-subtitle">Pencatatan faktur surat jalan, verifikasi kuantitas fisik layak/rusak, dan penambahan batch stok.</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType; ?>"><?= htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- 3 SUMMARY STATS PERSIS SKETSA UI MODUL 2 -->
<div class="stats-grid">
    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">PURCHASE ORDER AKTIF</div>
            <div class="stat-value"><?= count($daftarPesanan); ?> PO</div>
            <div class="stat-sub">Menunggu pengiriman supplier</div>
        </div>
        <span class="pill pill-teal">Siap Terima</span>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">PENERIMAAN HARI INI</div>
            <div class="stat-value">128 Unit</div>
            <div class="stat-sub">Total 4 dokumen surat jalan</div>
        </div>
        <span class="pill pill-green">+128 Unit</span>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">BARANG RUSAK / RETUR</div>
            <div class="stat-value">2 Unit</div>
            <div class="stat-sub">Perlu tindak lanjut retur vendor</div>
        </div>
        <span class="pill pill-red">Klaim Retur</span>
    </div>
</div>

<!-- FORMULIR INPUT PENERIMAAN PERSIS SKETSA UI -->
<div class="card-box">
    <?php if ($selectedPO && !empty($detailPO)): ?>
        <?php 
            $curPO = array_filter($daftarPesanan, function($p) use ($selectedPO) {
                return $p['id_pesanan'] == $selectedPO;
            });
            $curPO = reset($curPO);
        ?>
        <form method="POST" action="penerimaan_barang.php">
            <input type="hidden" name="action" value="simpan_penerimaan">
            <input type="hidden" name="id_pesanan" value="<?= $selectedPO; ?>">
            <input type="hidden" name="id_pemasok" value="<?= $curPO['id_pemasok']; ?>">

            <!-- TOP FILTER CONTROLS -->
            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label for="po_select">Pilih Referensi Pesanan (PO)</label>
                    <select id="po_select" name="po_id" class="form-control" onchange="window.location.href='penerimaan_barang.php?po_id=' + this.value">
                        <?php foreach ($daftarPesanan as $po): ?>
                            <option value="<?= $po['id_pesanan']; ?>" <?= ($selectedPO == $po['id_pesanan']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($po['nomor_pesanan']); ?> (<?= htmlspecialchars($po['nama_pemasok']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="flex: 2;">
                    <label for="nomor_nota">Nomor Nota / Surat Jalan Pemasok</label>
                    <input type="text" id="nomor_nota" name="nomor_nota" class="form-control" placeholder="SJ-88291/SUP" required>
                </div>

                <div class="form-group" style="flex: 1.5;">
                    <label for="tanggal_penerimaan">Tanggal Penerimaan</label>
                    <input type="date" id="tanggal_penerimaan" name="tanggal_penerimaan" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group" style="flex: 1.2;">
                    <label>Status Pengecekan</label>
                    <div style="padding-top: 6px;">
                        <span class="pill pill-green" style="font-size: 0.8rem; width: 100%; text-align: center;">Nota Unik / Valid</span>
                    </div>
                </div>
            </div>

            <!-- ITEM DETAIL TABLE -->
            <div class="table-wrap">
                <table class="sketch-table">
                    <thead>
                        <tr>
                            <th>KODE / SKU</th>
                            <th>NAMA PRODUK</th>
                            <th>QTY PO</th>
                            <th>QTY TERIMA</th>
                            <th style="text-align: center;">LAYAK (+)</th>
                            <th style="text-align: center;">RUSAK</th>
                            <th>NO. BATCH</th>
                            <th>KEDALUWARSA</th>
                            <th style="text-align: center;">STATUS FISIK</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detailPO as $idx => $row): ?>
                            <tr>
                                <td style="font-weight: 700; color: var(--primary);">PRD-00<?= $row['id_produk']; ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['nama_produk']); ?></strong>
                                    <input type="hidden" name="produk_id[]" value="<?= $row['id_produk']; ?>">
                                </td>
                                <td><?= $row['jumlah_pesanan']; ?> unit</td>
                                <td><?= $row['jumlah_pesanan']; ?> unit</td>
                                <td style="text-align: center;">
                                    <input type="number" name="jumlah_diterima[]" class="input-layak" value="<?= $row['jumlah_pesanan']; ?>" min="1" required>
                                </td>
                                <td style="text-align: center;">
                                    <input type="number" class="input-rusak" value="0" min="0">
                                </td>
                                <td>
                                    <input type="text" class="form-control" style="width: 120px; font-size: 0.82rem; padding: 5px 8px;" value="BTH-<?= date('Ym') . '-A' . ($idx+1); ?>">
                                </td>
                                <td>
                                    <input type="date" name="tgl_exp[]" class="form-control" style="width: 135px; font-size: 0.82rem; padding: 5px 8px;" value="<?= date('Y-m-d', strtotime('+1 year')); ?>" required>
                                </td>
                                <td style="text-align: center;">
                                    <span class="pill pill-green" style="font-size: 0.72rem;">Semua Baik</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- POLICY NOTE -->
            <div class="policy-box">
                <span>ⓘ</span>
                <span><strong>Kebijakan Stok:</strong> Hanya kuantitas <strong>'LAYAK'</strong> yang otomatis menambah saldo rak gudang dan membukukan Mutasi Masuk.</span>
            </div>

            <!-- ACTION BUTTONS -->
            <div class="action-bar">
                <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">Batal</button>
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">Cetak Tanda Terima</button>
                <button type="submit" class="btn btn-primary">Simpan Penerimaan</button>
            </div>
        </form>
    <?php else: ?>
        <p style="text-align: center; color: var(--gray-500); padding: 30px;">
            Semua Purchase Order telah selesai diterima atau tidak ada PO aktif saat ini.
        </p>
    <?php endif; ?>
</div>

<!-- RIWAYAT PENERIMAAN -->
<div class="card-box">
    <div class="card-box-header">
        <h3 class="card-box-title">Riwayat Dokumen Penerimaan Barang Gudang</h3>
        <span class="pill pill-teal"><?= count($riwayatPenerimaan); ?> Dokumen</span>
    </div>
    <div class="table-wrap">
        <table class="sketch-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>NOMOR NOTA</th>
                    <th>NAMA PEMASOK</th>
                    <th>NOMOR PO</th>
                    <th>TANGGAL PENERIMAAN</th>
                    <th>TOTAL DITERIMA</th>
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($riwayatPenerimaan)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--gray-400);">Belum ada riwayat dokumen penerimaan.</td></tr>
                <?php else: ?>
                    <?php foreach ($riwayatPenerimaan as $rp): ?>
                        <tr>
                            <td>#<?= $rp['id_penerimaan']; ?></td>
                            <td><strong><?= htmlspecialchars($rp['nomor_nota']); ?></strong></td>
                            <td><?= htmlspecialchars($rp['nama_pemasok']); ?></td>
                            <td><?= htmlspecialchars($rp['nomor_pesanan']); ?></td>
                            <td><?= $rp['tanggal_penerimaan']; ?></td>
                            <td><strong><?= $rp['total_qty']; ?> unit</strong> (<?= $rp['total_item']; ?> item)</td>
                            <td><span class="pill pill-green">Selesai Masuk Rak</span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
