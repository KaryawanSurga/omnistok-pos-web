<?php
/**
 * Halaman: Pusat Peringatan Dini Persediaan (UC-05 & UC-06)
 * Penulis: Fauzi Al Wahid (FW - 255150400111016)
 *
 * Entry point tipis (MVC):
 * - Model     : models/PeringatanStokModel.php
 * - Kontroler : controllers/PeringatanStokController.php
 * - View      : views/peringatan_stok/index.php
 */

require_once __DIR__ . '/config/auth.php';
auth_require_page(basename(__FILE__));

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/PeringatanStokController.php';

$db = Database::getInstance();
$controller = new PeringatanStokController($db);

extract($controller->index($_GET), EXTR_SKIP);

require __DIR__ . '/views/peringatan_stok/index.php';
