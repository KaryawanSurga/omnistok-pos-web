<?php
declare(strict_types=1);

/**
 * Helper bersama untuk halaman CRUD OmniStok
 * (Kelola Akun, Kelola Diskon, dan Daftar Produk).
 *
 * Dimuat oleh halaman root setelah config/auth.php, sehingga fungsi di sini
 * tidak lagi didefinisikan ulang di masing-masing halaman.
 */

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('csrfToken')) {
    function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrfField')) {
    function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
    }
}

if (!function_exists('verifyCsrf')) {
    function verifyCsrf(): void
    {
        $submitted = (string) ($_POST['csrf_token'] ?? '');
        $expected = (string) ($_SESSION['csrf_token'] ?? '');

        if ($submitted === '' || $expected === '' || !hash_equals($expected, $submitted)) {
            throw new InvalidArgumentException('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
        }
    }
}

if (!function_exists('redirectWithFlash')) {
    function redirectWithFlash(string $type, string $message, string $target): never
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        header('Location: ' . $target);
        exit;
    }
}

if (!function_exists('ambilFlash')) {
    /** @return array{type: string, message: string}|null */
    function ambilFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        if (!is_array($flash) || !isset($flash['type'], $flash['message'])) {
            return null;
        }

        return ['type' => (string) $flash['type'], 'message' => (string) $flash['message']];
    }
}

if (!function_exists('tanggalValid')) {
    function tanggalValid(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
    }
}

if (!function_exists('formatRupiah')) {
    function formatRupiah(float|int|string $value): string
    {
        return 'Rp ' . number_format((float) $value, 0, ',', '.');
    }
}

if (!function_exists('formatPersen')) {
    function formatPersen(float|int|string $value): string
    {
        $formatted = number_format((float) $value, 2, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }
}

if (!function_exists('formatAngka')) {
    function formatAngka(float $value): string
    {
        $formatted = number_format($value, 2, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }
}

if (!function_exists('formatTanggalIndo')) {
    function formatTanggalIndo(string $date, bool $withYear = true): string
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }

        $bulan = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        $label = date('d', $timestamp) . ' ' . $bulan[(int) date('n', $timestamp)];

        return $withYear ? $label . ' ' . date('Y', $timestamp) : $label;
    }
}

if (!function_exists('kodeProduk')) {
    function kodeProduk(int $idProduk): string
    {
        return sprintf('PRD-%03d', $idProduk);
    }
}

if (!function_exists('hitungMargin')) {
    function hitungMargin(float $hargaJual, float $hargaModal): float
    {
        if ($hargaJual <= 0) {
            return 0.0;
        }

        return round((($hargaJual - $hargaModal) / $hargaJual) * 100, 2);
    }
}

if (!function_exists('namaTampilAkun')) {
    function namaTampilAkun(array $akun): string
    {
        $nama = trim((string) ($akun['nama_lengkap'] ?? ''));
        if ($nama !== '') {
            return $nama;
        }

        $email = trim((string) ($akun['email'] ?? ''));
        $prefix = strstr($email, '@', true);
        $prefix = $prefix !== false ? $prefix : $email;
        $prefix = str_replace(['.', '_', '-'], ' ', $prefix);
        $rapi = ucwords(trim($prefix));

        return $rapi !== '' ? $rapi : 'Pengguna';
    }
}

if (!function_exists('namaTampil')) {
    /** Alias kompatibilitas untuk view lama. */
    function namaTampil(array $akun): string
    {
        return namaTampilAkun($akun);
    }
}

if (!function_exists('inisialNama')) {
    function inisialNama(string $nama): string
    {
        $kata = preg_split('/\s+/', trim($nama)) ?: [];
        $kata = array_values(array_filter($kata, static fn ($item): bool => $item !== ''));

        if ($kata === []) {
            return 'PG';
        }

        $hurufPertama = function_exists('mb_substr') ? mb_substr($kata[0], 0, 1) : substr($kata[0], 0, 1);
        $hurufTerakhir = count($kata) > 1
            ? (function_exists('mb_substr') ? mb_substr($kata[count($kata) - 1], 0, 1) : substr($kata[count($kata) - 1], 0, 1))
            : (function_exists('mb_substr') ? mb_substr($kata[0], 1, 1) : substr($kata[0], 1, 1));

        $inisial = strtoupper($hurufPertama . $hurufTerakhir);

        return $inisial !== '' ? $inisial : 'PG';
    }
}

if (!function_exists('formatLoginTerakhir')) {
    function formatLoginTerakhir(?string $waktu): string
    {
        if ($waktu === null || trim($waktu) === '') {
            return 'Belum pernah login';
        }

        $timestamp = strtotime($waktu);
        if ($timestamp === false) {
            return $waktu;
        }

        if (date('Y-m-d', $timestamp) === date('Y-m-d')) {
            return 'Hari ini, ' . date('H:i', $timestamp);
        }

        if (date('Y-m-d', $timestamp) === date('Y-m-d', strtotime('-1 day'))) {
            return 'Kemarin, ' . date('H:i', $timestamp);
        }

        return date('d M Y, H:i', $timestamp);
    }
}

if (!function_exists('promoStatus')) {
    /**
     * @return array{key: string, label: string, class: string, note: string, noteClass: string}
     */
    function promoStatus(array $diskon): array
    {
        $today = new DateTimeImmutable('today');
        $mulai = new DateTimeImmutable((string) $diskon['tanggal_mulai']);
        $selesai = new DateTimeImmutable((string) $diskon['tanggal_selesai']);
        $statusAktif = (int) ($diskon['status_aktif'] ?? 0) === 1;

        if (!$statusAktif) {
            return [
                'key' => 'inactive',
                'label' => 'Nonaktif',
                'class' => 'pill-gray',
                'note' => 'Tidak diaktifkan',
                'noteClass' => 'is-muted',
            ];
        }

        if ($mulai > $today) {
            $hari = (int) $today->diff($mulai)->days;

            return [
                'key' => 'scheduled',
                'label' => 'Dijadwalkan',
                'class' => 'pill-blue',
                'note' => 'Mulai dalam ' . $hari . ' hari',
                'noteClass' => 'is-info',
            ];
        }

        if ($selesai < $today) {
            return [
                'key' => 'expired',
                'label' => 'Berakhir',
                'class' => 'pill-gray',
                'note' => 'Kedaluwarsa ' . formatTanggalIndo($selesai->format('Y-m-d')),
                'noteClass' => 'is-muted',
            ];
        }

        $sisa = (int) $today->diff($selesai)->days;
        $note = match (true) {
            $sisa === 0 => 'Berakhir hari ini',
            $sisa === 1 => 'Sisa 1 hari',
            default => 'Sisa ' . $sisa . ' hari',
        };

        return [
            'key' => 'active',
            'label' => 'Aktif',
            'class' => 'pill-green',
            'note' => $note,
            'noteClass' => $sisa <= 2 ? 'is-warning' : '',
        ];
    }
}
