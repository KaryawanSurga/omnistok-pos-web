<?php
/**
 * Halaman: Laporan Mutasi Stok Persediaan (UC-01)
 * Penulis: Fauzi Al Wahid (FW - 255150400111016)
 *
 * Entry point tipis (MVC):
 * - Model     : models/MutasiStokModel.php
 * - Kontroler : controllers/MutasiStokController.php
 * - View      : views/laporan_mutasi/index.php
 */

require_once __DIR__ . '/config/auth.php';
auth_require_page(basename(__FILE__));

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/MutasiStokController.php';

$db = Database::getInstance();
$controller = new MutasiStokController($db);
$controller->handleExport($_GET);

extract($controller->index($_GET), EXTR_SKIP);

require __DIR__ . '/views/laporan_mutasi/index.php';
