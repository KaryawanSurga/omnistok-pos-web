<?php
declare(strict_types=1);

/**
 * Halaman: Daftar Produk & Pembaruan Harga [UI-15 / UI-20]
 * Penulis: Zainal Maarif (ZM - 255150400111070) & Justin Kelvin (JK)
 * Referensi UI SDD: UI-15 (Mengelola Produk), UI-16 (Tambah Produk),
 *                   UI-20 (Memperbarui Harga Modal & Jual)
 *
 * Entry point tipis (MVC):
 * - Model   : models/ProdukModel.php
 * - Kontroler: controllers/ProdukController.php
 * - View    : views/produk/index.php (tab katalog & tab harga)
 */

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/config/auth.php';
auth_require_page(basename(__FILE__));

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/ProdukController.php';

try {
    $db = Database::getInstance();
} catch (Throwable $e) {
    http_response_code(500);
    exit('Database tidak dapat dibuka: ' . e($e->getMessage()));
}

$controller = new ProdukController($db);
$controller->handlePost();

extract($controller->index(), EXTR_SKIP);

require __DIR__ . '/views/produk/index.php';
