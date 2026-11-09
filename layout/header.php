<?php
/**
 * Shared Header & Sidebar Template - OmniStok POS
 * Kelompok 6 - Praktikum RSI (Filkom UB)
 * 100% Sesuai Sketsa Wireframe UI Modul 2 (UI-01 s/d UI-22)
 */

if (!isset($activePage)) $activePage = 'dashboard';
if (!isset($pageTitle)) $pageTitle = 'Dashboard';
if (!isset($moduleBreadcrumb)) $moduleBreadcrumb = 'MAIN / DASHBOARD';
if (!isset($userName)) $userName = 'Zainal Maarif';
if (!isset($userRole)) $userRole = 'Petugas Stok Gudang';
if (!isset($userInitials)) $userInitials = 'ZM';

// Handle user role switcher via query parameter or cookie
if (isset($_GET['switch_user'])) {
    $sw = $_GET['switch_user'];
    if ($sw === 'ZM') { $userName = 'Zainal Maarif'; $userRole = 'Petugas Stok Gudang'; $userInitials = 'ZM'; }
    elseif ($sw === 'BS') { $userName = 'Budi Santoso'; $userRole = 'Kasir Utama • Jakarta'; $userInitials = 'BS'; }
    elseif ($sw === 'FF') { $userName = 'Ferdyansah'; $userRole = 'Pemilik Toko (Owner)'; $userInitials = 'FF'; }
    elseif ($sw === 'JK') { $userName = 'Justin Kelvin'; $userRole = 'Supervisor Toko'; $userInitials = 'JK'; }
    elseif ($sw === 'FW') { $userName = 'Fauzi Al Wahid'; $userRole = 'Petugas Logistik'; $userInitials = 'FW'; }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle); ?> - OmniStok POS</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="assets/logo.png" type="image/png">
</head>
<body>

