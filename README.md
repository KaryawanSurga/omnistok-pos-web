# OmniStok - Web Implementation & Source Code
### Praktikum Rekayasa Sistem Informasi (RSI) - FILKOM Universitas Brawijaya
**Kelas:** SI-G  
**Kelompok:** 6  

---

## 👥 Tim Pengembang & Pembagian Tugas (Kelompok 6)

1. **Justin Kelvin (JK - 255150407111098)**: 
   - **Use Case:** UC-02 (Kelola Akun & Hak Akses), UC-13..16 (Mengelola Master Produk), UC-04 (Kelola Diskon)
   - **Antarmuka (Wireframe):** `UI-02` (Kelola Akun), `UI-03` (Tambah Akun), `UI-04` (Ubah Akun), `UI-06` (Kelola Diskon), `UI-07` (Tambah Diskon), `UI-15` (Katalog Produk), `UI-16` (Tambah Produk).
   - **Artefak:** Tabel DDL `akun`, `diskon`, `produk`; Kontroler `AkunController.php`; View `kelola_akun.php`, `kelola_diskon.php`, `daftar_produk.php`.

2. **Ferdyansah (FF - 255150400111039)**: 
   - **Use Case:** UC-07 (Transaksi Penjualan Kasir POS), UC-08 (Merekonsiliasi Kas Shift), UC-17 (Otentikasi Login)
   - **Antarmuka (Wireframe):** `UI-11` (Kasir POS), `UI-12` (Pembayaran QRIS), `UI-13` (Pembayaran Tunai), `UI-14` (Rekonsiliasi Kas), `UI-18` (Login Terminal).
   - **Artefak:** Tabel DDL `transaksi`, `detail_penjualan`, `rekonsiliasi`; Kontroler `TransaksiController.php`; View `transaksi_pos.php`, `rekonsiliasi_kas.php`.

3. **Zainal Maarif (ZM - 255150400111070)**: 
   - **Use Case:** UC-09 (Mencatat Penerimaan Barang), UC-10 (Memperbarui Harga Modal & Jual), UC-11 (Penyesuaian Stok / Opname), UC-12 (Retur Barang ke Pemasok)
   - **Antarmuka (Wireframe):** `UI-09` / `UI-19` (Penerimaan Barang), `UI-10` / `UI-20` (Pembaruan Harga), `UI-11` / `UI-21` (Penyesuaian Stok Opname), `UI-12` / `UI-22` (Retur Barang ke Vendor).
   - **Artefak:** Tabel DDL `penerimaan`, `detail_penerimaan`, `riwayat_harga`, `retur_penerimaan`, `detail_retur`; Kontroler `PenerimaanController.php`; View `penerimaan_barang.php`, `daftar_produk.php`, `penyesuaian_stok.php`, `retur_barang.php`.

4. **Fauzi Al Wahid (FW - 255150400111016)**: 
   - **Use Case:** UC-01 (Melihat Laporan Mutasi Stok), UC-03 (Melihat Laporan Penjualan & Laba), UC-05 (Peringatan Stok Menipis), UC-06 (Peringatan Kadaluwarsa)
   - **Antarmuka (Wireframe):** `UI-01` (Laporan Mutasi Stok), `UI-05` (Laporan Penjualan & Laba), `UI-09` (Peringatan Stok Menipis), `UI-10` (Peringatan Kadaluwarsa).
   - **Artefak:** Tabel DDL `mutasi_stok`, `stok`; Kontroler `MutasiStokController.php`; View `laporan_penjualan.php`, `laporan_mutasi.php`.

---

## 📁 Struktur Direktori Repositori

