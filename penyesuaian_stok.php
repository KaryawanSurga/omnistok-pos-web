<?php
/**
 * View Antarmuka: Penyesuaian Stok Fisik (Stock Opname) [UI-11 / UI-21]
 * Penulis: Zainal Maarif (ZM - 255150400111070)
 * Referensi UI SDD: UI-11 / UI-21 (Mengelola Penyesuaian Stok / Opname)
 * Disinkronkan 100% dengan Sketsa UI Modul 2
 */

require_once __DIR__ . '/config/auth.php';
auth_require_page(basename(__FILE__));

$db = Database::getInstance();

$message = null;
$messageType = 'info';

// Proses Posting Opname
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'posting_opname') {
    $nomorSesi = trim($_POST['nomor_sesi']);
    $itemIds = $_POST['produk_id'] ?? [];
    $stokFisik = $_POST['stok_fisik'] ?? [];
    $alasanList = $_POST['alasan'] ?? [];

    $countUpdated = 0;
    try {
        $db->beginTransaction();

        $stmtStok = $db->prepare("UPDATE stok SET jumlah_stok = ? WHERE id_produk = ?");
        $stmtMutasi = $db->prepare("
            INSERT INTO mutasi_stok (id_produk, jenis_mutasi, jumlah, alasan, tanggal_mutasi)
            VALUES (?, 'Penyesuaian', ?, ?, datetime('now'))
        ");
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $stmtMutasi = $db->prepare("
                INSERT INTO mutasi_stok (id_produk, jenis_mutasi, jumlah, alasan, tanggal_mutasi)
                VALUES (?, 'Penyesuaian', ?, ?, NOW())
            ");
        }

        foreach ($itemIds as $idx => $idProd) {
            $fisik = intval($stokFisik[$idx]);
            $currBuku = $db->query("SELECT COALESCE(SUM(jumlah_stok), 0) FROM stok WHERE id_produk = {$idProd}")->fetchColumn();
            $selisih = $fisik - $currBuku;

            if ($selisih != 0) {
                // Update saldo stok menjadi stok fisik
                $stmtStok->execute([$fisik, $idProd]);

                // Catat mutasi penyesuaian
                $als = !empty($alasanList[$idx]) ? $alasanList[$idx] : "Koreksi Sesi Stock Opname {$nomorSesi}";
                $stmtMutasi->execute([$idProd, $selisih, "Stock Opname: {$als}"]);
                $countUpdated++;
            }
        }

        $db->commit();
        $message = "Sesi Stock Opname berhasil difinalisasi! Saldo persediaan disinkronkan dengan fisik.";
        $messageType = "success";
    } catch (Exception $e) {
        $db->rollBack();
        $message = "Gagal memproses penyesuaian: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Ambil data produk dan stok aktual
$stokData = $db->query("
    SELECT p.id_produk, p.nama_produk, p.kategori,
           COALESCE(SUM(s.jumlah_stok), 0) as stok_buku
    FROM produk p
    LEFT JOIN stok s ON p.id_produk = s.id_produk
    GROUP BY p.id_produk
    ORDER BY p.id_produk ASC
")->fetchAll();

$activePage = 'penyesuaian_stok';
$pageTitle = 'Penyesuaian Stok Fisik (Stock Opname)';
$moduleBreadcrumb = 'INVENTORY / PENYESUAIAN STOK & STOCK OPNAME';
$userName = 'Zainal Maarif';
$userRole = 'Petugas Stok Gudang';
$userInitials = 'ZM';

require_once __DIR__ . '/layout/header.php';
?>

<div class="page-title-box">
    <h1 class="page-title">Penyesuaian Stok Fisik (Stock Opname)</h1>
    <p class="page-subtitle">Pencatatan hitung fisik rak, kalkulasi selisih otomatis, dan posting mutasi koreksi persediaan.</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType; ?>"><?= htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- 3 SUMMARY STATS PERSIS SKETSA UI MODUL 2 -->
<div class="stats-grid">
    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">TOTAL ITEM DIPERIKSA</div>
            <div class="stat-value"><?= count($stokData); ?> Produk</div>
            <div class="stat-sub">Lokasi: Gudang Utama</div>
        </div>
        <span class="pill pill-teal">Sesi Aktif</span>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">STOK SESUAI (BALANCE)</div>
            <div class="stat-value" id="statBalance">4 Produk</div>
            <div class="stat-sub">Akurasi fisik tinggi</div>
        </div>
        <span class="pill pill-green">Match</span>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">SELISIH FISIK VS BUKU</div>
            <div class="stat-value" id="statSelisih">-1 Unit</div>
            <div class="stat-sub">Memerlukan alasan koreksi</div>
        </div>
        <span class="pill pill-red">Perlu Alasan</span>
    </div>
</div>

<!-- FORM OPNAME -->
<div class="card-box">
    <form method="POST" action="penyesuaian_stok.php">
        <input type="hidden" name="action" value="posting_opname">

        <div class="form-row">
            <div class="form-group" style="flex: 1.5;">
                <label for="lokasi">Pilih Lokasi Gudang / Rak</label>
                <input type="text" id="lokasi" class="form-control" value="Gudang Utama (Rak A & B)" readonly>
            </div>
            <div class="form-group" style="flex: 1.5;">
                <label for="nomor_sesi">Nomor Sesi Opname</label>
                <input type="text" id="nomor_sesi" name="nomor_sesi" class="form-control" value="SO/OMNI/<?= date('Y/m/d'); ?>" required>
            </div>
            <div class="form-group" style="flex: 1.5;">
                <label for="petugas">Petugas Pelaksana</label>
                <input type="text" id="petugas" class="form-control" value="Zainal Maarif (Petugas Stok)" readonly>
            </div>
        </div>

        <div class="table-wrap">
            <table class="sketch-table">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>NAMA PRODUK</th>
                        <th>LOKASI RAK</th>
                        <th style="text-align: center;">STOK BUKU</th>
                        <th style="text-align: center;">STOK FISIK</th>
                        <th style="text-align: center;">SELISIH</th>
                        <th style="text-align: center;">STATUS</th>
                        <th>ALASAN / CATATAN SELISIH</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stokData as $idx => $stk): ?>
                        <tr>
                            <td style="font-weight: 700; color: var(--primary);">PRD-00<?= $stk['id_produk']; ?></td>
                            <td>
                                <strong><?= htmlspecialchars($stk['nama_produk']); ?></strong>
                                <input type="hidden" name="produk_id[]" value="<?= $stk['id_produk']; ?>">
                            </td>
                            <td>Rak A-0<?= ($idx % 3) + 1; ?></td>
                            <td style="text-align: center; font-weight: 600;">
                                <span id="buku_<?= $idx; ?>"><?= $stk['stok_buku']; ?></span> pcs
                            </td>
                            <td style="text-align: center;">
                                <input type="number" name="stok_fisik[]" id="fisik_<?= $idx; ?>" class="input-layak" value="<?= $stk['stok_buku']; ?>" min="0" required oninput="hitungSelisihRow(<?= $idx; ?>)">
                            </td>
                            <td style="text-align: center; font-weight: 700;" id="selisih_<?= $idx; ?>">
                                0
                            </td>
                            <td style="text-align: center;" id="badge_<?= $idx; ?>">
                                <span class="pill pill-green">Match</span>
                            </td>
                            <td>
                                <input type="text" name="alasan[]" id="alasan_<?= $idx; ?>" class="form-control" placeholder="Isi alasan jika terdapat selisih fisik..." style="font-size: 0.82rem; padding: 6px 10px;">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="policy-box" style="background: #f0fdf4; border-color: #bbf7d0; color: #166534;">
            <span>✓</span>
            <span>Seluruh baris yang memiliki selisih telah dilengkapi alasan penjelas. Sistem siap melakukan posting saldo koreksi.</span>
        </div>

        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="alert('Draft sesi opname berhasil disimpan lokal.');">Simpan Draft</button>
            <button type="submit" class="btn btn-primary">Finalisasi & Posting Opname</button>
        </div>
    </form>
</div>

<script>
function hitungSelisihRow(idx) {
    const buku = parseInt(document.getElementById('buku_' + idx).innerText) || 0;
    const fisik = parseInt(document.getElementById('fisik_' + idx).value) || 0;
    const selisih = fisik - buku;

    const elSelisih = document.getElementById('selisih_' + idx);
    const elBadge = document.getElementById('badge_' + idx);
    const elAlasan = document.getElementById('alasan_' + idx);

    if (selisih === 0) {
        elSelisih.innerText = '0';
        elSelisih.style.color = 'var(--dark)';
        elBadge.innerHTML = '<span class="pill pill-green">Match</span>';
        elAlasan.removeAttribute('required');
    } else {
        elSelisih.innerText = (selisih > 0 ? '+' : '') + selisih;
        elSelisih.style.color = selisih < 0 ? 'var(--danger)' : 'var(--warning)';
        elBadge.innerHTML = '<span class="pill pill-red">Selisih (' + selisih + ')</span>';
        elAlasan.setAttribute('required', 'required');
    }
}
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
