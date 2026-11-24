<?php
/**
 * Kontroler: MutasiStokController
 * Penulis: Fauzi Al Wahid (FW - 255150400111016)
 * Referensi CD: CD-01 (Melihat Laporan Mutasi Stok) & SD-01
 * Referensi Pseudocode: PC-01.01 s/d PC-01.04
 * Arsitektur MVC:
 * - Model : models/MutasiStokModel.php
 * - View  : views/laporan_mutasi/index.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/MutasiStokModel.php';
require_once __DIR__ . '/ReportPdfHelper.php';

class MutasiStokController
{
    private MutasiStokModel $model;

    public function __construct(?PDO $db = null)
    {
        if (date_default_timezone_get() !== 'Asia/Jakarta') {
            date_default_timezone_set('Asia/Jakarta');
        }
        $pdo = $db ?? Database::getInstance();
        $this->model = new MutasiStokModel($pdo);
    }

    // [PC-01.01] Membuka tampilan laporan mutasi stok dan memuat parameter default
    public function buka_laporan_mutasi(): array
    {
        $jamSekarang = (int) date('H');
        // Operasional harian 06:00 - 21:00; jika belum jam 21:00 maka default s/d kemarin
        $tgl_akhir = ($jamSekarang >= 21) ? date('Y-m-d') : date('Y-m-d', strtotime('-1 day'));
        $tgl_mulai = date('Y-m-d', strtotime('-30 days'));
        return [
            'tgl_mulai' => $tgl_mulai,
            'tgl_akhir' => $tgl_akhir,
        ];
    }

    // [PC-01.03] Memvalidasi keabsahan format, rentang periode, dan jam operasional (06:00 - 21:00 WIB)
    public function validasi_periode($tgl_mulai, $tgl_akhir): array
    {
        if (empty($tgl_mulai) || empty($tgl_akhir)) {
            return ['valid' => false, 'message' => 'Tanggal awal dan akhir periode wajib ditentukan!'];
        }

        $timeMulai = strtotime($tgl_mulai);
        $timeAkhir = strtotime($tgl_akhir);

        if ($timeMulai === false || $timeAkhir === false) {
            return ['valid' => false, 'message' => 'Format tanggal yang dimasukkan tidak valid!'];
        }

        $tglMulaiNorm  = date('Y-m-d', $timeMulai);
        $tglAkhirNorm  = date('Y-m-d', $timeAkhir);
        $hariIni       = date('Y-m-d');
        $jamSekarang   = (int) date('H');
        $waktuSekarang = date('H:i');

        if ($tglMulaiNorm > $tglAkhirNorm) {
            return ['valid' => false, 'message' => 'Tanggal mulai tidak boleh lebih besar dari tanggal akhir!'];
        }

        if ($tglMulaiNorm > $hariIni || $tglAkhirNorm > $hariIni) {
            return [
                'valid' => false,
                'message' => 'Periode tidak valid: Tanggal yang dipilih melewati tanggal hari ini sehingga data mutasi stok belum tersedia!',
            ];
        }

        // Operasional harian dimulai pukul 06:00 pagi dan selesai pukul 21:00 malam
        if (($tglAkhirNorm === $hariIni || $tglMulaiNorm === $hariIni) && $jamSekarang < 21) {
            return [
                'valid' => false,
                'message' => "Periode tidak valid: Operasional harian dimulai pukul 06:00 pagi dan selesai pukul 21:00 malam. Saat ini pukul {$waktuSekarang} WIB sehingga data mutasi stok tanggal hari ini belum tersedia!",
            ];
        }

        return ['valid' => true, 'message' => 'Periode valid'];
    }

    // [PC-01.02] Mengambil data riwayat mutasi stok berdasarkan periode waktu yang ditentukan
    public function get_laporan_mutasi($tgl_mulai, $tgl_akhir): array
    {
        $validasi = $this->validasi_periode($tgl_mulai, $tgl_akhir);
        if (!$validasi['valid']) {
            return ['status' => false, 'message' => $validasi['message'], 'total_data' => 0, 'data' => []];
        }

        $rows = $this->model->get_data_mutasi((string) $tgl_mulai, (string) $tgl_akhir);

        return [
            'status' => true,
            'message' => 'Data mutasi stok berhasil dimuat.',
            'periode' => ['mulai' => $tgl_mulai, 'akhir' => $tgl_akhir],
            'total_data' => count($rows),
            'data' => $rows,
        ];
    }

    // Mengambil ringkasan ketersediaan stok fisik gudang saat ini
    public function get_ringkasan_stok(): array
    {
        return $this->model->get_ringkasan_stok();
    }

    // [PC-01.04] Mengunduh berkas laporan mutasi stok dalam format PDF
    public function unduh_pdf($tgl_mulai, $tgl_akhir): void
    {
        $hasilMutasi = $this->get_laporan_mutasi($tgl_mulai, $tgl_akhir);
        $ringkasanStok = $this->get_ringkasan_stok();
        ReportPdfHelper::generateLaporanMutasi(
            $tgl_mulai,
            $tgl_akhir,
            $hasilMutasi,
            $ringkasanStok
        );
    }

    /**
     * Menangani permintaan ekspor PDF dari query string.
     */
    public function handleExport(array $query): void
    {
        if (($query['export'] ?? '') !== 'pdf') {
            return;
        }

        $defaultPeriode = $this->buka_laporan_mutasi();
        $tgl_mulai = isset($query['tgl_mulai']) ? trim((string) $query['tgl_mulai']) : $defaultPeriode['tgl_mulai'];
        $tgl_akhir = isset($query['tgl_akhir']) ? trim((string) $query['tgl_akhir']) : $defaultPeriode['tgl_akhir'];

        $validasi = $this->validasi_periode($tgl_mulai, $tgl_akhir);
        if ($validasi['valid']) {
            $this->unduh_pdf($tgl_mulai, $tgl_akhir);
            exit;
        }
    }

    /**
     * Menyiapkan seluruh data untuk View `views/laporan_mutasi/index.php`.
     */
    public function index(array $query = []): array
    {
        $defaultPeriode = $this->buka_laporan_mutasi();
        $tgl_mulai = isset($query['tgl_mulai']) ? trim((string) $query['tgl_mulai']) : $defaultPeriode['tgl_mulai'];
        $tgl_akhir = isset($query['tgl_akhir']) ? trim((string) $query['tgl_akhir']) : $defaultPeriode['tgl_akhir'];

        $hasilMutasi = $this->get_laporan_mutasi($tgl_mulai, $tgl_akhir);
        $ringkasanStok = $this->get_ringkasan_stok();

        $isValid = $hasilMutasi['status'];
        $pesanPeringatan = $hasilMutasi['message'];

        $totalMasuk = 0;
        $totalKeluar = 0;
        $totalPenyesuaian = 0;

        if ($isValid) {
            foreach ($hasilMutasi['data'] as $m) {
                if ($m['jenis_mutasi'] === 'Masuk') {
                    $totalMasuk += abs($m['jumlah']);
                } elseif ($m['jenis_mutasi'] === 'Keluar') {
                    $totalKeluar += abs($m['jumlah']);
                } else {
                    $totalPenyesuaian += abs($m['jumlah']);
                }
            }
        }

        return [
            'activePage'       => 'laporan_mutasi',
            'pageTitle'        => 'Laporan Mutasi Stok',
            'moduleBreadcrumb' => 'REPORTS / LAPORAN MUTASI STOK PERSEDIAAN',
            'tgl_mulai'        => $tgl_mulai,
            'tgl_akhir'        => $tgl_akhir,
            'hasilMutasi'      => $hasilMutasi,
            'ringkasanStok'    => $ringkasanStok,
            'isValid'          => $isValid,
            'pesanPeringatan'  => $pesanPeringatan,
            'totalMasuk'       => $totalMasuk,
            'totalKeluar'      => $totalKeluar,
            'totalPenyesuaian' => $totalPenyesuaian,
        ];
    }
}