```text
omnistok-web/
├── assets/
│   ├── barcodes/            # Aset barcode grafis
│   ├── products/            # Gambar produk (PRD-001 s/d PRD-006)
│   ├── uploads/             # Bukti foto fisik retur (foto_beras_rusak_bthA1.jpg)
│   ├── wireframes/          # Arsip lengkap 22 sketsa UI Modul 2 (UI-01 s/d UI-22)
│   ├── logo.png             # Logo resmi OmniStok POS
│   └── qris_code.png        # Barcode QRIS dinamis pembayaran kasir
├── config/
│   └── database.php         # Koneksi PDO (MySQL / SQLite Fallback Serverless)
├── controllers/
│   ├── AkunController.php       # [JK-SC01] Kontroler Akun & Hak Akses
│   ├── TransaksiController.php  # [FF-SC01] Kontroler Kasir POS & Transaksi
│   ├── PenerimaanController.php # [ZM-SC01] Kontroler Penerimaan Faktur & Stok
│   └── MutasiStokController.php # [FW-SC01] Kontroler Mutasi Stok & Riwayat
├── css/
│   └── style.css            # Stylesheet tema antarmuka hijau toska (#0d7a74)
├── database/
│   ├── schema.sql           # Skrip DDL Lengkap (MySQL InnoDB)
│   └── omnistok_local.sqlite# Basis data SQLite lokal & serverless siap pakai
├── layout/
│   ├── header.php           # Template sidebar & top bar terpusat
│   └── footer.php           # JavaScript interaktivitas & responsive drawer
├── daftar_produk.php        # [UI-15 & UI-20] Katalog Master & Pembaruan Harga
├── index.php                # Portal Utama & Dashboard Integrasi Kelompok 6
├── kelola_akun.php          # [UI-02, UI-03] Kelola Akun & Hak Akses
├── kelola_diskon.php        # [UI-06, UI-07] Kelola Diskon & Program Promosi
├── laporan_mutasi.php       # [UI-01, UI-09, UI-10] Laporan Mutasi & Peringatan Stok
├── laporan_penjualan.php    # [UI-05] Laporan Omzet, HPP, & Laba Kotor Toko
├── penerimaan_barang.php    # [UI-19] Pencatatan Faktur & Penerimaan Barang
├── penyesuaian_stok.php     # [UI-21] Penyesuaian Stok Fisik / Stock Opname
├── rekonsiliasi_kas.php     # [UI-14] Rekonsiliasi Kas Shift Kasir POS
├── retur_barang.php         # [UI-22] Formulir Retur Barang ke Vendor Pemasok
├── transaksi_pos.php        # [UI-11, UI-12, UI-13] Transaksi Penjualan Kasir POS
├── vercel.json              # Konfigurasi deployment serverless Vercel (vercel-php)
└── README.md
```

---

## 🚀 Panduan Menjalankan Secara Lokal

1. Pastikan PHP 8.x telah terinstal di komputer.
2. Buka terminal pada folder proyek:
   ```bash
   cd "c:\ZAINAL\SEMESTER 3\omnistok-web"
   php -S localhost:8000
   ```
3. Akses aplikasi melalui browser:
   [http://localhost:8000](http://localhost:8000)

*Catatan: Sistem secara otomatis menggunakan SQLite bawaan (`omnistok_local.sqlite`) dengan 17 tabel tersinkronisasi tanpa memerlukan instalasi database tambahan.*

---

## 🌐 Panduan Deployment di Vercel

Aplikasi telah dikonfigurasi untuk berjalan di Vercel menggunakan Community Runtime `vercel-php@0.7.3` melalui file `vercel.json`.

1. **Import dari GitHub:**
   - Masuk ke dashboard [vercel.com](https://vercel.com).
   - Klik **"Add New..."** &rarr; **"Project"**.
   - Pilih repositori GitHub: `KaryawanSurga/omnistok-pos-web`.
   - Biarkan pengaturan default (Vercel otomatis mendeteksi `vercel.json`).
   - Klik **"Deploy"**.
2. **Database di Vercel:**
   - Konfigurasi `config/database.php` secara otomatis menyalin database SQLite ke `/tmp/omnistok_local.sqlite` pada runtime serverless sehingga seluruh transaksi dan operasi tulis berfungsi 100% tanpa error `read-only`.
