<?php
/**
 * View Antarmuka: Transaksi Penjualan Kasir POS [UI-11, UI-12, UI-13]
 * Penulis: Ferdyansah (FF - 255150400111039)
 * Referensi UI SDD: UI-11 (Mencatat Transaksi Penjualan), UI-12 (Pembayaran QRIS), UI-13 (Pembayaran Tunai)
 * Disinkronkan 100% dengan Sketsa UI Modul 2
 */

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/controllers/TransaksiController.php';
auth_require_page(basename(__FILE__));

$db  = Database::getInstance();
$trx = new TransaksiController();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = null;
$messageType = 'info';
$strukData = null;

// Pesan hasil aksi sebelumnya (pola Post/Redirect/Get agar transaksi tidak tersimpan ganda)
if (!empty($_SESSION['pos_flash'])) {
    $message = $_SESSION['pos_flash']['message'];
    $messageType = $_SESSION['pos_flash']['type'];
    unset($_SESSION['pos_flash']);
}

// Struk disimpan di session supaya tetap tampil setelah redirect / refresh
if (!empty($_SESSION['pos_struk'])) {
    $strukData = $_SESSION['pos_struk'];
    unset($_SESSION['pos_struk']);
}

// Keranjang awal diambil dari master produk agar harga & stok selalu sinkron dengan database
if (!isset($_SESSION['pos_cart'])) {
    $_SESSION['pos_cart'] = [];
    $produkAwal = $db->query("
        SELECT p.*, COALESCE(SUM(s.jumlah_stok), 0) AS stok
        FROM produk p
        LEFT JOIN stok s ON p.id_produk = s.id_produk
        GROUP BY p.id_produk
        ORDER BY p.id_produk ASC
        LIMIT 3
    ")->fetchAll();

    foreach ($produkAwal as $p) {
        $stokAwal = (int) $p['stok'];
        if ($stokAwal <= 0) {
            continue;
        }
        $_SESSION['pos_cart'][$p['id_produk']] = [
            'id_produk'   => (int) $p['id_produk'],
            'sku'         => 'PRD-00' . $p['id_produk'],
            'nama'        => $p['nama_produk'],
            'harga'       => (float) $p['harga_jual'],
            'harga_modal' => (float) $p['harga_modal'],
            'qty'         => 1,
            'diskon'      => 0,
            'stok'        => $stokAwal,
        ];
    }
}

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = $_POST['action'];

    if ($act === 'add_item') {
        $pId = intval($_POST['id_produk'] ?? 0);
        $stmtP = $db->prepare("
            SELECT p.*, COALESCE(SUM(s.jumlah_stok), 0) AS stok
            FROM produk p
            LEFT JOIN stok s ON p.id_produk = s.id_produk
            WHERE p.id_produk = ?
            GROUP BY p.id_produk
        ");
        $stmtP->execute([$pId]);
        $p = $stmtP->fetch();

        if ($p) {
            $stokTersedia = (int) $p['stok'];
            $qtySekarang  = isset($_SESSION['pos_cart'][$pId]) ? (int) $_SESSION['pos_cart'][$pId]['qty'] : 0;

            if ($qtySekarang + 1 > $stokTersedia) {
                $message = "Stok '{$p['nama_produk']}' tidak mencukupi (tersedia {$stokTersedia} pcs).";
                $messageType = 'warning';
            } else {
                if (isset($_SESSION['pos_cart'][$pId])) {
                    $_SESSION['pos_cart'][$pId]['qty']      = $qtySekarang + 1;
                    $_SESSION['pos_cart'][$pId]['stok']     = $stokTersedia;
                    $_SESSION['pos_cart'][$pId]['harga']    = (float) $p['harga_jual'];
                    $_SESSION['pos_cart'][$pId]['harga_modal'] = (float) $p['harga_modal'];
                } else {
                    $_SESSION['pos_cart'][$pId] = [
                        'id_produk'   => (int) $p['id_produk'],
                        'sku'         => 'PRD-00' . $p['id_produk'],
                        'nama'        => $p['nama_produk'],
                        'harga'       => (float) $p['harga_jual'],
                        'harga_modal' => (float) $p['harga_modal'],
                        'qty'         => 1,
                        'diskon'      => 0,
                        'stok'        => $stokTersedia,
                    ];
                }
                $message = "Produk '{$p['nama_produk']}' berhasil ditambahkan ke keranjang belanja!";
                $messageType = 'success';
            }
        } else {
            $message = 'Produk tidak ditemukan.';
            $messageType = 'danger';
        }
    } elseif ($act === 'update_qty') {
        $pId = intval($_POST['id_produk'] ?? 0);
        $delta = intval($_POST['delta'] ?? 0);
        if (isset($_SESSION['pos_cart'][$pId])) {
            $qtyBaru = (int) $_SESSION['pos_cart'][$pId]['qty'] + $delta;
            $stok    = (int) ($_SESSION['pos_cart'][$pId]['stok'] ?? 0);

            if ($qtyBaru <= 0) {
                unset($_SESSION['pos_cart'][$pId]);
            } elseif ($qtyBaru > $stok) {
                $_SESSION['pos_cart'][$pId]['qty'] = $stok;
                $message = "Jumlah '{$_SESSION['pos_cart'][$pId]['nama']}' dibatasi sesuai stok tersedia ({$stok} pcs).";
                $messageType = 'warning';
            } else {
                $_SESSION['pos_cart'][$pId]['qty'] = $qtyBaru;
            }
        }
    } elseif ($act === 'delete_item') {
        $pId = intval($_POST['id_produk']);
        unset($_SESSION['pos_cart'][$pId]);
    } elseif ($act === 'clear_cart') {
        $_SESSION['pos_cart'] = [];
        $message = "Keranjang transaksi berhasil dikosongkan.";
        $messageType = "info";
    } elseif ($act === 'checkout') {
        $metode = (isset($_POST['metode']) && strtoupper($_POST['metode']) === 'QRIS') ? 'QRIS' : 'Tunai';

        $subtotal = 0;
        $totalDiskon = 0;
        foreach ($_SESSION['pos_cart'] as $item) {
            $subtotal += ($item['harga'] * $item['qty']);
            $totalDiskon += ($item['diskon'] * $item['qty']);
        }
        $grandTotal = max(0, $subtotal - $totalDiskon);

        // QRIS selalu dibayar lunas sebesar total tagihan; tunai memakai nominal yang diisi kasir
        $bayar = ($metode === 'QRIS') ? $grandTotal : floatval($_POST['nominal_bayar'] ?? 0);

        if (empty($_SESSION['pos_cart'])) {
            $message = 'Keranjang transaksi masih kosong!';
            $messageType = 'danger';
        } else {
            // Akun kasir default; bila tidak ada, gunakan akun pertama yang tersedia
            $idKasir = (int) $db->query("SELECT id_akun FROM akun WHERE email = 'kasir@omnistok.com' LIMIT 1")->fetchColumn();
            if ($idKasir <= 0) {
                $idKasir = (int) $db->query("SELECT MIN(id_akun) FROM akun")->fetchColumn();
            }

            $items = [];
            $itemsStruk = [];
            foreach ($_SESSION['pos_cart'] as $it) {
                $items[] = [
                    'id_produk'   => $it['id_produk'],
                    'qty'         => $it['qty'],
                    'harga_jual'  => $it['harga'],
                    'harga_modal' => $it['harga_modal'] ?? null,
                    'diskon'      => $it['diskon'] ?? 0,
                ];
                $itemsStruk[] = $it;
            }

            $hasil = $trx->simpan_transaksi($idKasir, $items, $grandTotal, $metode, $bayar);

            if ($hasil['status']) {
                $_SESSION['pos_struk'] = [
                    'trx_id'    => 'TRX-' . date('Ymd') . '-' . str_pad($hasil['id_transaksi'], 4, '0', STR_PAD_LEFT),
                    'waktu'     => date('d/m/Y H:i:s'),
                    'kasir'     => 'Kasir Utama',
                    'metode'    => $hasil['metode_bayar'],
                    'items'     => $itemsStruk,
                    'subtotal'  => $subtotal,
                    'diskon'    => $totalDiskon,
                    'total'     => $grandTotal,
                    'bayar'     => $hasil['nominal_bayar'],
                    'kembalian' => $hasil['kembalian'],
                ];
                $_SESSION['pos_cart'] = [];
                $_SESSION['pos_flash'] = [
                    'message' => 'Transaksi POS berhasil diproses dan struk belanja telah tercetak!',
                    'type'    => 'success',
                ];

                header('Location: transaksi_pos.php');
                exit;
            }

            $message = $hasil['message'];
            $messageType = 'danger';
        }
    }
}

// Hitung subtotal cart saat ini
$cartSubtotal = 0;
$cartDiskon = 0;
$cartItemsCount = 0;
foreach ($_SESSION['pos_cart'] as $c) {
    $cartSubtotal += ($c['harga'] * $c['qty']);
    $cartDiskon += ($c['diskon'] * $c['qty']);
    $cartItemsCount += $c['qty'];
}
$cartGrandTotal = max(0, $cartSubtotal - $cartDiskon);

$produkList = $db->query("SELECT * FROM produk ORDER BY nama_produk ASC")->fetchAll();

$activePage = 'transaksi_pos';
$pageTitle = 'Transaksi Penjualan Kasir POS';
$moduleBreadcrumb = 'POS & SALES / TRANSAKSI PENJUALAN RETAIL';
$userName = 'Budi Santoso';
$userRole = 'Kasir Utama • Jakarta';
$userInitials = 'BS';

require_once __DIR__ . '/layout/header.php';
?>

<div class="page-title-box" style="margin-bottom: 16px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="background: var(--primary); color: white; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                🛒
            </div>
            <div>
                <h1 class="page-title" style="font-size: 1.35rem; margin-bottom: 0;">Menu Kasir (POS)</h1>
                <div style="font-size: 0.78rem; color: var(--gray-500);">
                    <span style="display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #16a34a; margin-right: 4px;"></span>
                    Kasir Aktif: <strong>Budi Santoso</strong> &bull; #TRX-<?= date('Ymd'); ?>-0089
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType; ?>"><?= htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- SEARCH BAR & CATEGORY CHIPS (UI-11) -->
<div class="card-box" style="padding: 16px 20px; margin-bottom: 20px;">
    <form method="POST" action="transaksi_pos.php" style="display: flex; gap: 12px; margin-bottom: 14px;">
        <input type="hidden" name="action" value="add_item">
        <div style="flex: 1; position: relative;">
            <select name="id_produk" id="posSearchSelect" class="form-control" style="font-size: 0.95rem; height: 44px; padding-left: 14px;" required>
                <option value="">🔍 Ketik Nama Produk / Scan Barcode [AUTO-FOCUS]...</option>
                <?php foreach ($produkList as $pr): ?>
                    <option value="<?= $pr['id_produk']; ?>">
                        PRD-00<?= $pr['id_produk']; ?> - <?= htmlspecialchars($pr['nama_produk']); ?> (Rp <?= number_format($pr['harga_jual'], 0, ',', '.'); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="height: 44px; padding: 0 24px; font-size: 0.95rem;">
            🛒 + Tambah [Enter]
        </button>
    </form>

    <!-- CATEGORY FILTER CHIPS PERSIS UI-11 -->
    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
        <span style="font-size: 0.74rem; font-weight: 700; color: var(--gray-400); text-transform: uppercase;">KATEGORI:</span>
        <button type="button" class="btn btn-primary btn-sm" style="border-radius: 20px; padding: 3px 12px;" onclick="filterPosCat('Semua')">Semua</button>
        <button type="button" class="btn btn-secondary btn-sm" style="border-radius: 20px; padding: 3px 12px;" onclick="filterPosCat('Minuman')">Minuman Segar</button>
        <button type="button" class="btn btn-secondary btn-sm" style="border-radius: 20px; padding: 3px 12px;" onclick="filterPosCat('Makanan')">Makanan &amp; Roti</button>
        <button type="button" class="btn btn-secondary btn-sm" style="border-radius: 20px; padding: 3px 12px;" onclick="filterPosCat('Sembako')">Sembako</button>
        <button type="button" class="btn btn-secondary btn-sm" style="border-radius: 20px; padding: 3px 12px;" onclick="filterPosCat('Personal')">Personal Care</button>
    </div>
</div>

<!-- CART & SUMMARY SPLIT VIEW PERSIS UI-11 -->
<div style="display: grid; grid-template-columns: 1.8fr 1fr; gap: 24px; align-items: flex-start;">
    <!-- LEFT: KERANJANG BELANJA AKTIF -->
    <div class="card-box" style="margin-bottom: 0;">
        <div class="card-box-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <h3 class="card-box-title">Keranjang Belanja Aktif</h3>
                <span class="pill pill-teal"><?= $cartItemsCount; ?> Item</span>
            </div>
            <?php if (!empty($_SESSION['pos_cart'])): ?>
                <form method="POST" action="transaksi_pos.php" style="margin: 0;">
                    <input type="hidden" name="action" value="clear_cart">
                    <button type="submit" class="btn btn-secondary btn-sm" style="color: #dc2626;">🗑 Kosongkan</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="table-wrap">
            <table class="sketch-table">
                <thead>
                    <tr>
                        <th style="width: 35px;">NO</th>
                        <th>SKU</th>
                        <th>NAMA PRODUK</th>
                        <th style="text-align: right;">HARGA</th>
                        <th style="text-align: center; width: 110px;">QTY</th>
                        <th style="text-align: right;">DISKON</th>
                        <th style="text-align: right;">SUBTOTAL</th>
                        <th style="text-align: center; width: 40px;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($_SESSION['pos_cart'])): ?>
                        <tr><td colspan="8" style="text-align: center; color: var(--gray-400); padding: 30px;">Keranjang belanja kasir masih kosong. Scan barcode atau pilih produk di atas.</td></tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($_SESSION['pos_cart'] as $id => $item): ?>
                            <?php $sub = ($item['harga'] - $item['diskon']) * $item['qty']; ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td class="sku-badge"><?= $item['sku']; ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['nama']); ?></strong>
                                    <div style="font-size: 0.72rem; color: var(--gray-400);">Ready: <?= $item['stok']; ?> pcs</div>
                                </td>
                                <td style="text-align: right;">Rp <?= number_format($item['harga'], 0, ',', '.'); ?></td>
                                <td style="text-align: center;">
                                    <div style="display: inline-flex; align-items: center; gap: 4px;">
                                        <form method="POST" action="transaksi_pos.php" style="display:inline; margin:0;">
                                            <input type="hidden" name="action" value="update_qty">
                                            <input type="hidden" name="id_produk" value="<?= $id; ?>">
                                            <input type="hidden" name="delta" value="-1">
                                            <button type="submit" class="btn btn-secondary btn-sm" style="padding: 2px 7px;">-</button>
                                        </form>
                                        <span style="font-weight: 700; width: 22px; text-align: center;"><?= $item['qty']; ?></span>
                                        <form method="POST" action="transaksi_pos.php" style="display:inline; margin:0;">
                                            <input type="hidden" name="action" value="update_qty">
                                            <input type="hidden" name="id_produk" value="<?= $id; ?>">
                                            <input type="hidden" name="delta" value="1">
                                            <button type="submit" class="btn btn-secondary btn-sm" style="padding: 2px 7px;">+</button>
                                        </form>
                                    </div>
                                </td>
                                <td style="text-align: right; color: #dc2626;">
                                    <?= ($item['diskon'] > 0) ? '-Rp ' . number_format($item['diskon'] * $item['qty'], 0, ',', '.') : '-'; ?>
                                </td>
                                <td style="text-align: right; font-weight: 700;">
                                    Rp <?= number_format($sub, 0, ',', '.'); ?>
                                </td>
                                <td style="text-align: center;">
                                    <form method="POST" action="transaksi_pos.php" style="display:inline; margin:0;">
                                        <input type="hidden" name="action" value="delete_item">
                                        <input type="hidden" name="id_produk" value="<?= $id; ?>">
                                        <button type="submit" style="background: none; border: none; color: var(--gray-400); cursor: pointer; font-size: 1.1rem;" title="Hapus Item">&times;</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- RIGHT: TOTAL TAGIHAN & CHECKOUT BUTTON (UI-11) -->
    <div class="card-box" style="margin-bottom: 0;">
        <div style="border-bottom: 1px solid var(--gray-100); padding-bottom: 14px; margin-bottom: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: baseline;">
                <span style="font-size: 0.74rem; font-weight: 700; color: var(--gray-400); text-transform: uppercase;">TOTAL TAGIHAN</span>
                <span class="pill pill-teal"><?= count($_SESSION['pos_cart']); ?> Produk Terpilih</span>
            </div>
            <div style="font-size: 2.2rem; font-weight: 800; color: var(--primary); margin-top: 6px; letter-spacing: -0.5px;">
                Rp <?= number_format($cartGrandTotal, 0, ',', '.'); ?>
            </div>
            <div style="font-size: 0.76rem; color: var(--gray-500); margin-top: 4px;">
                Subtotal: Rp <?= number_format($cartSubtotal, 0, ',', '.'); ?> | Diskon: -Rp <?= number_format($cartDiskon, 0, ',', '.'); ?> | PPN (11%): Termasuk
            </div>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="if(confirm('Batalkan transaksi saat ini?')) window.location.reload();">
                ⊗ Batal (Esc)
            </button>
            <button type="button" class="btn btn-primary" style="flex: 2; padding: 12px; font-size: 0.95rem;" onclick="openPaymentModal()" <?= empty($_SESSION['pos_cart']) ? 'disabled' : ''; ?>>
                🖨 PROSES BAYAR (F9)
            </button>
        </div>
    </div>
</div>

<!-- MODAL PEMBAYARAN: TUNAI (UI-13) & QRIS (UI-12) -->
<div id="paymentModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.75); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; border-radius: 12px; max-width: 640px; width: 92%; overflow: hidden; box-shadow: 0 25px 35px -5px rgba(0,0,0,0.3);">
        <!-- MODAL HEADER -->
        <div style="padding: 16px 22px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
            <div>
                <span style="font-size: 0.72rem; color: var(--gray-400); text-transform: uppercase;">KASIR POS UTAMA</span>
                <h3 style="font-size: 1.15rem; color: var(--dark); margin: 0;">Menu Pembayaran Kasir</h3>
            </div>
            <div style="text-align: right;">
                <span style="font-size: 0.72rem; color: var(--gray-400);">TOTAL TAGIHAN:</span>
                <div style="font-size: 1.35rem; font-weight: 800; color: var(--primary);">Rp <?= number_format($cartGrandTotal, 0, ',', '.'); ?></div>
            </div>
        </div>

        <!-- PAYMENT METHOD TABS -->
        <div style="padding: 14px 22px 0 22px;">
            <div style="font-size: 0.74rem; font-weight: 700; color: var(--gray-400); margin-bottom: 8px;">PILIH METODE PEMBAYARAN:</div>
            <div style="display: flex; gap: 10px;">
                <button type="button" id="tabTunaiBtn" class="btn btn-primary" style="flex: 1;" onclick="switchPaymentTab('tunai')">
                    💵 Metode Tunai [UI-13]
                </button>
                <button type="button" id="tabQrisBtn" class="btn btn-secondary" style="flex: 1;" onclick="switchPaymentTab('qris')">
                    📱 Metode QRIS [UI-12]
                </button>
            </div>
        </div>

        <!-- FORM CHECKOUT -->
        <form method="POST" action="transaksi_pos.php" style="padding: 20px 22px 22px 22px;">
            <input type="hidden" name="action" value="checkout">
            <input type="hidden" name="metode" id="inputMetode" value="Tunai">

            <!-- TAB 1: TUNAI (UI-13) -->
            <div id="panelTunai">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Uang Diterima (Rp):</label>
                    <input type="number" id="nominalBayar" name="nominal_bayar" class="form-control" style="font-size: 1.25rem; font-weight: 800; padding: 10px 14px;" value="<?= $cartGrandTotal; ?>" min="<?= $cartGrandTotal; ?>" required oninput="hitungKembalian()">
                </div>

                <!-- PRESET NOMINAL CEPAT -->
                <div style="display: flex; gap: 8px; margin-bottom: 16px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="setNominal(<?= $cartGrandTotal; ?>)">Uang Pas</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="setNominal(50000)">Rp 50.000</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="setNominal(100000)">Rp 100.000</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="setNominal(200000)">Rp 200.000</button>
                </div>

                <!-- KEMBALIAN BOX (UI-13) -->
                <div style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; padding: 16px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span style="font-size: 0.74rem; font-weight: 700; color: var(--gray-500); text-transform: uppercase;">Kembalian (Rp):</span>
                        <div style="font-size: 0.74rem; color: var(--gray-400);">Harap hitung dan serahkan ke pelanggan</div>
                    </div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: var(--primary);" id="displayKembalian">Rp 0</div>
                </div>

                <div class="action-bar" style="margin-top: 10px; padding-top: 14px;">
                    <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">✕ Batal (Esc)</button>
                    <button type="submit" class="btn btn-primary" style="padding: 11px 22px; font-size: 0.95rem;">
                        🖨 Simpan &amp; Cetak Struk (Enter / F9)
                    </button>
                </div>
            </div>

            <!-- TAB 2: QRIS DINAMIS (UI-12) -->
            <div id="panelQris" style="display: none;">
                <div style="text-align: center; margin-bottom: 14px;">
                    <div style="display: inline-flex; align-items: center; gap: 6px; background: #fefce8; border: 1px solid #fde047; padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; color: #a16207; margin-bottom: 12px;">
                        ⏱ Sisa waktu bayar: <strong id="qrisTimer">04:57 menit</strong>
                    </div>

                    <div style="display: flex; justify-content: center; margin-bottom: 14px;">
                        <img src="assets/qris_code.png" alt="QRIS Code" style="width: 220px; height: auto; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.06);">
                    </div>

                    <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 10px 16px; max-width: 320px; margin: 0 auto 14px auto;">
                        <span style="font-size: 0.74rem; color: #1e40af;">STATUS TRANSAKSI &bull; Auto-check 2s</span>
                        <div style="font-weight: 700; color: #1e3a8a; font-size: 0.9rem; margin-top: 2px;">
                            ⏳ Status: Menunggu pembayaran...
                        </div>
                    </div>
                </div>

                <div class="action-bar" style="margin-top: 10px; padding-top: 14px;">
                    <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">✕ Batal (Esc)</button>
                    <button type="submit" class="btn btn-primary" style="padding: 11px 22px; font-size: 0.95rem;">
                        ✓ Konfirmasi Lunas &amp; Cetak Struk
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- STRUK THERMAL PRINTER MODAL JIKA SUKSES -->
<?php if ($strukData): ?>
    <div id="receiptModal" style="display: flex; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.75); z-index: 2500; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div style="background: white; border-radius: 8px; width: 340px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3); font-family: 'Courier New', Courier, monospace; font-size: 0.82rem; line-height: 1.4;">
            <div style="text-align: center; border-bottom: 1px dashed #cbd5e1; padding-bottom: 12px; margin-bottom: 12px;">
                <strong style="font-size: 1.1rem; display: block; font-family: sans-serif;">OmniStok POS</strong>
                <span>Jl. Veteran No. 1 Malang</span><br>
                <span>Telp: (0341) 551611</span>
            </div>

            <div style="margin-bottom: 12px; font-size: 0.76rem;">
                <div>No: <?= $strukData['trx_id']; ?></div>
                <div>Tgl: <?= $strukData['waktu']; ?></div>
                <div>Kasir: <?= $strukData['kasir']; ?></div>
            </div>

            <div style="border-top: 1px dashed #cbd5e1; border-bottom: 1px dashed #cbd5e1; padding: 8px 0; margin-bottom: 10px;">
                <?php foreach ($strukData['items'] as $it): ?>
                    <div style="display: flex; justify-content: space-between;">
                        <span><?= htmlspecialchars($it['nama']); ?> x<?= $it['qty']; ?></span>
                        <span><?= number_format(($it['harga'] - $it['diskon']) * $it['qty'], 0, ',', '.'); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="line-height: 1.6; margin-bottom: 14px;">
                <div style="display: flex; justify-content: space-between;"><span>Subtotal:</span><span><?= number_format($strukData['subtotal'], 0, ',', '.'); ?></span></div>
                <div style="display: flex; justify-content: space-between;"><span>Diskon:</span><span>-<?= number_format($strukData['diskon'], 0, ',', '.'); ?></span></div>
                <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 0.95rem;"><span>TOTAL:</span><span>Rp <?= number_format($strukData['total'], 0, ',', '.'); ?></span></div>
                <div style="display: flex; justify-content: space-between;"><span>Metode:</span><span><?= $strukData['metode']; ?></span></div>
                <div style="display: flex; justify-content: space-between;"><span>Bayar:</span><span>Rp <?= number_format($strukData['bayar'], 0, ',', '.'); ?></span></div>
                <div style="display: flex; justify-content: space-between; font-weight: bold;"><span>Kembali:</span><span>Rp <?= number_format($strukData['kembalian'], 0, ',', '.'); ?></span></div>
            </div>

            <div style="text-align: center; border-top: 1px dashed #cbd5e1; padding-top: 10px; font-size: 0.74rem; color: #64748b;">
                Terima kasih atas kunjungan Anda!<br>Barang yang dibeli tidak dapat ditukar kecuali ada perjanjian retur.
            </div>

            <div style="margin-top: 18px; display: flex; gap: 8px;">
                <button type="button" class="btn btn-secondary btn-sm" style="flex: 1;" onclick="document.getElementById('receiptModal').remove()">Tutup</button>
                <button type="button" class="btn btn-primary btn-sm" style="flex: 1;" onclick="window.print()">Cetak</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
