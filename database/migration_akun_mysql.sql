-- Migrasi tabel akun OmniStok untuk database MySQL/MariaDB yang sudah ada.
-- Backup database terlebih dahulu. Jalankan tiap blok satu per satu.
-- Jika muncul "Duplicate column name", lewati blok tersebut.

ALTER TABLE akun
    ADD COLUMN nama_lengkap VARCHAR(100) NULL AFTER email;

-- Isi nama tampilan untuk akun demo bawaan (opsional).
UPDATE akun SET nama_lengkap = 'Administrator Toko'
WHERE email = 'admin@omnistok.com' AND (nama_lengkap IS NULL OR nama_lengkap = '');

UPDATE akun SET nama_lengkap = 'Kasir Utama'
WHERE email = 'kasir@omnistok.com' AND (nama_lengkap IS NULL OR nama_lengkap = '');

UPDATE akun SET nama_lengkap = 'Petugas Gudang'
WHERE email = 'gudang@omnistok.com' AND (nama_lengkap IS NULL OR nama_lengkap = '');

-- Halaman Kelola Akun memakai password_hash (bcrypt) untuk akun baru.
-- Akun lama tetap dapat diubah; isi kata sandi baru melalui form edit
-- agar nilainya ikut di-hash.
