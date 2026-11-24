<?php
/**
 * Halaman: Laporan Penjualan & Laba Kotor (UC-03)
 * Penulis: Fauzi Al Wahid (FW - 255150400111016)
 *
 * Entry point tipis (MVC):
 * - Model     : models/LaporanPenjualanModel.php
 * - Kontroler : controllers/LaporanPenjualanController.php
 * - View      : views/laporan_penjualan/index.php
 */

require_once __DIR__ . '/config/auth.php';
auth_require_page(basename(__FILE__));

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/LaporanPenjualanController.php';

$db = Database::getInstance();
$controller = new LaporanPenjualanController($db);
$controller->handleExport($_GET);

extract($controller->index($_GET), EXTR_SKIP);

require __DIR__ . '/views/laporan_penjualan/index.php';