const grandTotal = <?= $cartGrandTotal; ?>;

function openPaymentModal() {
    document.getElementById('paymentModal').style.display = 'flex';
    hitungKembalian();
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

function switchPaymentTab(tab) {
    const tBtn = document.getElementById('tabTunaiBtn');
    const qBtn = document.getElementById('tabQrisBtn');
    const pTunai = document.getElementById('panelTunai');
    const pQris = document.getElementById('panelQris');
    const inpMetode = document.getElementById('inputMetode');
    const inpNominal = document.getElementById('nominalBayar');

    if (tab === 'tunai') {
        tBtn.className = 'btn btn-primary';
        qBtn.className = 'btn btn-secondary';
        pTunai.style.display = 'block';
        pQris.style.display = 'none';
        inpMetode.value = 'Tunai';
        inpNominal.disabled = false;
        if (parseFloat(inpNominal.value) < grandTotal) {
            inpNominal.value = grandTotal;
        }
        hitungKembalian();
    } else {
        tBtn.className = 'btn btn-secondary';
        qBtn.className = 'btn btn-primary';
        pTunai.style.display = 'none';
        pQris.style.display = 'block';
        inpMetode.value = 'QRIS';
        // QRIS dibayar lunas otomatis, nominal tidak perlu diisi kasir
        inpNominal.value = grandTotal;
        inpNominal.disabled = true;
    }
}

function setNominal(amt) {
    document.getElementById('nominalBayar').value = amt;
    hitungKembalian();
}

function hitungKembalian() {
    const bayar = parseFloat(document.getElementById('nominalBayar').value) || 0;
    const kembali = Math.max(0, bayar - grandTotal);
    document.getElementById('displayKembalian').innerText = 'Rp ' + kembali.toLocaleString('id-ID');
}

function filterPosCat(cat) {
    const sel = document.getElementById('posSearchSelect');
    // Simple filter simulation
    if (cat !== 'Semua') {
        sel.focus();
    }
}
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
