<?php
/**
 * Halaman Login Terminal Otentikasi [UI-18]
 * Penulis: Ferdyansah (FF - 255150400111039)
 * Referensi UI SDD: UI-18 (Login Terminal Kasir)
 *
 * Seluruh halaman operasional mewajibkan sesi login aktif.
 */

require_once __DIR__ . '/config/auth.php';

// Sudah login? langsung ke dashboard
if (auth_check()) {
    header('Location: index.php');
    exit;
}

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!auth_csrf_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Sesi formulir telah kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } else {
        $hasil = auth_login($email, $password);

        if ($hasil['status']) {
            $target = $_SESSION['auth_redirect'] ?? 'index.php';
            unset($_SESSION['auth_redirect']);

            $targetValid = is_string($target)
                && $target !== ''
                && strpos($target, "\n") === false
                && strpos($target, "\r") === false
                && !preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $target);

            header('Location: ' . ($targetValid ? $target : 'index.php'));
            exit;
        }

        $error = $hasil['message'];
    }
}

$csrfToken = auth_csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - OmniStok POS</title>
    <link rel="icon" href="assets/logo.png" type="image/png">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>

<div class="login-shell">
    <!-- BRAND KIRI ATAS -->
    <header class="login-brand">
        <span class="brand-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2.6 20.2 7v10L12 21.4 3.8 17V7L12 2.6Z"/>
                <path d="M3.8 7 12 11.4 20.2 7"/>
                <path d="M12 11.4v10"/>
            </svg>
        </span>
        <span class="brand-word">OmniStok</span>
    </header>

    <!-- KARTU LOGIN TENGAH -->
    <main class="login-card">
        <div class="card-accent" aria-hidden="true"></div>

        <div class="card-head">
            <div class="terminal-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6.5 9V3.5h11V9"/>
                    <path d="M6.5 18H4.5A1.5 1.5 0 0 1 3 16.5v-6A1.5 1.5 0 0 1 4.5 9h15A1.5 1.5 0 0 1 21 10.5v6a1.5 1.5 0 0 1-1.5 1.5h-2"/>
                    <rect x="6.5" y="13.5" width="11" height="7" rx="1.2"/>
                    <path d="M8.8 16.2h2.4"/>
                </svg>
            </div>

            <div class="eyebrow">Terminal Otentikasi</div>
            <h1>Masuk ke OmniStok POS</h1>
            <p class="subtitle">Sistem Terpadu Kasir &amp; Manajemen Inventaris Multi-Cabang</p>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert-error" role="alert">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 7.5v5.5"/>
                    <path d="M12 16.5h.01"/>
                </svg>
                <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="field-label-row">
                <label for="email">Email Karyawan</label>
                <span class="field-meta">ID POS</span>
            </div>
            <div class="input-group">
                <span class="input-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="5" width="18" height="14" rx="2"/>
                        <path d="m3.6 6.4 8.4 6 8.4-6"/>
                    </svg>
                </span>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                       placeholder="kasir.utama@omnistok.id" autocomplete="username" spellcheck="false" required autofocus>
            </div>

            <div class="field-label-row">
                <label for="password">Kata Sandi</label>
                <span class="field-meta">&nbsp;</span>
            </div>
            <div class="input-group">
                <span class="input-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4.5" y="10.5" width="15" height="10" rx="2"/>
                        <path d="M8 10.5V7.8a4 4 0 0 1 8 0v2.7"/>
                    </svg>
                </span>
                <input type="password" id="password" name="password" placeholder="Masukkan kata sandi"
                       autocomplete="current-password" required>
                <button type="button" class="toggle-pass" id="togglePass" aria-label="Tampilkan kata sandi">
                    <svg id="eyeOpen" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2.5 12s3.6-6 9.5-6 9.5 6 9.5 6-3.6 6-9.5 6-9.5-6-9.5-6Z"/>
                        <circle cx="12" cy="12" r="2.8"/>
                    </svg>
                    <svg id="eyeClosed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                        <path d="M4 4.5 20 20"/>
                        <path d="M9.6 6.3A9.7 9.7 0 0 1 12 6c5.9 0 9.5 6 9.5 6a17.6 17.6 0 0 1-3 3.6"/>
                        <path d="M6.3 8.3A17.3 17.3 0 0 0 2.5 12s3.6 6 9.5 6a9.6 9.6 0 0 0 3.3-.6"/>
                    </svg>
                </button>
            </div>

            <button type="submit" class="btn-login">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 4h3.5A2.5 2.5 0 0 1 20 6.5v11a2.5 2.5 0 0 1-2.5 2.5H14"/>
                    <path d="M9.5 8 5.5 12l4 4"/>
                    <path d="M5.5 12H16"/>
                </svg>
                <span>Masuk ke Sistem POS</span>
            </button>
        </form>

        <aside class="info-box">
            <div class="info-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="3.1"/>
                    <path d="M19.4 13.5a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.55V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1.11-1.55 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.7 1.7 0 0 0 .34-1.87 1.7 1.7 0 0 0-1.55-1H3a2 2 0 1 1 0-4h.09a1.7 1.7 0 0 0 1.55-1.11 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34H9a1.7 1.7 0 0 0 1-1.55V3a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1 1.55 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87V9a1.7 1.7 0 0 0 1.55 1H21a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.51 1Z"/>
                </svg>
                <span>Pemberitahuan Akses Terminal</span>
            </div>
            <p>
                Pendaftaran akun kasir atau staf <strong>tidak tersedia</strong> di portal ini.
                Seluruh akun karyawan hanya dapat didaftarkan langsung oleh
                <strong>Pemilik Toko (Store Owner)</strong> melalui konsol <em>Kelola Akun &amp; Akses</em>.
            </p>
            <div class="info-help">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="8.5"/>
                    <path d="M12 7.5V12l3 1.8"/>
                </svg>
                <span><strong>Butuh kredensial baru?</strong> Hubungi supervisor kasir cabang Anda.</span>
            </div>
        </aside>
    </main>
</div>

<footer class="login-footer" aria-hidden="true"></footer>

<script>
(function () {
    var input = document.getElementById('password');
    var button = document.getElementById('togglePass');
    var eyeOpen = document.getElementById('eyeOpen');
    var eyeClosed = document.getElementById('eyeClosed');

    button.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        eyeOpen.style.display = show ? 'none' : '';
        eyeClosed.style.display = show ? '' : 'none';
        button.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        input.focus();
    });
})();
</script>

</body>
</html>
