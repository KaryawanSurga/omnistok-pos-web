<?php
/**
 * Kontroler: AkunController
 * Penulis: Justin Kelvin (JK - 255150407111098)
 * Referensi CD: CD-02 (Mengelola Data Akun & Akses)
 * Referensi Pseudocode: PC-2.1.01 s/d PC-2.1.05
 */

require_once __DIR__ . '/../config/database.php';

class AkunController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // [PC-2.1.03] Mengambil seluruh daftar akun pengguna
    public function getall_akun() {
        $stmt = $this->db->query("SELECT * FROM akun ORDER BY id_akun ASC");
        return $stmt->fetchAll();
    }

    // [PC-2.2.03] Mengambil data detail akun berdasarkan ID
    public function get_akun_by_id($id_akun) {
        $stmt = $this->db->prepare("SELECT * FROM akun WHERE id_akun = ?");
        $stmt->execute([$id_akun]);
        return $stmt->fetch();
    }

    // [PC-2.1.01] Menambahkan akun dan hak akses baru
    public function tambah_akun($email, $password, $hak_akses, $role) {
        if (empty($email) || empty($password) || empty($hak_akses) || empty($role)) {
            return ['status' => false, 'message' => 'Semua kolom formulir wajib diisi!'];
        }

        // Cek duplikasi email
        $stmt = $this->db->prepare("SELECT id_akun FROM akun WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['status' => false, 'message' => 'Email akun sudah terdaftar dalam sistem!'];
        }

        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare("
            INSERT INTO akun (email, password_hash, hak_akses, role, status_aktif, last_login_time)
            VALUES (?, ?, ?, ?, 1, NULL)
        ");
        $success = $stmt->execute([$email, $password_hash, $hak_akses, $role]);

        return [
            'status' => $success,
            'message' => $success ? 'Akun pengguna berhasil ditambahkan!' : 'Gagal menyimpan data akun.'
        ];
    }

    // [PC-2.2.04] Memperbarui informasi akun dan hak akses
    public function ubah_akun($id_akun, $email, $hak_akses, $role, $status_aktif) {
        if (empty($id_akun) || empty($email) || empty($hak_akses) || empty($role)) {
            return ['status' => false, 'message' => 'Data pembaharuan akun tidak lengkap!'];
        }

        $stmt = $this->db->prepare("
            UPDATE akun 
            SET email = ?, hak_akses = ?, role = ?, status_aktif = ? 
            WHERE id_akun = ?
        ");
        $success = $stmt->execute([$email, $hak_akses, $role, $status_aktif, $id_akun]);

        return [
            'status' => $success,
            'message' => $success ? 'Data akun berhasil diperbarui!' : 'Gagal memperbarui data akun.'
        ];
    }

    // [PC-2.3.02] Menghapus data akun pengguna dari sistem
    public function hapus_akun($id_akun) {
        if (empty($id_akun)) {
            return ['status' => false, 'message' => 'ID Akun tidak valid!'];
        }

        $stmt = $this->db->prepare("DELETE FROM akun WHERE id_akun = ?");
        $success = $stmt->execute([$id_akun]);

        return [
            'status' => $success,
            'message' => $success ? 'Akun berhasil dihapus dari sistem!' : 'Gagal menghapus akun.'
        ];
    }
}