<div class="app-layout">
    <!-- BACKDROP UNTUK TAMPILAN MOBILE/TABLET -->
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

    <!-- SIDEBAR NAVIGASI RESMI MODUL 2 -->
    <aside class="sidebar" id="sidebar">
        <!-- BRAND LOGO -->
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-brand">
                <span class="brand-icon">✓</span>
                <span class="brand-name">OmniStok</span>
                <span class="badge-pos">POS</span>
            </a>
            <button class="mobile-close-btn" onclick="toggleSidebar()" aria-label="Tutup Menu">&times;</button>
        </div>

        <!-- MENU ITEMS WITH ACCORDION SECTIONS -->
        <nav class="sidebar-menu" id="sidebarMenu">
            <!-- SECTION 1: MAIN -->
            <div class="menu-section-group active-section" id="sec-main">
                <button class="menu-section-header" onclick="toggleSection('sec-main')">
                    <span>MAIN</span>
                    <span class="chevron-icon">▾</span>
                </button>
                <div class="menu-section-content">
                    <a href="index.php" class="menu-item <?= ($activePage === 'dashboard') ? 'active' : ''; ?>">
                        <span class="menu-ico">⊞</span>
                        <span class="menu-text">Dashboard</span>
                    </a>
                </div>
            </div>

            <!-- SECTION 2: PRODUCTS -->
            <div class="menu-section-group <?= in_array($activePage, ['daftar_produk', 'kelola_diskon']) ? 'active-section' : ''; ?>" id="sec-products">
                <button class="menu-section-header" onclick="toggleSection('sec-products')">
                    <span>PRODUCTS</span>
                    <span class="chevron-icon">▾</span>
                </button>
                <div class="menu-section-content">
                    <a href="daftar_produk.php" class="menu-item <?= ($activePage === 'daftar_produk') ? 'active' : ''; ?>">
                        <span class="menu-ico">▤</span>
                        <span class="menu-text">Daftar Produk</span>
                    </a>
                    <a href="kelola_diskon.php" class="menu-item <?= ($activePage === 'kelola_diskon') ? 'active' : ''; ?>">
                        <span class="menu-ico">%</span>
                        <span class="menu-text">Kelola Diskon</span>
                    </a>
                </div>
            </div>

            <!-- SECTION 3: INVENTORY -->
            <div class="menu-section-group <?= in_array($activePage, ['penerimaan_barang', 'penyesuaian_stok', 'retur_barang']) ? 'active-section' : ''; ?>" id="sec-inventory">
                <button class="menu-section-header" onclick="toggleSection('sec-inventory')">
                    <span>INVENTORY</span>
                    <span class="chevron-icon">▾</span>
                </button>
                <div class="menu-section-content">
                    <a href="penerimaan_barang.php" class="menu-item <?= ($activePage === 'penerimaan_barang') ? 'active' : ''; ?>">
                        <span class="menu-ico">⇲</span>
                        <span class="menu-text">Penerimaan Barang</span>
                    </a>
                    <a href="penyesuaian_stok.php" class="menu-item <?= ($activePage === 'penyesuaian_stok') ? 'active' : ''; ?>">
                        <span class="menu-ico">≚</span>
                        <span class="menu-text">Penyesuaian Stok</span>
                    </a>
                    <a href="retur_barang.php" class="menu-item <?= ($activePage === 'retur_barang') ? 'active' : ''; ?>">
                        <span class="menu-ico">↩</span>
                        <span class="menu-text">Retur Barang</span>
                    </a>
                </div>
            </div>

            <!-- SECTION 4: POS & SALES -->
            <div class="menu-section-group <?= in_array($activePage, ['transaksi_pos', 'rekonsiliasi_kas']) ? 'active-section' : ''; ?>" id="sec-pos">
                <button class="menu-section-header" onclick="toggleSection('sec-pos')">
                    <span>POS & SALES</span>
                    <span class="chevron-icon">▾</span>
                </button>
                <div class="menu-section-content">
                    <a href="transaksi_pos.php" class="menu-item <?= ($activePage === 'transaksi_pos') ? 'active' : ''; ?>">
                        <span class="menu-ico">🛒</span>
                        <span class="menu-text">Transaksi Penjualan</span>
                    </a>
                    <a href="rekonsiliasi_kas.php" class="menu-item <?= ($activePage === 'rekonsiliasi_kas') ? 'active' : ''; ?>">
                        <span class="menu-ico">🖩</span>
                        <span class="menu-text">Rekonsiliasi Kas</span>
                    </a>
                </div>
            </div>

            <!-- SECTION 5: REPORTS -->
            <div class="menu-section-group <?= in_array($activePage, ['laporan_penjualan', 'laporan_mutasi']) ? 'active-section' : ''; ?>" id="sec-reports">
                <button class="menu-section-header" onclick="toggleSection('sec-reports')">
                    <span>REPORTS</span>
                    <span class="chevron-icon">▾</span>
                </button>
                <div class="menu-section-content">
                    <a href="laporan_penjualan.php" class="menu-item <?= ($activePage === 'laporan_penjualan') ? 'active' : ''; ?>">
                        <span class="menu-ico">📊</span>
                        <span class="menu-text">Laporan Penjualan & Laba</span>
                    </a>
                    <a href="laporan_mutasi.php" class="menu-item <?= ($activePage === 'laporan_mutasi') ? 'active' : ''; ?>">
                        <span class="menu-ico">📋</span>
                        <span class="menu-text">Laporan Mutasi Stok</span>
                    </a>
                </div>
            </div>

            <!-- SECTION 6: USER & SETTINGS -->
            <div class="menu-section-group <?= ($activePage === 'kelola_akun') ? 'active-section' : ''; ?>" id="sec-user">
                <button class="menu-section-header" onclick="toggleSection('sec-user')">
                    <span>USER & SETTINGS</span>
                    <span class="chevron-icon">▾</span>
                </button>
                <div class="menu-section-content">
                    <a href="kelola_akun.php" class="menu-item <?= ($activePage === 'kelola_akun') ? 'active' : ''; ?>">
                        <span class="menu-ico">👥</span>
                        <span class="menu-text">Kelola Akun & Akses</span>
                    </a>
                </div>
            </div>
        </nav>

        <!-- FOOTER SIDEBAR RESMI MODUL 2 -->
        <div class="sidebar-footer">
            <div class="sync-status">
                <span class="sync-dot"></span>
                <span>Terminal Synced</span>
            </div>
            <span class="version-tag">v2.4.1</span>
        </div>
    </aside>

    <!-- WRAPPER KONTEN UTAMA -->
    <div class="main-wrapper">
        <!-- TOP HEADER RESMI MODUL 2 -->
        <header class="top-header">
            <div class="header-left">
                <button class="mobile-toggle-btn" onclick="toggleSidebar()" aria-label="Buka Navigasi">☰</button>
                <div class="breadcrumb-box">
                    <span class="breadcrumb-system">OmniStok POS / Sistem Kasir Terpadu</span>
                    <span class="breadcrumb-active"><?= htmlspecialchars($moduleBreadcrumb); ?></span>
                </div>
            </div>

            <div class="header-right">
                <!-- ONLINE BADGE -->
                <div class="header-online-badge">
                    <span class="online-indicator"></span> Online
                </div>

                <!-- NOTIFICATION BELL -->
                <div class="notification-wrapper">
                    <button class="notification-btn" onclick="toggleNotificationDropdown()" title="Notifikasi Sistem">
                        <span class="bell-icon">🔔</span>
                        <span class="notif-badge">3</span>
                    </button>
                    <div class="notification-dropdown" id="notifDropdown">
                        <div class="notif-header">
                            <strong>Pemberitahuan Sistem (3)</strong>
                            <span class="pill pill-teal" style="font-size: 0.68rem;">Live POS</span>
                        </div>
                        <div class="notif-list">
                            <a href="daftar_produk.php" class="notif-item">
                                <span class="notif-dot warning"></span>
                                <div>
                                    <div class="notif-title">Peringatan Margin Minimum</div>
                                    <div class="notif-desc">1 SKU (PRD-002) memiliki margin jual &lt; 8% dari modal.</div>
                                </div>
                            </a>
                            <a href="retur_barang.php" class="notif-item">
                                <span class="notif-dot danger"></span>
                                <div>
                                    <div class="notif-title">Klaim Retur Pemasok</div>
                                    <div class="notif-desc">2 Unit Beras Rojolele rusak kemasan siap diretur ke vendor.</div>
                                </div>
                            </a>
                            <a href="laporan_mutasi.php" class="notif-item">
                                <span class="notif-dot info"></span>
                                <div>
                                    <div class="notif-title">Penerimaan Barang Selesai</div>
                                    <div class="notif-desc">PO-202610-001 telah berhasil dibukukan ke saldo rak gudang.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- USER PROFILE WITH ROLE SWITCHER -->
                <div class="user-profile-wrapper">
                    <div class="user-profile" onclick="toggleUserDropdown()" title="Klik untuk ganti peran">
                        <div class="avatar-circle"><?= htmlspecialchars($userInitials); ?></div>
                        <div class="user-info">
                            <span class="user-name"><?= htmlspecialchars($userName); ?></span>
                            <span class="user-role"><?= htmlspecialchars($userRole); ?></span>
                        </div>
                        <span class="user-chevron">▾</span>
                    </div>
                    <div class="user-dropdown" id="userDropdown">
                        <div class="dropdown-header">Ganti Pengguna Simulasi:</div>
                        <a href="?switch_user=ZM" class="dropdown-item <?= ($userInitials === 'ZM') ? 'active-user' : ''; ?>">
                            <strong>Zainal Maarif</strong> (Petugas Stok Gudang)
                        </a>
                        <a href="?switch_user=BS" class="dropdown-item <?= ($userInitials === 'BS') ? 'active-user' : ''; ?>">
                            <strong>Budi Santoso</strong> (Kasir Utama • Jakarta)
                        </a>
                        <a href="?switch_user=FF" class="dropdown-item <?= ($userInitials === 'FF') ? 'active-user' : ''; ?>">
                            <strong>Ferdyansah</strong> (Pemilik Toko / Owner)
                        </a>
                        <a href="?switch_user=JK" class="dropdown-item <?= ($userInitials === 'JK') ? 'active-user' : ''; ?>">
                            <strong>Justin Kelvin</strong> (Supervisor Toko)
                        </a>
                        <a href="?switch_user=FW" class="dropdown-item <?= ($userInitials === 'FW') ? 'active-user' : ''; ?>">
                            <strong>Fauzi Al Wahid</strong> (Petugas Logistik)
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- KONTEN HALAMAN -->
        <main class="content-area">
