<?php
declare(strict_types=1);

/**
 * View Antarmuka: Pengaturan Pembayaran QRIS Merchant Sendiri
 *
 * Halaman khusus Pemilik Toko untuk:
 * - Memindai (decode) gambar QRIS milik toko langsung di browser (jsQR)
 * - Menyimpan payload QRIS merchant sebagai dasar QRIS dinamis di kasir
 * - Melihat pratinjau QRIS dinamis beserta nominal
 *
 * Nominal pada QR selalu mengikuti total tagihan kasir (tag 01 = 12, tag 54).
 */

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/controllers/QrisController.php';
auth_require_page(basename(__FILE__));

function qe(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function qRedirect(string $type, string $message, string $anchor = ''): never
{
    $_SESSION['qris_flash'] = ['type' => $type, 'message' => $message];
    header('Location: pengaturan_qris.php' . ($anchor !== '' ? '#' . $anchor : ''));
    exit;
}

$flash = $_SESSION['qris_flash'] ?? null;
unset($_SESSION['qris_flash']);

/* --------------------------------------------------------------------------
 * Endpoint AJAX: payload dinamis untuk nominal tertentu
 * ----------------------------------------------------------------------- */
if (($_GET['ajax'] ?? '') === 'payload') {
    header('Content-Type: application/json; charset=utf-8');

    $nominal = (int) ($_GET['nominal'] ?? 0);
    $payload = QrisController::forAmount($nominal);

    echo json_encode([
        'nominal' => $nominal,
        'payload' => $payload,
        'status'  => $payload !== null,
    ]);
    exit;
}

/* --------------------------------------------------------------------------
 * Aksi formulir
 * ----------------------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!auth_csrf_valid($_POST['csrf_token'] ?? null)) {
        qRedirect('danger', 'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
    }

    if ($action === 'save_payload') {
        $payload = trim((string) ($_POST['payload'] ?? ''));

        // Simpan juga gambar QRIS (opsional) sebagai tampilan cadangan
        $imageNote = '';
        if (!empty($_FILES['gambar_qris']['name']) && (int) ($_FILES['gambar_qris']['error'] ?? 0) !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['gambar_qris'];
            $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($error !== UPLOAD_ERR_OK) {
                $imageNote = ' Gambar QRIS gagal diunggah (kode ' . $error . ').';
            } elseif ((int) $file['size'] > 5 * 1024 * 1024) {
                $imageNote = ' Gambar QRIS melebihi 5 MB sehingga tidak disimpan.';
            } else {
                $info = @getimagesize((string) $file['tmp_name']);
                $mime = is_array($info) ? ($info['mime'] ?? '') : '';
                $ext  = ['image/png' => 'png', 'image/jpeg' => 'jpg'][$mime] ?? '';

                if ($ext === '') {
                    $imageNote = ' Format gambar QRIS harus PNG atau JPG.';
                } else {
                    $target = __DIR__ . '/assets/qris_merchant_qr.' . $ext;
                    foreach (['png', 'jpg', 'jpeg'] as $old) {
                        $oldPath = __DIR__ . '/assets/qris_merchant_qr.' . $old;
                        if ($old !== $ext && is_file($oldPath)) {
                            @unlink($oldPath);
                        }
                    }

                    if (@move_uploaded_file((string) $file['tmp_name'], $target)) {
                        QrisController::saveImagePath('assets/qris_merchant_qr.' . $ext);
                    } else {
                        $imageNote = ' Gambar QRIS tidak dapat disimpan (folder assets tidak memiliki izin tulis).';
                    }
                }
            }
        }

        $result = QrisController::savePayload($payload);
        qRedirect($result['status'] ? 'success' : 'danger', $result['message'] . $imageNote, 'status-qris');
    }

    if ($action === 'clear_payload') {
        QrisController::clearPayload();
        qRedirect('info', 'Payload QRIS merchant dihapus. Kasir kembali memakai gambar QRIS statis.', 'status-qris');
    }
}

/* --------------------------------------------------------------------------
 * Data untuk tampilan
 * ----------------------------------------------------------------------- */
$stored     = QrisController::load();
$payload    = (string) $stored['payload'];
$check      = QrisController::validate($payload);
$configured = $payload !== '' && $check['ok'];
$imagePath  = QrisController::imagePath();

$nominalUji = (int) ($_GET['nominal'] ?? 1000);
if ($nominalUji < 1) {
    $nominalUji = 1000;
}
$payloadUji = $configured ? QrisController::dynamic($payload, $nominalUji) : '';

$activePage       = 'pengaturan_qris';
$pageTitle        = 'Pengaturan Pembayaran QRIS';
$moduleBreadcrumb = 'USER & SETTINGS / PEMBAYARAN QRIS MERCHANT';
$csrf             = auth_csrf_token();

require_once __DIR__ . '/layout/header.php';
?>

<style>
    .qris-grid { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr); gap: 20px; align-items: start; }
    @media (max-width: 960px) { .qris-grid { grid-template-columns: 1fr; } }
    .qris-drop {
        border: 2px dashed #cbd5e1; border-radius: 10px; padding: 22px; text-align: center;
        background: #f8fafc; transition: border-color .2s ease, background .2s ease;
    }
    .qris-drop.is-active { border-color: var(--primary); background: #eef7f6; }
    .qris-note { font-size: .8rem; color: #64748b; line-height: 1.6; }
    .qris-preview { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; text-align: center; }
    .qris-preview svg { width: 100%; max-width: 260px; height: auto; }
    .qris-kv { width: 100%; border-collapse: collapse; font-size: .86rem; }
    .qris-kv th, .qris-kv td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eef2f7; vertical-align: top; }
    .qris-kv th { width: 40%; color: #64748b; font-weight: 600; }
    .qris-kv td { color: #0f172a; word-break: break-all; }
    .qris-payload { font-family: ui-monospace, "Cascadia Mono", Consolas, monospace; font-size: .78rem; }
</style>

<div class="page-title-box">
    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <div style="background: var(--primary); color: #fff; width: 38px; height: 38px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">📱</div>
        <div>
            <h1 class="page-title" style="font-size: 1.4rem; margin-bottom: 2px;">Pembayaran QRIS Merchant Sendiri</h1>
            <p class="page-subtitle" style="margin: 0;">Pindai QRIS toko Anda sekali, lalu kasir menampilkan QR dengan nominal otomatis sesuai total belanja.</p>
        </div>
    </div>
</div>

<?php if ($flash): ?>
    <?php
    $warna = [
        'success' => ['#ecfdf5', '#a7f3d0', '#047857'],
        'danger'  => ['#fef2f2', '#fecaca', '#b91c1c'],
        'info'    => ['#eff6ff', '#bfdbfe', '#1d4ed8'],
        'warning' => ['#fffbeb', '#fde68a', '#b45309'],
    ][$flash['type'] ?? 'info'] ?? ['#eff6ff', '#bfdbfe', '#1d4ed8'];
    ?>
    <div style="background: <?= $warna[0]; ?>; border: 1px solid <?= $warna[1]; ?>; color: <?= $warna[2]; ?>; border-radius: 8px; padding: 12px 16px; margin-bottom: 18px; font-size: .88rem;">
        <?= qe($flash['message']); ?>
    </div>
<?php endif; ?>

<!-- STATUS KONFIGURASI -->
<div class="card-box" id="status-qris" style="margin-bottom: 20px; border-left: 4px solid <?= $configured ? '#16a34a' : '#f59e0b'; ?>;">
    <div class="card-box-header" style="margin-bottom: 10px;">
        <div>
            <span class="sku-badge"><?= $configured ? 'QRIS SIAP' : 'BELUM DIATUR'; ?></span>
            <h3 class="card-box-title" style="margin-top: 4px;">Status Pembayaran QRIS</h3>
        </div>
        <span class="pill <?= $configured ? 'pill-green' : 'pill-teal'; ?>"><?= $configured ? 'Dinamis Aktif' : 'Menunggu QRIS Merchant'; ?></span>
    </div>

    <?php if ($configured): ?>
        <p style="font-size: .88rem; color: #475569; margin: 0 0 12px 0;">
            QRIS dinamis aktif. Setiap transaksi QRIS di menu kasir akan menampilkan QR milik
            <strong><?= qe($check['merchant'] !== '' ? $check['merchant'] : QrisController::merchantName()); ?></strong>
            dengan nominal sesuai total tagihan.
        </p>
        <table class="qris-kv">
            <tr><th>Merchant (tag 59)</th><td><?= qe($check['merchant'] !== '' ? $check['merchant'] : '-'); ?></td></tr>
            <tr><th>Kota / Cabang (tag 60)</th><td><?= qe($check['city'] !== '' ? $check['city'] : '-'); ?></td></tr>
            <tr><th>NMID</th><td><?= qe($check['nmid'] !== '' ? $check['nmid'] : '-'); ?></td></tr>
            <tr><th>Acquirer (tag 26-51)</th><td><?= qe($check['acquirer'] !== '' ? $check['acquirer'] : '-'); ?></td></tr>
            <tr><th>Mata uang</th><td><?= $check['currency'] === '360' ? 'IDR (360)' : qe($check['currency']); ?></td></tr>
            <tr><th>CRC16</th><td><span style="color: #047857; font-weight: 700;">Valid</span></td></tr>
            <tr><th>Terakhir diperbarui</th><td><?= qe($stored['updated_at'] !== '' ? $stored['updated_at'] : '-'); ?></td></tr>
            <tr><th>Payload statis</th><td class="qris-payload"><?= qe($payload); ?></td></tr>
        </table>

        <form method="POST" style="margin-top: 14px;" onsubmit="return confirm('Hapus payload QRIS merchant? Kasir akan kembali memakai gambar QRIS statis.');">
            <input type="hidden" name="csrf_token" value="<?= qe($csrf); ?>">
            <input type="hidden" name="action" value="clear_payload">
            <button type="submit" class="btn btn-secondary btn-sm">Hapus Payload QRIS</button>
        </form>
    <?php else: ?>
        <p style="font-size: .88rem; color: #475569; margin: 0 0 8px 0;">
            Payload QRIS merchant belum tersimpan, sehingga menu kasir masih memakai gambar QRIS statis
            (<code><?= qe($imagePath); ?></code>) tanpa nominal otomatis.
        </p>
        <?php if ($payload !== '' && !$check['ok']): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 8px; padding: 12px 14px; font-size: .84rem; margin-top: 8px;">
                <strong>Payload tersimpan tidak valid:</strong>
                <ul style="margin: 6px 0 0 18px; padding: 0;">
                    <?php foreach ($check['errors'] as $err): ?>
                        <li><?= qe($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <p class="qris-note" style="margin-top: 10px;">
            Unggah gambar QRIS resmi milik toko (poster GoPay/QRIS atau tangkapan layar aplikasi merchant)
            melalui formulir di bawah. Gambar dipindai langsung di perangkat ini, tidak dikirim ke server lain.
        </p>
    <?php endif; ?>
</div>

<div class="qris-grid">
    <!-- FORM PEMINDAIAN -->
    <div class="card-box" style="margin-bottom: 0;">
        <div class="card-box-header" style="margin-bottom: 14px;">
            <div>
                <span class="sku-badge">LANGKAH 1</span>
                <h3 class="card-box-title" style="margin-top: 4px;">Pindai / Tempel Payload QRIS</h3>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="qrisForm">
            <input type="hidden" name="csrf_token" value="<?= qe($csrf); ?>">
            <input type="hidden" name="action" value="save_payload">

            <div class="qris-drop" id="qrisDrop">
                <div style="font-size: 1.6rem; margin-bottom: 6px;">🖼️</div>
                <div style="font-weight: 700; color: #0f172a; font-size: .92rem; margin-bottom: 4px;">Pilih gambar QRIS merchant</div>
                <div class="qris-note" style="margin-bottom: 10px;">PNG atau JPG, maksimal 5 MB. QR pada gambar akan dipindai otomatis.</div>
                <input type="file" name="gambar_qris" id="qrisFile" accept="image/png,image/jpeg" style="display: none;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('qrisFile').click()">Pilih Gambar QRIS</button>
                <div id="qrisScanStatus" class="qris-note" style="margin-top: 10px;"></div>
                <img id="qrisImagePreview" alt="Pratinjau QRIS" style="display: none; max-width: 180px; margin: 10px auto 0 auto; border: 1px solid #e2e8f0; border-radius: 8px;">
            </div>

            <div class="form-group" style="margin-top: 16px;">
                <label class="form-label" for="payload">Payload QRIS (terisi otomatis, bisa ditempel manual):</label>
                <textarea id="payload" name="payload" class="form-control qris-payload" rows="6" spellcheck="false" placeholder="00020101021126...6304ABCD"><?= qe($payload); ?></textarea>
                <div class="qris-note" style="margin-top: 6px;">
                    Isi dengan string panjang berawalan <code>000201</code> dan berakhiran 4 karakter CRC.
                    Nominal transaksi nanti dibuat otomatis oleh sistem, bukan dari string ini.
                </div>
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px;">
                <button type="submit" class="btn btn-primary">Simpan Payload QRIS</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('payload').value='';">Bersihkan Kolom</button>
            </div>
        </form>

        <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid #eef2f7;">
            <div style="font-size: .78rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 8px;">Gambar QRIS statis tersimpan</div>
            <div style="display: flex; align-items: center; gap: 14px;">
                <img src="<?= qe($imagePath); ?>?v=<?= qe((string) (@filemtime(__DIR__ . '/' . $imagePath) ?: time())); ?>" alt="QRIS Statis" style="width: 92px; height: auto; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff;">
                <div class="qris-note">
                    <code><?= qe($imagePath); ?></code><br>
                    Dipakai kasir sebagai cadangan bila payload dinamis belum tersedia.
                </div>
            </div>
        </div>
    </div>

    <!-- PRATINJAU QRIS DINAMIS -->
    <div class="card-box" style="margin-bottom: 0;">
        <div class="card-box-header" style="margin-bottom: 14px;">
            <div>
                <span class="sku-badge">LANGKAH 2</span>
                <h3 class="card-box-title" style="margin-top: 4px;">Pratinjau QRIS Dinamis</h3>
            </div>
        </div>

        <?php if ($configured): ?>
            <div style="display: flex; gap: 8px; align-items: flex-end; margin-bottom: 14px;">
                <div class="form-group" style="flex: 1; margin-bottom: 0;">
                    <label class="form-label" for="nominalUji">Nominal uji (Rp)</label>
                    <input type="number" id="nominalUji" class="form-control" value="<?= $nominalUji; ?>" min="1" step="1">
                </div>
                <button type="button" class="btn btn-primary" onclick="renderPreview()">Tampilkan</button>
            </div>

            <div class="qris-preview">
                <div id="qrisPreviewQr"></div>
                <div style="margin-top: 10px; font-weight: 800; color: #0f172a; font-size: 1.05rem;" id="qrisPreviewNominal">Rp <?= number_format($nominalUji, 0, ',', '.'); ?></div>
                <div class="qris-note" id="qrisPreviewMeta">
                    <?= qe($check['merchant'] !== '' ? $check['merchant'] : QrisController::merchantName()); ?> &bull;
                    <?= qe($check['city'] !== '' ? $check['city'] : QrisController::merchantCity()); ?> &bull;
                    NMID <?= qe($check['nmid']); ?>
                </div>
            </div>

            <details style="margin-top: 12px;">
                <summary style="cursor: pointer; font-size: .8rem; color: #475569;">Lihat payload dinamis (hasil perhitungan ulang CRC)</summary>
                <div class="qris-payload" id="qrisPreviewPayload" style="margin-top: 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px;"><?= qe($payloadUji); ?></div>
            </details>

            <p class="qris-note" style="margin-top: 12px;">
                QR di atas memakai tag <code>01 = 12</code> (dinamis) dan tag <code>54</code> berisi nominal.
                Nominal yang dipakai di kasir selalu total belanja pelanggan, bukan angka uji ini.
            </p>
        <?php else: ?>
            <div style="text-align: center; padding: 26px 10px; color: #94a3b8;">
                <div style="font-size: 2rem; margin-bottom: 8px;">🔒</div>
                <div style="font-size: .9rem; line-height: 1.6;">
                    Pratinjau tersedia setelah payload QRIS merchant berhasil disimpan.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="assets/vendor/qrcode-generator.js"></script>
<?php if ($configured): ?>
<script src="assets/vendor/jsqr.js"></script>
<script>
    // Render pratinjau QR memakai qrcode-generator (offline, tanpa CDN)
    function drawQr(target, text) {
        if (!text) { return; }
        try {
            const qr = qrcode(0, 'M');
            qr.addData(text);
            qr.make();
            target.innerHTML = qr.createSvgTag({ scalable: true, cellSize: 4 });
        } catch (err) {
            target.innerHTML = '<div style="color:#b91c1c;font-size:.8rem;">Payload terlalu panjang untuk QR: ' + err.message + '</div>';
        }
    }

    drawQr(document.getElementById('qrisPreviewQr'), <?= json_encode($payloadUji, JSON_UNESCAPED_SLASHES); ?>);

    async function renderPreview() {
        const nominal = parseInt(document.getElementById('nominalUji').value, 10) || 0;
        const status = document.getElementById('qrisScanStatus');
        const res = await fetch('pengaturan_qris.php?ajax=payload&nominal=' + encodeURIComponent(nominal), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();

        if (!data.status || !data.payload) {
            document.getElementById('qrisPreviewQr').innerHTML = '';
            document.getElementById('qrisPreviewPayload').textContent = '';
            document.getElementById('qrisPreviewNominal').textContent = 'Nominal tidak valid';
            return;
        }

        document.getElementById('qrisPreviewNominal').textContent = 'Rp ' + nominal.toLocaleString('id-ID');
        document.getElementById('qrisPreviewPayload').textContent = data.payload;
        drawQr(document.getElementById('qrisPreviewQr'), data.payload);

        if (status) { status.textContent = ''; }
    }
</script>
<?php else: ?>
<script src="assets/vendor/jsqr.js"></script>
<?php endif; ?>

<script>
    // Pemindaian QRIS di sisi browser: gambar tidak dikirim ke server mana pun.
    (function () {
        const input    = document.getElementById('qrisFile');
        const drop     = document.getElementById('qrisDrop');
        const status   = document.getElementById('qrisScanStatus');
        const preview  = document.getElementById('qrisImagePreview');
        const payload  = document.getElementById('payload');
        if (!input) { return; }

        function setStatus(text, color) {
            status.textContent = text;
            status.style.color = color || '#64748b';
        }

        function scanImage(img) {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d', { willReadFrequently: true });

            // Berbagai skala & area potong: poster merchant sering memuat foto besar.
            const attempts = [
                { scale: 1, crop: 1 }, { scale: 0.7, crop: 1 }, { scale: 0.5, crop: 1 },
                { scale: 0.35, crop: 1 }, { scale: 1, crop: 0.6 }, { scale: 0.8, crop: 0.5 },
                { scale: 0.6, crop: 0.8 }, { scale: 1.6, crop: 0.45 }, { scale: 1, crop: 0.35 }
            ];

            for (const a of attempts) {
                const cropW = img.naturalWidth * a.crop;
                const cropH = img.naturalHeight * a.crop;
                const sx = (img.naturalWidth - cropW) / 2;
                const sy = (img.naturalHeight - cropH) / 2;

                let w = Math.round(cropW * a.scale);
                let h = Math.round(cropH * a.scale);
                const max = 1400;
                if (w > max || h > max) {
                    const k = max / Math.max(w, h);
                    w = Math.round(w * k);
                    h = Math.round(h * k);
                }
                if (w < 60 || h < 60) { continue; }

                canvas.width = w;
                canvas.height = h;
                ctx.drawImage(img, sx, sy, cropW, cropH, 0, 0, w, h);

                const data = ctx.getImageData(0, 0, w, h);
                const result = jsQR(data.data, w, h, { inversionAttempts: 'attemptBoth' });
                if (result && result.data) {
                    return result.data;
                }
            }

            return null;
        }

        input.addEventListener('change', function () {
            const file = input.files && input.files[0];
            if (!file) { return; }

            if (file.size > 5 * 1024 * 1024) {
                setStatus('Ukuran gambar melebihi 5 MB. Kompres atau pilih gambar lain.', '#b91c1c');
                input.value = '';
                return;
            }

            const url = URL.createObjectURL(file);
            const img = new Image();
            preview.style.display = 'none';

            img.onload = function () {
                preview.src = url;
                preview.style.display = 'block';
                setStatus('Memindai QR pada gambar...', '#64748b');

                // Beri kesempatan browser menggambar pratinjau lebih dulu
                setTimeout(function () {
                    try {
                        const found = scanImage(img);
                        if (found) {
                            payload.value = found;
                            setStatus('QRIS berhasil dibaca. Periksa kolom payload lalu klik "Simpan Payload QRIS".', '#047857');
                        } else {
                            setStatus('QR tidak terbaca otomatis. Coba gambar yang lebih tajam, atau tempel payload secara manual di kolom bawah.', '#b45309');
                        }
                    } catch (err) {
                        setStatus('Gagal memindai gambar: ' + err.message, '#b91c1c');
                    }
                }, 60);
            };

            img.onerror = function () {
                setStatus('Berkas gambar tidak dapat dibuka.', '#b91c1c');
            };

            img.src = url;
        });

        if (drop) {
            ['dragenter', 'dragover'].forEach(function (evt) {
                drop.addEventListener(evt, function (e) {
                    e.preventDefault();
                    drop.classList.add('is-active');
                });
            });
            ['dragleave', 'drop'].forEach(function (evt) {
                drop.addEventListener(evt, function (e) {
                    e.preventDefault();
                    drop.classList.remove('is-active');
                });
            });
            drop.addEventListener('drop', function (e) {
                if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                    input.files = e.dataTransfer.files;
                    input.dispatchEvent(new Event('change'));
                }
            });
        }
    })();
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
