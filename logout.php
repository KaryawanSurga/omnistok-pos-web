<?php
/**
 * Keluar dari sesi terminal OmniStok POS.
 */

require_once __DIR__ . '/config/auth.php';

auth_logout();

header('Location: login.php');
exit;
