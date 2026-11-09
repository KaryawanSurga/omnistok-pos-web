<?php
/**
 * View Antarmuka: Rekonsiliasi Kas Shift Kasir [UI-14]
 * Penulis: Ferdyansah (FF - 255150400111039)
 * Referensi UI SDD: UI-14 (Merekonsiliasi Kas - Cash Reconciliation & End of Shift)
 * Disinkronkan 100% dengan Sketsa UI Modul 2
 */

require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();

$message = null;
$messageType = 'info';

// Modal awal dan target kas sistem persis sketsa UI-14
$modalAwal = 500000;
$totalTunaiSistem = 4250000;
$totalQrisSistem = 4200000;
$kasSistemDiharapkan = $modalAwal + $totalTunaiSistem; // Rp 4.750.000

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'simpan_rekonsiliasi') {
    $kasFisik = floatval($_POST['kas_fisik']);
    $selisih = $kasFisik - $kasSistemDiharapkan;
    $catatan = trim($_POST['catatan'] ?? '');
    $status = ($selisih == 0) ? 'Match (Cocok)' : (($selisih < 0) ? 'Selisih Kurang (-Rp ' . number_format(abs($selisih), 0, ',', '.') . ')' : 'Selisih Lebih (+Rp ' . number_format($selisih, 0, ',', '.') . ')');

    try {
        $stmt = $db->prepare("
            INSERT INTO rekonsiliasi (id_transaksi, status, tanggal)
            VALUES (1001, ?, datetime('now'))
        ");
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $stmt = $db->prepare("
                INSERT INTO rekonsiliasi (id_transaksi, status, tanggal)
                VALUES (1001, ?, NOW())
            ");
        }
        $stmt->execute([$status]);

        $message = "Shift Kasir 01 berhasil ditutup dan Berita Acara Rekonsiliasi Kas telah diterbitkan!";
        $messageType = ($selisih == 0) ? "success" : "warning";
    } catch (Exception $e) {
        $message = "Gagal menyimpan rekonsiliasi: " . $e->getMessage();
        $messageType = "danger";
    }
}

$activePage = 'rekonsiliasi_kas';
$pageTitle = 'Rekonsiliasi Kas (Cash Reconciliation & End of Shift)';
$moduleBreadcrumb = 'POS & SALES / CASH MANAGEMENT';
$userName = 'Budi Santoso';
$userRole = 'Kasir Utama • Jakarta';
$userInitials = 'BS';

require_once __DIR__ . '/layout/header.php';
?>

<div class="page-title-box">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 class="page-title">Rekonsiliasi Kas (Cash Reconciliation &amp; End of Shift)</h1>
            <p class="page-subtitle">Penutupan register kasir, audit fisik uang per pecahan laci kasir, dan validasi selisih kas.</p>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="alert('Membuka riwayat rekap shift sebelumnya.');">
            ▤ Riwayat Tutup Kasir
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType; ?>"><?= htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- REGISTER STATUS BANNER (UI-14) -->
<div class="card-box" style="padding: 14px 20px; margin-bottom: 20px; background: #f8fafc; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
    <div style="display: flex; align-items: center; gap: 12px;">
        <div style="background: var(--primary); color: white; width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            🖩
        </div>
        <div>
            <div style="font-weight: 700; color: var(--dark); font-size: 1rem;">Register Kasir 01</div>
            <div style="font-size: 0.76rem; color: var(--gray-500);">Kasir Aktif: <strong>Budi Santoso</strong> (ID: POS-8821)</div>
        </div>
    </div>
    <div style="display: flex; align-items: center; gap: 18px;">
        <div>
            <span style="font-size: 0.72rem; color: var(--gray-400); display: block;">Durasi Operasional</span>
            <strong style="font-size: 0.95rem; color: var(--dark);" id="liveDuration">07j 42m 20d</strong>
        </div>
        <span class="pill pill-green">Status: Terbuka / Siap Rekonsiliasi</span>
    </div>
</div>

<!-- 4 SUMMARY CARDS PERSIS UI-14 -->
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 24px;">
    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">MODAL AWAL LACI</div>
            <div class="stat-value">Rp <?= number_format($modalAwal, 0, ',', '.'); ?></div>
            <div class="stat-sub">Uang kembalian awal</div>
        </div>
        <div style="background: var(--gray-100); width: 34px; height: 34px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
            💵
        </div>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">TOTAL PENJUALAN TUNAI</div>
            <div class="stat-value">Rp <?= number_format($totalTunaiSistem, 0, ',', '.'); ?></div>
            <div class="stat-sub">34 transaksi tunai tercatat</div>
        </div>
        <div style="background: #f0fdf4; color: #16a34a; width: 34px; height: 34px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
            🛒
        </div>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">TOTAL QRIS / NON-TUNAI</div>
            <div class="stat-value">Rp <?= number_format($totalQrisSistem, 0, ',', '.'); ?></div>
            <div class="stat-sub">108 transaksi digital</div>
        </div>
        <div style="background: #eff6ff; color: #2563eb; width: 34px; height: 34px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
            📱
        </div>
    </div>

    <div class="stat-box" style="background: var(--primary); color: white; border-color: var(--primary);">
        <div class="stat-info">
            <div class="stat-label" style="color: #ccfbf1;">SALDO KAS SISTEM DIHARAPKAN</div>
            <div class="stat-value" style="color: white;">Rp <?= number_format($kasSistemDiharapkan, 0, ',', '.'); ?></div>
            <div class="stat-sub" style="color: #99f6e4;">Wajib ada di laci fisik kasir</div>
        </div>
        <div style="background: rgba(255,255,255,0.2); width: 34px; height: 34px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
            ✓
        </div>
    </div>
</div>

<!-- DENOMINATION COUNTER & AUDIT PANELS -->
<div style="display: grid; grid-template-columns: 1.35fr 1fr; gap: 24px;">
    <!-- LEFT PANEL: PENGHITUNGAN FISIK PECAHAN -->
    <div class="card-box">
        <div class="card-box-header">
            <div>
                <h3 class="card-box-title">Penghitungan Fisik Uang Kas Laci</h3>
                <p style="font-size: 0.78rem; color: var(--gray-500); margin-top: 2px;">
                    Masukkan kuantitas lembar uang kertas dan keping koin yang dihitung secara aktual dari laci kasir.
                </p>
            </div>
            <span class="pill pill-teal">Form Laci</span>
        </div>

        <div class="table-wrap">
            <table class="sketch-table">
                <thead>
                    <tr>
                        <th>PECAHAN</th>
                        <th style="text-align: center; width: 140px;">JUMLAH LEMBAR / KOIN</th>
                        <th style="text-align: right;">SUBTOTAL TERHITUNG</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Rp 100.000 -->
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="pill pill-red" style="font-size: 0.7rem; font-weight: 700;">100k</span>
                                <div>
                                    <strong>Rp 100.000</strong>
                                    <div style="font-size: 0.72rem; color: var(--gray-400);">Uang Kertas Merah</div>
                                </div>
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 6px;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="adjustCount('c100', -1)">-</button>
                                <input type="number" id="c100" class="form-control" value="32" min="0" style="width: 55px; text-align: center; padding: 4px;" oninput="recalcDenom()">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="adjustCount('c100', 1)">+</button>
                            </div>
                        </td>
                        <td style="text-align: right; font-weight: 700;" id="sub100">Rp 3.200.000</td>
                    </tr>

                    <!-- Rp 50.000 -->
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="pill pill-teal" style="font-size: 0.7rem; font-weight: 700;">50k</span>
                                <div>
                                    <strong>Rp 50.000</strong>
                                    <div style="font-size: 0.72rem; color: var(--gray-400);">Uang Kertas Biru</div>
                                </div>
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 6px;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="adjustCount('c50', -1)">-</button>
                                <input type="number" id="c50" class="form-control" value="18" min="0" style="width: 55px; text-align: center; padding: 4px;" oninput="recalcDenom()">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="adjustCount('c50', 1)">+</button>
                            </div>
                        </td>
                        <td style="text-align: right; font-weight: 700;" id="sub50">Rp 900.000</td>
                    </tr>

                    <!-- Rp 20.000 -->
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="pill pill-green" style="font-size: 0.7rem; font-weight: 700;">20k</span>
                                <div>
                                    <strong>Rp 20.000</strong>
                                    <div style="font-size: 0.72rem; color: var(--gray-400);">Uang Kertas Hijau</div>
                                </div>
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 6px;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="adjustCount('c20', -1)">-</button>
                                <input type="number" id="c20" class="form-control" value="15" min="0" style="width: 55px; text-align: center; padding: 4px;" oninput="recalcDenom()">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="adjustCount('c20', 1)">+</button>
                            </div>
                        </td>
                        <td style="text-align: right; font-weight: 700;" id="sub20">Rp 300.000</td>
                    </tr>

                    <!-- Rp 10.000 -->
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="pill pill-gray" style="font-size: 0.7rem; font-weight: 700; background: #f3e8ff; color: #7e22ce;">10k</span>
                                <div>
                                    <strong>Rp 10.000</strong>
                                    <div style="font-size: 0.72rem; color: var(--gray-400);">Uang Kertas Ungu</div>
                                </div>
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 6px;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="adjustCount('c10', -1)">-</button>
                                <input type="number" id="c10" class="form-control" value="20" min="0" style="width: 55px; text-align: center; padding: 4px;" oninput="recalcDenom()">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="adjustCount('c10', 1)">+</button>
                            </div>
                        </td>
                        <td style="text-align: right; font-weight: 700;" id="sub10">Rp 200.000</td>
                    </tr>

                    <!-- Rp 5.000 -->
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="pill pill-amber" style="font-size: 0.7rem; font-weight: 700;">5k</span>
                                <div>
                                    <strong>Rp 5.000</strong>
                                    <div style="font-size: 0.72rem; color: var(--gray-400);">Uang Kertas Cokelat</div>
                                </div>
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 6px;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="adjustCount('c5', -1)">-</button>
                                <input type="number" id="c5" class="form-control" value="14" min="0" style="width: 55px; text-align: center; padding: 4px;" oninput="recalcDenom()">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="adjustCount('c5', 1)">+</button>
                            </div>
                        </td>
                        <td style="text-align: right; font-weight: 700;" id="sub5">Rp 70.000</td>
                    </tr>

                    <!-- Rp 2.000 -->
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="pill pill-gray" style="font-size: 0.7rem; font-weight: 700;">2k</span>
                                <div>
                                    <strong>Rp 2.000</strong>
                                    <div style="font-size: 0.72rem; color: var(--gray-400);">Uang Kertas Abu-abu</div>
                                </div>
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 6px;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="adjustCount('c2', -1)">-</button>
                                <input type="number" id="c2" class="form-control" value="30" min="0" style="width: 55px; text-align: center; padding: 4px;" oninput="recalcDenom()">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="adjustCount('c2', 1)">+</button>
                            </div>
                        </td>
                        <td style="text-align: right; font-weight: 700;" id="sub2">Rp 60.000</td>
                    </tr>

                    <!-- Rp 1.000 -->
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="pill pill-gray" style="font-size: 0.7rem; font-weight: 700;">1k</span>
                                <div>
                                    <strong>Rp 1.000</strong>
                                    <div style="font-size: 0.72rem; color: var(--gray-400);">Kertas / Logam</div>
                                </div>
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 6px;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="adjustCount('c1', -1)">-</button>
                                <input type="number" id="c1" class="form-control" value="15" min="0" style="width: 55px; text-align: center; padding: 4px;" oninput="recalcDenom()">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="adjustCount('c1', 1)">+</button>
                            </div>
                        </td>
                        <td style="text-align: right; font-weight: 700;" id="sub1">Rp 15.000</td>
                    </tr>

                    <!-- Koin Rp 500 -->
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="pill pill-gray" style="font-size: 0.7rem; font-weight: 700;">Coin</span>
                                <div>
                                    <strong>Koin Receh</strong>
                                    <div style="font-size: 0.72rem; color: var(--gray-400);">Rp 500</div>
                                </div>
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 6px;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="adjustCount('c500', -1)">-</button>
                                <input type="number" id="c500" class="form-control" value="10" min="0" style="width: 55px; text-align: center; padding: 4px;" oninput="recalcDenom()">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="adjustCount('c500', 1)">+</button>
                            </div>
                        </td>
                        <td style="text-align: right; font-weight: 700;" id="sub500">Rp 5.000</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="background: #f1f5f9; padding: 14px 18px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; border: 1px solid var(--gray-200);">
            <strong style="font-size: 0.95rem; color: var(--dark);">Total Uang Fisik Terhitung di Laci:</strong>
            <strong style="font-size: 1.3rem; color: var(--primary);" id="totalFisikDisplay">Rp 4.750.000</strong>
        </div>
    </div>

    <!-- RIGHT PANEL: AUDIT SELISIH KASIR (UI-14) -->
    <div class="card-box">
        <div class="card-box-header">
            <h3 class="card-box-title">Audit Selisih Kasir</h3>
            <span class="pill pill-teal">End of Shift</span>
        </div>

        <form method="POST" action="rekonsiliasi_kas.php">
            <input type="hidden" name="action" value="simpan_rekonsiliasi">
            <input type="hidden" id="inputKasFisik" name="kas_fisik" value="4750000">

            <div style="margin-bottom: 16px; padding-bottom: 14px; border-bottom: 1px solid var(--gray-100);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 0.84rem; color: var(--gray-500);">Kas Sistem Diharapkan:</span>
                    <strong style="font-size: 0.95rem;">Rp <?= number_format($kasSistemDiharapkan, 0, ',', '.'); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="font-size: 0.84rem; color: var(--gray-500);">Kas Fisik Terhitung:</span>
                    <strong style="font-size: 0.95rem;" id="sideKasFisik">Rp 4.750.000</strong>
                </div>
            </div>

            <!-- STATUS SELISIH (VARIANCE) -->
            <div id="selisihBox" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                <div style="font-size: 0.74rem; font-weight: 700; color: var(--gray-500); text-transform: uppercase;">STATUS SELISIH (VARIANCE)</div>
                <div style="display: flex; justify-content: space-between; align-items: baseline; margin-top: 4px; margin-bottom: 6px;">
                    <div style="font-size: 1.6rem; font-weight: 800; color: #16a34a;" id="varianceAmount">Rp 0</div>
                    <span class="pill pill-green" id="varianceBadge">Cocok (Balance)</span>
                </div>
                <p style="font-size: 0.78rem; color: var(--gray-500);" id="varianceDesc">
                    Saldo fisik laci kasir cocok dengan rekap transaksi sistem POS.
                </p>
            </div>

            <button type="button" class="btn btn-outline-primary" style="width: 100%; margin-bottom: 18px;" onclick="recalcDenom()">
                Verifikasi / Hitung Ulang
            </button>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label">CATATAN KASIR / KETERANGAN SELISIH</label>
                <textarea name="catatan" class="form-control" rows="3" placeholder="Tuliskan catatan bila ada uang rusak, tips kasir, atau alasan selisih kas..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 0.95rem;">
                🖩 Simpan Selisih &amp; Tutup Shift
            </button>
        </form>
    </div>
</div>

<script>
const targetSistem = <?= $kasSistemDiharapkan; ?>;

function adjustCount(id, delta) {
    const inp = document.getElementById(id);
    let val = parseInt(inp.value) || 0;
    val = Math.max(0, val + delta);
    inp.value = val;
    recalcDenom();
}

function recalcDenom() {
    const v100 = (parseInt(document.getElementById('c100').value) || 0) * 100000;
    const v50 = (parseInt(document.getElementById('c50').value) || 0) * 50000;
    const v20 = (parseInt(document.getElementById('c20').value) || 0) * 20000;
    const v10 = (parseInt(document.getElementById('c10').value) || 0) * 10000;
    const v5 = (parseInt(document.getElementById('c5').value) || 0) * 5000;
    const v2 = (parseInt(document.getElementById('c2').value) || 0) * 2000;
    const v1 = (parseInt(document.getElementById('c1').value) || 0) * 1000;
    const v500 = (parseInt(document.getElementById('c500').value) || 0) * 500;

    document.getElementById('sub100').innerText = 'Rp ' + v100.toLocaleString('id-ID');
    document.getElementById('sub50').innerText = 'Rp ' + v50.toLocaleString('id-ID');
    document.getElementById('sub20').innerText = 'Rp ' + v20.toLocaleString('id-ID');
    document.getElementById('sub10').innerText = 'Rp ' + v10.toLocaleString('id-ID');
    document.getElementById('sub5').innerText = 'Rp ' + v5.toLocaleString('id-ID');
    document.getElementById('sub2').innerText = 'Rp ' + v2.toLocaleString('id-ID');
    document.getElementById('sub1').innerText = 'Rp ' + v1.toLocaleString('id-ID');
    document.getElementById('sub500').innerText = 'Rp ' + v500.toLocaleString('id-ID');

    const totalFisik = v100 + v50 + v20 + v10 + v5 + v2 + v1 + v500;
    document.getElementById('totalFisikDisplay').innerText = 'Rp ' + totalFisik.toLocaleString('id-ID');
    document.getElementById('sideKasFisik').innerText = 'Rp ' + totalFisik.toLocaleString('id-ID');
    document.getElementById('inputKasFisik').value = totalFisik;

    const diff = totalFisik - targetSistem;
    const box = document.getElementById('selisihBox');
    const amt = document.getElementById('varianceAmount');
    const badge = document.getElementById('varianceBadge');
    const desc = document.getElementById('varianceDesc');

    if (diff === 0) {
        box.style.background = '#f0fdf4';
        box.style.borderColor = '#bbf7d0';
        amt.style.color = '#16a34a';
        amt.innerText = 'Rp 0';
        badge.className = 'pill pill-green';
        badge.innerText = 'Cocok (Balance)';
        desc.innerText = 'Saldo fisik laci kasir cocok dengan rekap transaksi sistem POS.';
    } else if (diff < 0) {
        box.style.background = '#fef2f2';
        box.style.borderColor = '#fca5a5';
        amt.style.color = '#dc2626';
        amt.innerText = '-Rp ' + Math.abs(diff).toLocaleString('id-ID');
        badge.className = 'pill pill-red';
        badge.innerText = 'Terdapat Selisih Kurang';
        desc.innerText = 'Uang fisik laci kasir kurang Rp ' + Math.abs(diff).toLocaleString('id-ID') + ' dari target rekap sistem.';
    } else {
        box.style.background = '#fefce8';
        box.style.borderColor = '#fde047';
        amt.style.color = '#ca8a04';
        amt.innerText = '+Rp ' + diff.toLocaleString('id-ID');
        badge.className = 'pill pill-amber';
        badge.innerText = 'Terdapat Selisih Lebih';
        desc.innerText = 'Uang fisik laci kasir lebih Rp ' + diff.toLocaleString('id-ID') + ' dari target rekap sistem.';
    }
}

// Initial calc
recalcDenom();
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
