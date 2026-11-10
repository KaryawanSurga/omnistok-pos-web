<?php
/**
 * Otentikasi & Otorisasi - OmniStok POS
 * Kelompok 6 - Praktikum RSI
 *
 * Menyediakan:
 * - Sesi login (wajib login untuk seluruh halaman operasional)
 * - Verifikasi kata sandi (bcrypt + kompatibilitas data lama)
 * - Proteksi CSRF untuk formulir login
 * - Pembatasan akses berbasis hak akses (role)
 */

require_once __DIR__ . '/database.php';

if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $secure,
    ]);
    session_start();
}

function auth_db(): PDO {
    return Database::getInstance();
}

function auth_now_sql(): string {
    return (auth_db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql')
        ? 'NOW()'
        : "datetime('now','localtime')";
}

/** Data pengguna yang sedang login, atau null bila belum login. */
function auth_user(): ?array {
    return (isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user']))
        ? $_SESSION['auth_user']
        : null;
}

function auth_check(): bool {
    return auth_user() !== null;
}

/** Inisial nama untuk avatar (maksimal 2 huruf). */
function auth_initials(string $name): string {
    $name = trim($name);
    if ($name === '') {
        return '??';
    }
    $parts = preg_split('/\s+/', $name) ?: [];
    if (count($parts) >= 2) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    }
    return strtoupper(mb_substr($name, 0, 2));
}

/**
 * Verifikasi kredensial dan buat sesi login.
 * Mendukung hash bcrypt maupun password lama yang masih tersimpan sebagai teks biasa
 * (otomatis diubah menjadi bcrypt saat login pertama berhasil).
 */
function auth_login(string $email, string $password): array {
    $email = trim($email);

    if ($email === '' || $password === '') {
        return ['status' => false, 'message' => 'Email dan kata sandi wajib diisi.'];
    }

    $db = auth_db();
    $stmt = $db->prepare('SELECT * FROM akun WHERE LOWER(email) = LOWER(?) LIMIT 1');
    $stmt->execute([$email]);
    $akun = $stmt->fetch();

    if (!$akun) {
        return ['status' => false, 'message' => 'Email atau kata sandi tidak sesuai.'];
    }

    if ((int) $akun['status_aktif'] !== 1) {
        return ['status' => false, 'message' => 'Akun ini sedang tidak aktif. Hubungi Pemilik Toko untuk mengaktifkannya.'];
    }

    $stored  = (string) $akun['password_hash'];
    $info    = password_get_info($stored);
    $hashed  = (($info['algoName'] ?? 'unknown') !== 'unknown');
    $isValid = $hashed
        ? password_verify($password, $stored)
        : hash_equals($stored, $password);

    if (!$isValid) {
        return ['status' => false, 'message' => 'Email atau kata sandi tidak sesuai.'];
    }

    // Perbarui hash lama/lemah ke bcrypt terbaru
    if (!$hashed || password_needs_rehash($stored, PASSWORD_DEFAULT)) {
        $db->prepare('UPDATE akun SET password_hash = ? WHERE id_akun = ?')
           ->execute([password_hash($password, PASSWORD_DEFAULT), $akun['id_akun']]);
    }

    $db->prepare('UPDATE akun SET last_login_time = ' . auth_now_sql() . ' WHERE id_akun = ?')
       ->execute([$akun['id_akun']]);

    session_regenerate_id(true);

    $_SESSION['auth_user'] = [
        'id_akun'      => (int) $akun['id_akun'],
        'email'        => (string) $akun['email'],
        'nama_lengkap' => ($akun['nama_lengkap'] !== null && $akun['nama_lengkap'] !== '')
            ? (string) $akun['nama_lengkap']
            : (string) $akun['email'],
        'hak_akses'    => (string) $akun['hak_akses'],
        'role'         => (string) $akun['role'],
        'login_at'     => time(),
    ];

    return ['status' => true, 'user' => $_SESSION['auth_user']];
}

function auth_logout(): void {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires'  => time() - 42000,
                'path'     => $params['path'] ?? '/',
                'domain'   => $params['domain'] ?? '',
                'secure'   => (bool) ($params['secure'] ?? false),
                'httponly' => (bool) ($params['httponly'] ?? true),
                'samesite' => 'Lax',
            ]
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/** Wajib login; halaman yang dilindungi memanggil fungsi ini sebelum output apa pun. */
function auth_require_login(): void {
    if (auth_check()) {
        return;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !empty($_SERVER['REQUEST_URI'])) {
        $_SESSION['auth_redirect'] = $_SERVER['REQUEST_URI'];
    }

    header('Location: login.php');
    exit;
}

/** Hanya Pemilik Toko (Store Owner) / Super Admin yang boleh mengelola akun. */
function auth_is_owner(): bool {
    $user = auth_user();
    if (!$user) {
        return false;
    }

    $hak  = strtoupper((string) ($user['hak_akses'] ?? ''));
    $role = strtolower((string) ($user['role'] ?? ''));

    return in_array($hak, ['SUPER_ADMIN', 'PEMILIK', 'OWNER', 'ADMIN'], true)
        || strpos($role, 'pemilik') !== false
        || strpos($role, 'owner') !== false;
}

/** Hentikan akses bila pengguna bukan Pemilik Toko. */
function auth_require_owner(): void {
    auth_require_login();

    if (auth_is_owner()) {
        return;
    }

    http_response_code(403);
    $user = auth_user();
    $nama = htmlspecialchars((string) ($user['nama_lengkap'] ?? ''), ENT_QUOTES, 'UTF-8');

    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>Akses Ditolak - OmniStok POS</title>'
        . '<style>'
        . 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
        . 'font-family:"Segoe UI",system-ui,sans-serif;background:#f3f5f8;color:#0f172a;padding:24px;}'
        . '.box{max-width:460px;background:#fff;border-radius:12px;padding:36px;text-align:center;'
        . 'box-shadow:0 18px 45px rgba(15,23,42,.08);border-top:5px solid #dc2626;}'
        . '.ico{width:56px;height:56px;border-radius:14px;background:#fef2f2;color:#dc2626;display:flex;'
        . 'align-items:center;justify-content:center;font-size:1.6rem;margin:0 auto 16px;}'
        . 'h1{font-size:1.35rem;margin:0 0 10px;}p{color:#64748b;font-size:.92rem;line-height:1.6;margin:0 0 22px;}'
        . 'a{display:inline-block;background:#0d7a74;color:#fff;text-decoration:none;padding:11px 22px;'
        . 'border-radius:6px;font-weight:700;font-size:.9rem;}'
        . 'a.secondary{background:#e2e8f0;color:#334155;margin-left:8px;}'
        . '</style></head><body><div class="box">'
        . '<div class="ico">&#128274;</div>'
        . '<h1>Akses Ditolak</h1>'
        . '<p>Halaman <strong>Kelola Akun &amp; Hak Akses</strong> hanya dapat dibuka oleh '
        . '<strong>Pemilik Toko (Store Owner)</strong>. Akun <strong>' . $nama . '</strong> tidak memiliki izin.</p>'
        . '<a href="index.php">Kembali ke Dashboard</a>'
        . '<a class="secondary" href="logout.php">Ganti Akun</a>'
        . '</div></body></html>';
    exit;
}

/** Token CSRF untuk formulir login. */
function auth_csrf_token(): string {
    if (empty($_SESSION['auth_csrf'])) {
        $_SESSION['auth_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['auth_csrf'];
}

function auth_csrf_valid($token): bool {
    return is_string($token)
        && !empty($_SESSION['auth_csrf'])
        && hash_equals($_SESSION['auth_csrf'], $token);
}
