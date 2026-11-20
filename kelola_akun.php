<?php
declare(strict_types=1);

/**
 * Halaman: Kelola Data Akun & Hak Akses [UI-02]
 * Penulis: Justin Kelvin (JK - 255150407111098)
 * Referensi UI SDD: UI-02 (Mengelola Akun & Hak Akses), UI-03 (Menambah Akun), UI-04 (Mengubah Akun)
 *
 * Entry point tipis (MVC):
 * - Model   : models/AkunModel.php
 * - Kontroler: controllers/AkunController.php
 * - View    : views/akun/index.php
 *
 * Hak akses: hanya Pemilik Toko (SUPER_ADMIN).
 */

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/config/auth.php';
auth_require_owner();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/AkunController.php';

try {
    $db = Database::getInstance();
} catch (Throwable $e) {
    http_response_code(500);
    exit('Database tidak dapat dibuka: ' . e($e->getMessage()));
}

$controller = new AkunController($db);
$controller->handlePost();

extract($controller->index(), EXTR_SKIP);

require __DIR__ . '/views/akun/index.php';
