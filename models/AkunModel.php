<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';

/**
 * Model Akun
 * Menangani seluruh akses data dan aturan bisnis tabel `akun`.
 */
final class AkunModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Peta peran organisasi ke hak akses, kelas pill, dan filter UI.
     *
     * @return array<string, array{hak: string, class: string, filter: string}>
     */
    public static function petaRole(): array
    {
        return [
            'Pemilik Toko' => ['hak' => 'SUPER_ADMIN', 'class' => 'pill-pink', 'filter' => 'owner'],
            'Kasir' => ['hak' => 'KASIR_POS', 'class' => 'pill-blue', 'filter' => 'kasir'],
            'Petugas Stok' => ['hak' => 'PETUGAS_GUDANG', 'class' => 'pill-teal', 'filter' => 'stok'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function semua(): array
    {
        return $this->db->query('SELECT * FROM akun ORDER BY id_akun ASC')->fetchAll();
    }

    /** @return array<string, mixed>|false */
    public function cari(int $idAkun): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM akun WHERE id_akun = ?');
        $stmt->execute([$idAkun]);

        return $stmt->fetch();
    }

    public function emailDipakai(string $email, int $kecualiIdAkun = 0): bool
    {
        $stmt = $this->db->prepare('SELECT id_akun FROM akun WHERE email = ? AND id_akun <> ?');
        $stmt->execute([$email, $kecualiIdAkun]);

        return $stmt->fetchColumn() !== false;
    }

    public function jumlahOwner(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM akun WHERE role = 'Pemilik Toko'")->fetchColumn();
    }

    public function punyaTransaksi(int $idAkun): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM transaksi WHERE id_akun = ?');
        $stmt->execute([$idAkun]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param array{nama_lengkap: string, email: string, role: string, status_aktif: int, password: string|null} $data */
    public function tambah(array $data, string $hakAkses): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO akun (email, nama_lengkap, password_hash, hak_akses, role, status_aktif, last_login_time)
             VALUES (?, ?, ?, ?, ?, ?, NULL)'
        );
        $stmt->execute([
            $data['email'],
            $data['nama_lengkap'],
            password_hash((string) $data['password'], PASSWORD_BCRYPT),
            $hakAkses,
            $data['role'],
            $data['status_aktif'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array{nama_lengkap: string, email: string, role: string, status_aktif: int, password: string|null} $data */
    public function ubah(int $idAkun, array $data, string $hakAkses): void
    {
        if ($data['password'] !== null) {
            $stmt = $this->db->prepare(
                'UPDATE akun SET email = ?, nama_lengkap = ?, password_hash = ?, hak_akses = ?, role = ?, status_aktif = ?
                 WHERE id_akun = ?'
            );
            $stmt->execute([
                $data['email'],
                $data['nama_lengkap'],
                password_hash((string) $data['password'], PASSWORD_BCRYPT),
                $hakAkses,
                $data['role'],
                $data['status_aktif'],
                $idAkun,
            ]);

            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE akun SET email = ?, nama_lengkap = ?, hak_akses = ?, role = ?, status_aktif = ?
             WHERE id_akun = ?'
        );
        $stmt->execute([
            $data['email'],
            $data['nama_lengkap'],
            $hakAkses,
            $data['role'],
            $data['status_aktif'],
            $idAkun,
        ]);
    }

    public function hapus(int $idAkun): void
    {
        $this->db->prepare('DELETE FROM akun WHERE id_akun = ?')->execute([$idAkun]);
    }

    /**
     * Membaca sekaligus memvalidasi input form akun.
     *
     * @return array{nama_lengkap: string, email: string, role: string, status_aktif: int, password: string|null}
     */
    public static function validasi(array $input, bool $wajibPassword): array
    {
        $errors = [];
        $roles = self::petaRole();

        $nama = trim((string) ($input['nama_lengkap'] ?? ''));
        $panjangNama = function_exists('mb_strlen') ? mb_strlen($nama) : strlen($nama);
        if ($nama === '') {
            $errors[] = 'Nama lengkap pegawai wajib diisi.';
        } elseif ($panjangNama < 3) {
            $errors[] = 'Nama lengkap minimal 3 karakter.';
        } elseif ($panjangNama > 100) {
            $errors[] = 'Nama lengkap maksimal 100 karakter.';
        }

        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Alamat surel tidak valid.';
        } elseif (strlen($email) > 255) {
            $errors[] = 'Alamat surel maksimal 255 karakter.';
        }

        $role = trim((string) ($input['role'] ?? ''));
        if (!isset($roles[$role])) {
            $errors[] = 'Peran organisasi wajib dipilih dengan benar.';
        }

        $password = (string) ($input['password'] ?? '');
        $panjangPassword = strlen($password);

        if ($wajibPassword || $password !== '') {
            if ($panjangPassword < 6) {
                $errors[] = 'Kata sandi minimal 6 karakter.';
            } elseif ($panjangPassword > 100) {
                $errors[] = 'Kata sandi maksimal 100 karakter.';
            }
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        return [
            'nama_lengkap' => $nama,
            'email' => $email,
            'role' => $role,
            'status_aktif' => ((string) ($input['status_aktif'] ?? '0')) === '1' ? 1 : 0,
            'password' => $password !== '' ? $password : null,
        ];
    }
}
