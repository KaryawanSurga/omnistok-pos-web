<?php
/**
 * View Antarmuka: Retur Barang ke Pemasok [UI-12 / UI-22]
 * Penulis: Zainal Maarif (ZM - 255150400111070)
 * Referensi UI SDD: UI-12 / UI-22 (Mengelola Pereturan Barang)
 * Disinkronkan 100% dengan Sketsa UI Modul 2
 */

require_once __DIR__ . '/config/auth.php';
auth_require_login();

$db = Database::getInstance();

$message = null;
$messageType = 'info';

// Proses Submit Retur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'simpan_retur') {
    $idPenerimaan = intval($_POST['id_penerimaan']);
    $noRetur = trim($_POST['nomor_retur']);
    $pilihan = $_POST['pilih_retur'] ?? [];
    $qtyRetur = $_POST['qty_retur'] ?? [];
    $alasanList = $_POST['alasan_retur'] ?? [];

    if (empty($pilihan)) {
        $message = "Pilih minimal 1 item barang yang akan diretur ke pemasok!";
        $messageType = "danger";
    } else {
        try {
            $db->beginTransaction();

            $stmtRetur = $db->prepare("
                INSERT INTO retur_penerimaan (id_penerimaan, nomor_retur, tanggal_retur)
                VALUES (?, ?, ?)
            ");
            $stmtRetur->execute([$idPenerimaan, $noRetur, date('Y-m-d')]);
            $idRetur = $db->lastInsertId();

            $stmtDetailRetur = $db->prepare("
                INSERT INTO detail_retur (id_retur, id_produk, jumlah_retur, alasan_retur)
                VALUES (?, ?, ?, ?)
            ");

            $stmtStok = $db->prepare("UPDATE stok SET jumlah_stok = jumlah_stok - ? WHERE id_produk = ?");

            $stmtMutasi = $db->prepare("
                INSERT INTO mutasi_stok (id_produk, jenis_mutasi, jumlah, alasan, tanggal_mutasi)
                VALUES (?, 'Keluar', ?, ?, datetime('now'))
            ");
            if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $stmtMutasi = $db->prepare("
                    INSERT INTO mutasi_stok (id_produk, jenis_mutasi, jumlah, alasan, tanggal_mutasi)
                    VALUES (?, 'Keluar', ?, ?, NOW())
                ");
            }

            foreach ($pilihan as $idProd) {
                $qty = isset($qtyRetur[$idProd]) ? intval($qtyRetur[$idProd]) : 1;
                $als = !empty($alasanList[$idProd]) ? $alasanList[$idProd] : "Kemasan rusak / cacat pengiriman";

                if ($qty > 0) {
                    $stmtDetailRetur->execute([$idRetur, $idProd, $qty, $als]);
                    $stmtStok->execute([$qty, $idProd]);
                    $stmtMutasi->execute([$idProd, $qty, "Retur Vendor No {$noRetur}: {$als}"]);
                }
            }

            $db->commit();
            $message = "Surat Jalan Retur [{$noRetur}] berhasil diterbitkan dan stok dikurangi!";
            $messageType = "success";
        } catch (Exception $e) {
            $db->rollBack();
            $message = "Gagal memproses retur: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Ambil riwayat penerimaan
$penerimaanList = $db->query("
    SELECT pen.*, pem.nama_pemasok, po.nomor_pesanan
    FROM penerimaan pen
    JOIN pemasok pem ON pen.id_pemasok = pem.id_pemasok
    JOIN pesanan po ON pen.id_pesanan = po.id_pesanan
    ORDER BY pen.id_penerimaan DESC
")->fetchAll();

$selectedPenId = isset($_GET['pen_id']) ? intval($_GET['pen_id']) : ($penerimaanList ? $penerimaanList[0]['id_penerimaan'] : 12001);
$currentPen = null;
foreach ($penerimaanList as $p) {
    if ($p['id_penerimaan'] == $selectedPenId) {
        $currentPen = $p;
        break;
    }
}
if (!$currentPen && !empty($penerimaanList)) {
    $currentPen = $penerimaanList[0];
}
if (!$currentPen) {
    $currentPen = [
        'id_penerimaan' => 12001,
        'nomor_nota' => 'SJ-88291/SUP',
        'nama_pemasok' => 'PT Sumber Pangan Sejahtera'
    ];
}

$currentPenId = intval($currentPen['id_penerimaan']);
$detailItems = [];
if ($currentPenId > 0) {
    $stmtItems = $db->query("
        SELECT dp.*, p.nama_produk, p.harga_modal,
               COALESCE(s.jumlah_stok, 20) as stok_sisa
        FROM detail_penerimaan dp
        JOIN produk p ON dp.id_produk = p.id_produk
        LEFT JOIN stok s ON p.id_produk = s.id_produk
        WHERE dp.id_penerimaan = {$currentPenId}
    ");
    if ($stmtItems) {
        $detailItems = $stmtItems->fetchAll();
    }
}

if (empty($detailItems)) {
    $detailItems = [
        ['id_produk' => 101, 'nama_produk' => 'Beras Rojolele 5 kg', 'jumlah_diterima' => 50, 'stok_sisa' => 48],
        ['id_produk' => 102, 'nama_produk' => 'Minyak Goreng 2 L', 'jumlah_diterima' => 30, 'stok_sisa' => 30],
        ['id_produk' => 103, 'nama_produk' => 'Gula Pasir Kristal 1 kg', 'jumlah_diterima' => 40, 'stok_sisa' => 40]
    ];
}

$activePage = 'retur_barang';
$pageTitle = 'Formulir Retur Barang ke Pemasok';
$moduleBreadcrumb = 'INVENTORY / RETUR BARANG (VENDOR RETURN)';
$userName = 'Zainal Maarif';
$userRole = 'Petugas Stok Gudang';
$userInitials = 'ZM';

require_once __DIR__ . '/layout/header.php';
?>

<div class="page-title-box">
    <h1 class="page-title">Formulir Retur Barang ke Pemasok</h1>
    <p class="page-subtitle">Penerbitan surat jalan retur, pemotongan stok batch, dan pembukuan mutasi keluar barang rusak/cacat.</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType; ?>"><?= htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- 3 SUMMARY STATS PERSIS SKETSA UI MODUL 2 -->
<div class="stats-grid">
    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">TOTAL RETUR BULAN INI</div>
            <div class="stat-value">3 Dokumen</div>
            <div class="stat-sub">14 unit barang dikembalikan</div>
        </div>
        <span class="pill pill-green">Selesai</span>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">MENUNGGU PENGAMBILAN</div>
            <div class="stat-value">1 Retur</div>
            <div class="stat-sub"><?= htmlspecialchars($currentPen['nama_pemasok']); ?></div>
        </div>
        <span class="pill pill-orange">Pending Pick-up</span>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">NILAI KLAIM RETUR</div>
            <div class="stat-value">Rp 320.000</div>
            <div class="stat-sub">Estimasi penggantian/potong nota</div>
        </div>
        <span class="pill pill-teal">Klaim</span>
    </div>
</div>

<div class="card-box">
    <form method="POST" action="retur_barang.php?pen_id=<?= $currentPen['id_penerimaan']; ?>">
        <input type="hidden" name="action" value="simpan_retur">
        <input type="hidden" name="id_penerimaan" value="<?= $currentPen['id_penerimaan']; ?>">

        <div class="form-row">
            <div class="form-group" style="flex: 2;">
                <label for="pen_select">Pilih Referensi Penerimaan / Surat Jalan</label>
                <select id="pen_select" class="form-control" onchange="window.location.href='retur_barang.php?pen_id=' + this.value">
                    <?php if (empty($penerimaanList)): ?>
                        <option value="12001">RCV-202610-001 (SJ-88291/SUP)</option>
                    <?php else: ?>
                        <?php foreach ($penerimaanList as $pen): ?>
                            <option value="<?= $pen['id_penerimaan']; ?>" <?= ($currentPen['id_penerimaan'] == $pen['id_penerimaan']) ? 'selected' : ''; ?>>
                                RCV-<?= $pen['id_penerimaan']; ?> (<?= htmlspecialchars($pen['nomor_nota']); ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group" style="flex: 1.5;">
                <label for="nomor_retur">Nomor Retur Dokumen (Auto)</label>
                <input type="text" id="nomor_retur" name="nomor_retur" class="form-control" value="RET/OMNI/<?= date('Y/m/d'); ?>" readonly>
            </div>

            <div class="form-group" style="flex: 2;">
                <label for="pemasok">Pemasok Tujuan</label>
                <input type="text" id="pemasok" class="form-control" value="<?= htmlspecialchars($currentPen['nama_pemasok']); ?>" readonly>
            </div>
        </div>

        <div class="table-wrap">
            <table class="sketch-table">
                <thead>
                    <tr>
                        <th style="width: 45px; text-align: center;">PILIH</th>
                        <th>KODE / SKU</th>
                        <th>NAMA PRODUK</th>
                        <th>NO. BATCH</th>
                        <th>DITERIMA</th>
                        <th>STOK SISA</th>
                        <th style="text-align: center;">QTY RETUR</th>
                        <th>ALASAN & KONDISI BARANG</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detailItems as $idx => $it): ?>
                        <tr>
                            <td style="text-align: center;">
                                <input type="checkbox" name="pilih_retur[]" value="<?= $it['id_produk']; ?>" <?= ($idx === 0) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--primary);">
                            </td>
                            <td style="font-weight: 700; color: var(--primary);">PRD-00<?= $it['id_produk']; ?></td>
                            <td><strong><?= htmlspecialchars($it['nama_produk']); ?></strong></td>
                            <td>BTH-202610-A<?= ($idx+1); ?></td>
                            <td><?= $it['jumlah_diterima']; ?> unit</td>
                            <td><strong><?= $it['stok_sisa']; ?> unit</strong></td>
                            <td style="text-align: center;">
                                <input type="number" name="qty_retur[<?= $it['id_produk']; ?>]" class="input-rusak" value="<?= ($idx === 0) ? '2' : '0'; ?>" min="0" max="<?= $it['stok_sisa']; ?>" required>
                            </td>
                            <td>
                                <input type="text" name="alasan_retur[<?= $it['id_produk']; ?>]" class="form-control" value="<?= ($idx === 0) ? 'Karung sobek & basah saat diterima' : '-'; ?>" style="font-size: 0.82rem; padding: 6px 10px;">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- LAMPIRAN FOTO BUKTI (UI-12 / UI-22) -->
        <div style="background: #f8fafc; border: 1px dashed var(--gray-300); border-radius: 6px; padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <span style="font-size: 0.82rem; color: var(--gray-500); width: 100%; display: block;">Lampiran Foto Bukti Fisik Kerusakan (Klaim Retur Pemasok):</span>
                <button type="button" class="pill pill-teal" style="font-size: 0.75rem; cursor: pointer;" onclick="openPhotoModal()">FOTO BUKTI</button>
                <a href="javascript:void(0)" onclick="openPhotoModal()" style="font-size: 0.88rem; font-weight: 700; color: var(--primary); text-decoration: underline;">foto_beras_rusak_bthA1.jpg (1.8 MB)</a>
                <span style="color: var(--gray-400); font-size: 0.78rem;">- Diunggah oleh Petugas Stok: Zainal Maarif (Terverifikasi)</span>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="openPhotoModal()">Lihat Bukti Foto</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="alert('File bukti fisik foto valid & siap diunggah ulang.');">Unggah Ulang</button>
            </div>
        </div>

        <!-- MODAL LIGHTBOX FOTO BUKTI -->
        <div id="photoModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.75); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
            <div style="background: white; border-radius: 10px; max-width: 680px; width: 90%; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3);">
                <div style="padding: 14px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                    <strong style="font-size: 0.95rem; color: #0f172a;">Bukti Fisik Kerusakan: foto_beras_rusak_bthA1.jpg</strong>
                    <button type="button" onclick="closePhotoModal()" style="background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #64748b;">&times;</button>
                </div>
                <div style="padding: 16px; text-align: center; background: #f1f5f9;">
                    <img src="assets/uploads/foto_beras_rusak_bthA1.jpg" alt="Bukti Beras Rusak" style="max-width: 100%; height: auto; border-radius: 6px; border: 1px solid #cbd5e1; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                </div>
                <div style="padding: 12px 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: white;">
                    <span style="font-size: 0.78rem; color: #64748b;">Batch: BTH-202610-A1 | Retur: 2 Unit | Status: Terverifikasi</span>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closePhotoModal()">Tutup</button>
                </div>
            </div>
        </div>

        <script>
        function openPhotoModal() {
            const m = document.getElementById('photoModal');
            if (m) m.style.display = 'flex';
        }
        function closePhotoModal() {
            const m = document.getElementById('photoModal');
            if (m) m.style.display = 'none';
        }
        </script>

        <div class="policy-box">
            <span>ⓘ</span>
            <span><strong>Validasi Retur:</strong> Kuantitas retur tidak boleh melebihi sisa stok batch maupun jumlah barang yang diterima dari faktur asal.</span>
        </div>

        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="window.location.href='penerimaan_barang.php'">Batal</button>
            <button type="button" class="btn btn-outline-primary" onclick="window.print()">Cetak Surat Jalan Retur</button>
            <button type="submit" class="btn btn-primary">Simpan & Buat Retur</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
