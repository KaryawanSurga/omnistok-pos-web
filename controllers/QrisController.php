<?php
/**
 * Controller QRIS - OmniStok POS
 *
 * Menangani payload QRIS milik merchant sendiri:
 * - Membaca & memvalidasi payload hasil pemindaian QRIS (CRC16, tag wajib, NMID)
 * - Menurunkan QRIS statis (tag 01 = 11) menjadi QRIS dinamis (tag 01 = 12,
 *   nominal pada tag 54, CRC16 dihitung ulang)
 * - Menyimpan payload merchant pada `database/qris_merchant.json`
 *
 * Referensi: EMVCo QR Code Specification (TLV, CRC16-CCITT/FALSE, tag 63).
 */

declare(strict_types=1);

final class QrisController
{
    /** @var array<string,mixed>|null */
    private static ?array $config = null;

    public static function config(): array
    {
        if (self::$config === null) {
            $file = dirname(__DIR__) . '/config/qris.php';
            $cfg  = is_file($file) ? require $file : [];
            self::$config = is_array($cfg) ? $cfg : [];
        }

        return self::$config;
    }

    public static function nmid(): string
    {
        return (string) (self::config()['nmid'] ?? '');
    }

    public static function merchantName(): string
    {
        return (string) (self::config()['merchant_name'] ?? '');
    }

    public static function merchantCity(): string
    {
        return (string) (self::config()['merchant_city'] ?? '');
    }

    // ------------------------------------------------------------------
    // Penyimpanan konfigurasi merchant
    // ------------------------------------------------------------------

    /** @return array{payload:string,image:string,updated_at:string} */
    public static function load(): array
    {
        $data = ['payload' => '', 'image' => '', 'updated_at' => ''];
        $file = (string) (self::config()['storage_file'] ?? '');

        if ($file !== '' && is_file($file)) {
            $raw = @file_get_contents($file);
            $json = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($json)) {
                $data['payload']    = isset($json['payload']) ? (string) $json['payload'] : '';
                $data['image']      = isset($json['image']) ? (string) $json['image'] : '';
                $data['updated_at'] = isset($json['updated_at']) ? (string) $json['updated_at'] : '';
            }
        }

