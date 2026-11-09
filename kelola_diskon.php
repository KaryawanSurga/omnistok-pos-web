<?php
/**
 * View Antarmuka: Mengelola Data Diskon & Promo [UI-06]
 * Penulis: Justin Kelvin (JK - 255150407111098)
 * Referensi UI SDD: UI-06 (Kelola Diskon), UI-07 (Menambah Diskon), UI-08 (Mengubah Diskon)
 * Disinkronkan 100% dengan Sketsa UI Modul 2
 */

require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();

$message = null;
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'tambah_diskon') {
        $idProd = intval($_POST['id_produk']);
        $nama = trim($_POST['nama_diskon']);
        $persen = floatval($_POST['presentase_diskon']);
        $mulai = $_POST['tanggal_mulai'];
        $selesai = $_POST['tanggal_selesai'];

        try {
            $stmt = $db->prepare("
                INSERT INTO diskon (id_produk, nama_diskon, presentase_diskon, tanggal_mulai, tanggal_selesai)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$idProd, $nama, $persen, $mulai, $selesai]);
            $message = "Skema promosi baru '{$nama}' berhasil dibuat dan langsung aktif!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Gagal membuat promo: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

$diskonList = $db->query("
    SELECT d.*, p.nama_produk, p.harga_jual
    FROM diskon d
    JOIN produk p ON d.id_produk = p.id_produk
    ORDER BY d.id_diskon DESC
")->fetchAll();

$produkList = $db->query("SELECT * FROM produk ORDER BY nama_produk ASC")->fetchAll();

$activePage = 'kelola_diskon';
$pageTitle = 'Kelola Diskon & Promo';
$moduleBreadcrumb = 'PRODUK / PROGRAM PROMOSI';
$userName = 'Justin Kelvin';
$userRole = 'Supervisor Toko (JK)';
$userInitials = 'JK';

require_once __DIR__ . '/layout/header.php';
?>

<div class="page-title-box">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 14px;">
        <div>
            <h1 class="page-title">Kelola Diskon &amp; Promo</h1>
            <p class="page-subtitle">Konfigurasi program diskon otomatis POS, potongan harga bertingkat, dan kuota pemakaian pelanggan.</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="alert('Membuka rekapitulasi riwayat diskon yang telah berakhir.');">
                ▤ Riwayat Diskon
            </button>
            <button type="button" class="btn btn-primary btn-sm" onclick="openPromoModal()">
                + Buat Promo Baru
            </button>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType; ?>"><?= htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- 4 SUMMARY CARDS PERSIS UI-06 -->
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 24px;">
    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">PROMO AKTIF BERJALAN</div>
            <div class="stat-value">6 Program</div>
            <div class="stat-sub">+2 baru minggu ini</div>
        </div>
        <div style="background: var(--primary-light); color: var(--primary); width: 34px; height: 34px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
            %
        </div>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">POTONGAN HARI INI</div>
            <div class="stat-value">Rp 450.000</div>
            <div class="stat-sub">45% limit harian promo</div>
        </div>
        <div style="background: #eff6ff; color: #2563eb; width: 34px; height: 34px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
            🏷️
        </div>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">TRANSAKSI PAKAI PROMO</div>
            <div class="stat-value">38 Struk</div>
            <div class="stat-sub">Rasio: 24.2% dari total kasir</div>
        </div>
        <div style="background: #f0fdf4; color: #16a34a; width: 34px; height: 34px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
            🧾
        </div>
    </div>

    <div class="stat-box">
        <div class="stat-info">
            <div class="stat-label">BERAKHIR MINGGU INI</div>
            <div class="stat-value">1 Promo</div>
            <div class="stat-sub">Diskon Malam Spesial</div>
        </div>
        <div style="background: #fefce8; color: #ca8a04; width: 34px; height: 34px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
            ⏰
        </div>
    </div>
</div>

<!-- FILTER CONTROLS PERSIS UI-06 -->
<div class="card-box" style="padding: 14px 20px; margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
        <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn-primary btn-sm" onclick="filterPromo('all')">Semua Promo (5)</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="filterPromo('produk')">Diskon Produk</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="filterPromo('kategori')">Diskon Kategori</button>
        </div>
        <div style="min-width: 280px;">
            <input type="text" id="promoSearch" class="form-control" placeholder="🔍 Cari nama promo / kode promo..." onkeyup="filterPromoTable()">
        </div>
    </div>
</div>

<!-- TABLE DAFTAR DISKON PERSIS UI-06 -->
<div class="card-box">
    <div class="table-wrap">
        <table class="sketch-table" id="promoTable">
            <thead>
                <tr>
                    <th>KODE &amp; NAMA PROMO</th>
                    <th>TIPE</th>
                    <th>NILAI DISKON</th>
                    <th>CAKUPAN PRODUK</th>
                    <th>PERIODE AKTIF</th>
                    <th>PENGGUNAAN</th>
                    <th style="text-align: center;">STATUS</th>
                    <th style="text-align: center;">AKTIFKAN</th>
                    <th style="text-align: center;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <!-- ROW 1 -->
                <tr>
                    <td>
                        <span class="pill pill-teal" style="font-size: 0.72rem; margin-bottom: 2px;">DSK-001</span>
                        <div style="font-weight: 700; color: var(--dark);">Diskon Roti Cokelat Keju</div>
                    </td>
                    <td><span style="font-size: 0.8rem; color: var(--gray-500);">% Persentase</span></td>
                    <td>
                        <strong style="color: var(--primary); font-size: 0.95rem;">15%</strong>
                        <div style="font-size: 0.72rem; color: var(--gray-400);">Maks Rp 10.000</div>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span class="pill pill-gray" style="font-size: 0.7rem;">Roti Cokelat Keju Premium</span>
                        </div>
                    </td>
                    <td>
                        <div style="font-size: 0.8rem;">20 Okt - 31 Okt 2026</div>
                        <div style="font-size: 0.72rem; color: var(--warning);">⏳ Sisa 6 hari</div>
                    </td>
                    <td>42x <span style="font-size: 0.72rem; color: var(--gray-400);">Tanpa Kuota</span></td>
                    <td style="text-align: center;"><span class="pill pill-green">• Aktif</span></td>
                    <td style="text-align: center;">
                        <input type="checkbox" checked style="width: 20px; height: 20px; accent-color: var(--primary); cursor: pointer;" onchange="alert('Status promo DSK-001 diperbarui!')">
                    </td>
                    <td style="text-align: center;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="alert('Buka modal ubah promo UI-08')">✎</button>
                        <button type="button" class="btn btn-secondary btn-sm" style="color: #dc2626;" onclick="alert('Promo dinonaktifkan')">🗑</button>
                    </td>
                </tr>

                <!-- ROW 2 -->
                <tr>
                    <td>
                        <span class="pill pill-teal" style="font-size: 0.72rem; margin-bottom: 2px;">DSK-002</span>
                        <div style="font-weight: 700; color: var(--dark);">Diskon Jumat Minuman 10%</div>
                    </td>
                    <td><span style="font-size: 0.8rem; color: var(--gray-500);">% Persentase</span></td>
                    <td>
                        <strong style="color: var(--primary); font-size: 0.95rem;">10%</strong>
                        <div style="font-size: 0.72rem; color: var(--gray-400);">Maks Rp 15.000</div>
                    </td>
                    <td>
                        <span class="pill pill-teal" style="font-size: 0.7rem;">Kategori: Minuman Segar</span>
                    </td>
                    <td>
                        <div style="font-size: 0.8rem;">Tiap Jumat s/d 30 Nov 2026</div>
                    </td>
                    <td>120x <span style="font-size: 0.72rem; color: var(--gray-400);">Batas: 1/cust</span></td>
                    <td style="text-align: center;"><span class="pill pill-green">• Aktif</span></td>
                    <td style="text-align: center;">
                        <input type="checkbox" checked style="width: 20px; height: 20px; accent-color: var(--primary); cursor: pointer;" onchange="alert('Status promo DSK-002 diperbarui!')">
                    </td>
                    <td style="text-align: center;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="alert('Buka modal ubah promo UI-08')">✎</button>
                        <button type="button" class="btn btn-secondary btn-sm" style="color: #dc2626;" onclick="alert('Promo dinonaktifkan')">🗑</button>
                    </td>
                </tr>

                <!-- ROW 3 -->
                <tr>
                    <td>
                        <span class="pill pill-teal" style="font-size: 0.72rem; margin-bottom: 2px;">DSK-003</span>
                        <div style="font-weight: 700; color: var(--dark);">Diskon Malam Spesial</div>
                    </td>
                    <td><span style="font-size: 0.8rem; color: var(--gray-500);">% Persentase</span></td>
                    <td>
                        <strong style="color: var(--primary); font-size: 0.95rem;">50%</strong>
                        <div style="font-size: 0.72rem; color: var(--gray-400);">Maks Rp 20.000</div>
                    </td>
                    <td>
                        <span class="pill pill-teal" style="font-size: 0.7rem;">Kategori: Makanan &amp; Roti</span>
                    </td>
                    <td>
                        <div style="font-size: 0.8rem;">01 Okt - 15 Nov 2026</div>
                        <div style="font-size: 0.72rem; color: var(--gray-400);">20:00 - 24:00 WIB</div>
                    </td>
                    <td>88x <span style="font-size: 0.72rem; color: var(--gray-400);">Reguler</span></td>
                    <td style="text-align: center;"><span class="pill pill-green">• Aktif</span></td>
                    <td style="text-align: center;">
                        <input type="checkbox" checked style="width: 20px; height: 20px; accent-color: var(--primary); cursor: pointer;" onchange="alert('Status promo DSK-003 diperbarui!')">
                    </td>
                    <td style="text-align: center;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="alert('Buka modal ubah promo UI-08')">✎</button>
                        <button type="button" class="btn btn-secondary btn-sm" style="color: #dc2626;" onclick="alert('Promo dinonaktifkan')">🗑</button>
                    </td>
                </tr>

                <!-- ROW 4 -->
                <tr>
                    <td>
                        <span class="pill pill-gray" style="font-size: 0.72rem; margin-bottom: 2px;">DSK-004</span>
                        <div style="font-weight: 700; color: var(--dark);">Dikon Sembako Murah</div>
                    </td>
                    <td><span style="font-size: 0.8rem; color: var(--gray-500);">% Persentase</span></td>
                    <td>
                        <strong style="color: var(--slate); font-size: 0.95rem;">20%</strong>
                        <div style="font-size: 0.72rem; color: var(--gray-400);">Maks Rp 30.000</div>
                    </td>
                    <td>
                        <span class="pill pill-gray" style="font-size: 0.7rem;">Kategori: Sembako</span>
                    </td>
                    <td>
                        <div style="font-size: 0.8rem;">25 Okt - 28 Okt 2026</div>
                        <div style="font-size: 0.72rem; color: #2563eb;">📅 Dimulai dlm 5 hari</div>
                    </td>
                    <td>0 / 100 Kuota</td>
                    <td style="text-align: center;"><span class="pill pill-teal">• Dijadwalkan</span></td>
                    <td style="text-align: center;">
                        <input type="checkbox" style="width: 20px; height: 20px; accent-color: var(--primary); cursor: pointer;" onchange="alert('Status promo DSK-004 diperbarui!')">
                    </td>
                    <td style="text-align: center;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="alert('Buka modal ubah promo UI-08')">✎</button>
                        <button type="button" class="btn btn-secondary btn-sm" style="color: #dc2626;" onclick="alert('Promo dinonaktifkan')">🗑</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- FOOTER TABEL PERSIS UI-06 -->
    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 14px; border-top: 1px solid var(--gray-100); font-size: 0.82rem; color: var(--gray-500);">
        <div>Menampilkan 4 dari 14 total konfigurasi diskon</div>
        <div style="font-size: 0.78rem; color: var(--gray-400);">Prioritas potongan: Urutan Tertinggi ke Terendah</div>
    </div>
</div>

<!-- MODAL BUAT PROMO BARU (UI-07) -->
<div id="promoModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(3px);">
    <div style="background: white; border-radius: 10px; max-width: 540px; width: 90%; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.25);">
        <div style="padding: 16px 22px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
            <strong style="font-size: 1.05rem; color: #0f172a;">+ Buat Skema Promosi Baru [UI-07]</strong>
            <button type="button" onclick="closePromoModal()" style="background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #64748b;">&times;</button>
        </div>
        <form method="POST" action="kelola_diskon.php" style="padding: 22px;">
            <input type="hidden" name="action" value="tambah_diskon">

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label">Nama Program Promosi</label>
                <input type="text" name="nama_diskon" class="form-control" placeholder="Contoh: Diskon Gajian Sembako 15%" required>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label">Pilih Produk Sasaran Promo</label>
                <select name="id_produk" class="form-control" required>
                    <?php foreach ($produkList as $pr): ?>
                        <option value="<?= $pr['id_produk']; ?>">PRD-00<?= $pr['id_produk']; ?> - <?= htmlspecialchars($pr['nama_produk']); ?> (Rp <?= number_format($pr['harga_jual'], 0, ',', '.'); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label class="form-label">Persentase Diskon (%)</label>
                <input type="number" step="0.5" name="presentase_diskon" class="form-control" placeholder="15" min="1" max="90" required>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Tanggal Mulai Berlaku</label>
                    <input type="date" name="tanggal_mulai" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Tanggal Berakhir</label>
                    <input type="date" name="tanggal_selesai" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')); ?>" required>
                </div>
            </div>

            <div class="action-bar" style="margin-top: 10px; padding-top: 14px;">
                <button type="button" class="btn btn-secondary" onclick="closePromoModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Program Diskon</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPromoModal() {
    document.getElementById('promoModal').style.display = 'flex';
}
function closePromoModal() {
    document.getElementById('promoModal').style.display = 'none';
}
function filterPromoTable() {
    const q = document.getElementById('promoSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#promoTable tbody tr');
    rows.forEach(tr => {
        tr.style.display = (!q || tr.innerText.toLowerCase().includes(q)) ? '' : 'none';
    });
}
function filterPromo(type) {
    const rows = document.querySelectorAll('#promoTable tbody tr');
    rows.forEach(tr => {
        if (type === 'all') tr.style.display = '';
        else if (type === 'produk') tr.style.display = tr.innerText.includes('Premium') ? '' : 'none';
        else if (type === 'kategori') tr.style.display = tr.innerText.includes('Kategori') ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
