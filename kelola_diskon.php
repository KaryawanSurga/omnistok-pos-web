<?php
declare(strict_types=1);

/**
 * Halaman: Mengelola Data Diskon & Promo [UI-06]
 * Penulis: Justin Kelvin (JK - 255150407111098)
 * Referensi UI SDD: UI-06 (Kelola Diskon), UI-07 (Menambah Diskon), UI-08 (Mengubah Diskon)
 *
 * Entry point tipis (MVC):
 * - Model   : models/DiskonModel.php
 * - Kontroler: controllers/DiskonController.php
 * - View    : views/diskon/index.php
 */

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/config/auth.php';
auth_require_page(basename(__FILE__));

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/DiskonController.php';

try {
    $db = Database::getInstance();
} catch (Throwable $e) {
    http_response_code(500);
    exit('Database tidak dapat dibuka: ' . e($e->getMessage()));
}

$controller = new DiskonController($db);
$controller->handlePost();

extract($controller->index(), EXTR_SKIP);

require __DIR__ . '/views/diskon/index.php';