        return $data;
    }

    public static function isConfigured(): bool
    {
        $payload = self::load()['payload'];

        return $payload !== '' && self::validate($payload)['ok'];
    }

    /** Simpan payload QRIS yang sudah tervalidasi. */
    public static function savePayload(string $payload): array
    {
        $payload = trim($payload);
        $check   = self::validate($payload);

        if (!$check['ok']) {
            return ['status' => false, 'message' => implode(' ', $check['errors'])];
        }

        $data = self::load();
        $data['payload']    = $payload;
        $data['updated_at'] = date('Y-m-d H:i:s');

        return self::persist($data, 'Payload QRIS berhasil disimpan. QRIS dinamis siap dipakai di menu kasir.');
    }

    /** Simpan lokasi gambar QRIS statis (untuk tampilan cadangan). */
    public static function saveImagePath(string $relativePath): void
    {
        $data = self::load();
        $data['image']      = $relativePath;
        $data['updated_at'] = date('Y-m-d H:i:s');
        self::persist($data);
    }

    public static function clearPayload(): void
    {
        $data = self::load();
        $data['payload']    = '';
        $data['updated_at'] = date('Y-m-d H:i:s');
        self::persist($data);
    }

    private static function persist(array $data, string $successMessage = 'Konfigurasi tersimpan.'): array
    {
        $file = (string) (self::config()['storage_file'] ?? '');
        if ($file === '') {
            return ['status' => false, 'message' => 'Lokasi berkas konfigurasi QRIS tidak ditemukan.'];
        }

        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['status' => false, 'message' => 'Folder penyimpanan konfigurasi QRIS tidak dapat dibuat.'];
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false || @file_put_contents($file, $json . PHP_EOL, LOCK_EX) === false) {
            return [
                'status'  => false,
                'message' => 'Berkas konfigurasi QRIS tidak dapat ditulis (folder database tidak memiliki izin tulis).',
            ];
        }

        return ['status' => true, 'message' => $successMessage, 'data' => $data];
    }

    /** Path gambar QRIS statis yang dipakai sebagai tampilan cadangan. */
    public static function imagePath(): string
    {
        $cfg    = self::config();
        $stored = self::load()['image'];
        $root   = dirname(__DIR__);

        if ($stored !== '' && is_file($root . '/' . ltrim($stored, '/'))) {
            return $stored;
        }

        $base = (string) ($cfg['uploaded_image'] ?? 'assets/qris_merchant_qr');
        foreach (['png', 'jpg', 'jpeg'] as $ext) {
            if (is_file($root . '/' . $base . '.' . $ext)) {
                return $base . '.' . $ext;
            }
        }

        return (string) ($cfg['fallback_image'] ?? 'assets/qris_code.png');
    }

    // ------------------------------------------------------------------
    // TLV & CRC16
    // ------------------------------------------------------------------

    /**
     * Urai string TLV dua digit menjadi pasangan tag => value.
     *
     * @return array{fields:array<string,string>,complete:bool}
     */
    public static function tlv(string $payload): array
    {
        $fields = [];
        $offset = 0;
        $length = strlen($payload);

        while ($offset + 4 <= $length) {
            $tag    = substr($payload, $offset, 2);
            $lenStr = substr($payload, $offset + 2, 2);

            if (!ctype_digit($tag) || !ctype_digit($lenStr)) {
                break;
            }

            $len = (int) $lenStr;
            if ($offset + 4 + $len > $length) {
                break;
            }

            $fields[$tag] = substr($payload, $offset + 4, $len);
            $offset += 4 + $len;
        }

        return ['fields' => $fields, 'complete' => ($offset === $length)];
    }

    /** CRC16-CCITT (FALSE): poly 0x1021, init 0xFFFF. */
    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;
        $len = strlen($data);

        for ($i = 0; $i < $len; $i++) {
            $crc ^= (ord($data[$i]) << 8);
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = (($crc & 0x8000) !== 0) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    public static function crcValid(string $payload): bool
    {
        if (strlen($payload) < 8) {
            return false;
        }

        $body    = substr($payload, 0, -4);
        $given   = strtoupper(substr($payload, -4));

        if (!preg_match('/^[0-9A-F]{4}$/', $given)) {
            return false;
        }

        if (substr($body, -4) !== '6304') {
            return false;
        }

        return self::crc16($body) === $given;
    }

    /** NMID merchant diambil dari merchant account information (tag 26-51). */
    public static function extractNmid(array $fields): string
    {
        for ($tag = 26; $tag <= 51; $tag++) {
            $key = str_pad((string) $tag, 2, '0', STR_PAD_LEFT);
            if (!isset($fields[$key])) {
                continue;
            }
            $sub = self::tlv($fields[$key])['fields'];
            if (($sub['01'] ?? '') !== '') {
                return $sub['01'];
            }
        }

        return '';
    }

    /** Domain acquirer (mis. ID.CO.QRIS.WWW) dari merchant account information. */
    public static function extractAcquirer(array $fields): string
    {
        for ($tag = 26; $tag <= 51; $tag++) {
            $key = str_pad((string) $tag, 2, '0', STR_PAD_LEFT);
            if (!isset($fields[$key])) {
                continue;
            }
            $sub = self::tlv($fields[$key])['fields'];
            if (($sub['00'] ?? '') !== '') {
                return $sub['00'];
            }
        }

        return '';
    }

    // ------------------------------------------------------------------
    // Validasi & pembentukan QRIS dinamis
    // ------------------------------------------------------------------

    /** @return array{ok:bool,errors:array<int,string>,warnings:array<int,string>,fields:array<string,string>,nmid:string,acquirer:string,merchant:string,city:string,currency:string} */
    public static function validate(string $payload): array
    {
        $payload  = trim($payload);
        $errors   = [];
        $warnings = [];

        if ($payload === '') {
            return [
                'ok'       => false,
                'errors'   => ['Payload QRIS masih kosong.'],
                'warnings' => [],
                'fields'   => [],
                'nmid'     => '',
                'acquirer' => '',
                'merchant' => '',
                'city'     => '',
                'currency' => '',
            ];
        }

        if (preg_match('/[^\x20-\x7E]/', $payload) === 1) {
            $errors[] = 'Payload mengandung karakter tidak valid (hanya karakter ASCII yang diizinkan).';
        }
        if (strlen($payload) < 40) {
            $errors[] = 'Panjang payload terlalu pendek untuk QRIS yang valid.';
        }

        $parsed = self::tlv($payload);
        $fields  = $parsed['fields'];

        if (!$parsed['complete']) {
            $errors[] = 'Struktur TLV payload tidak utuh (ada tag yang panjangnya tidak sesuai).';
        }

        if (($fields['00'] ?? '') !== '01') {
            $errors[] = 'Tag 00 bukan "01" sehingga payload ini bukan QRIS.';
        }
        if (!isset($fields['01'])) {
            $errors[] = 'Tag 01 (indikator statis/dinamis) tidak ditemukan.';
        } elseif (!in_array($fields['01'], ['11', '12'], true)) {
            $warnings[] = 'Tag 01 bernilai "' . $fields['01'] . '" (umumnya 11 = statis, 12 = dinamis).';
        }
        if (!isset($fields['53'])) {
            $errors[] = 'Tag 53 (kode mata uang) tidak ditemukan.';
        } elseif ($fields['53'] !== '360') {
            $errors[] = 'Tag 53 bukan 360 (IDR). Pastikan QRIS rupiah.';
        }
        if (isset($fields['54']) && !ctype_digit($fields['54'])) {
            $errors[] = 'Tag 54 (nominal) berisi nilai bukan angka.';
        }

        if (!self::crcValid($payload)) {
            $errors[] = 'CRC16 pada tag 63 tidak cocok — payload rusak atau terpotong saat disalin.';
        }

        $nmid = self::extractNmid($fields);
        if ($nmid === '') {
            $errors[] = 'NMID merchant tidak ditemukan pada merchant account information (tag 26-51).';
        } elseif (self::nmid() !== '' && $nmid !== self::nmid()) {
            $warnings[] = 'NMID pada QR ("' . $nmid . '") berbeda dengan NMID di config/qris.php ("' . self::nmid() . '").';
        }

        return [
            'ok'       => $errors === [],
            'errors'   => $errors,
            'warnings' => $warnings,
            'fields'   => $fields,
            'nmid'     => $nmid,
            'acquirer' => self::extractAcquirer($fields),
            'merchant' => (string) ($fields['59'] ?? ''),
            'city'     => (string) ($fields['60'] ?? ''),
            'currency' => (string) ($fields['53'] ?? ''),
        ];
    }

    /**
     * Susun ulang payload dengan tag terurut menaik dan CRC16 baru.
     *
     * @param array<string,string> $fields
     */
    private static function build(array $fields): string
    {
        ksort($fields, SORT_STRING);

        $body = '';
        foreach ($fields as $tag => $value) {
            $body .= $tag . str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT) . $value;
        }

        $body .= '6304';

        return $body . self::crc16($body);
    }

    /** Ubah QRIS statis menjadi QRIS dinamis dengan nominal tertentu. */
    public static function dynamic(string $staticPayload, int $amount): string
    {
        $fields = self::tlv(trim($staticPayload))['fields'];

        unset($fields['63']);
        $fields['00'] = '01';
        $fields['01'] = '12';

        if ($amount > 0) {
            $fields['54'] = (string) $amount;
        } else {
            unset($fields['54']);
        }

        return self::build($fields);
    }

    /** Payload dinamis untuk nominal transaksi, atau null bila belum siap. */
    public static function forAmount(int $amount): ?string
    {
        $cfg = self::config();
        $min = (int) ($cfg['min_amount'] ?? 1);
        $max = (int) ($cfg['max_amount'] ?? 10000000);

        if ($amount < $min || $amount > $max) {
            return null;
        }

        $payload = self::load()['payload'];
        if ($payload === '' || !self::validate($payload)['ok']) {
            return null;
        }

        return self::dynamic($payload, $amount);
    }

    /** Nominal rupiah dari tag 54 (0 bila tidak ada / statis). */
    public static function amountOf(string $payload): int
    {
        $value = self::tlv($payload)['fields']['54'] ?? '';

        return ctype_digit($value) ? (int) $value : 0;
    }
}
