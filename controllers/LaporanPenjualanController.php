<?php
/**
 * Kontroler: LaporanPenjualanController
 * Penulis: Fauzi Al Wahid (FW - 255150400111016)
 * Referensi CD: CD-03 (Melihat Laporan Penjualan dan Laba) & SD-06
 * Referensi Pseudocode: PC-03.01 s/d PC-03.06
 * Arsitektur MVC:
 * - Model : models/LaporanPenjualanModel.php
 * - View  : views/laporan_penjualan/index.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/LaporanPenjualanModel.php';
require_once __DIR__ . '/ReportPdfHelper.php';

class LaporanPenjualanController
{
    private LaporanPenjualanModel $model;
    private array $periode = [];
    private array $data_transaksi_dan_hpp = [];
    private array $ringkasan = [];
    private array $data_detail = [];
    private array $detail_laporan = [];

    public function __construct(?PDO $db = null)
    {
        if (date_default_timezone_get() !== 'Asia/Jakarta') {
            date_default_timezone_set('Asia/Jakarta');
        }
        $pdo = $db ?? Database::getInstance();
        $this->model = new LaporanPenjualanModel($pdo);
    }

    /**
     * [PC-03.01] Membuka tampilan awal laporan penjualan & laba dengan rentang default
     */
    public function buka_laporan_penjualan(): array
    {
        $jamSekarang = (int) date('H');
        // Penjualan harian dimulai 06:00 pagi dan selesai 21:00 malam; jika belum jam 21:00 gunakan s/d kemarin
        $tgl_akhir = ($jamSekarang >= 21) ? date('Y-m-d') : date('Y-m-d', strtotime('-1 day'));
        $tgl_mulai = date('Y-m-d', strtotime('-30 days'));
        return [
            'tgl_mulai' => $tgl_mulai,
            'tgl_akhir' => $tgl_akhir,
        ];
    }

    /**
     * [PC-03.03] Memvalidasi keabsahan parameter rentang periode tanggal & jam operasional (06:00 - 21:00 WIB)
     */
    public function validasi_periode($tgl_mulai, $tgl_akhir): array
    {
        if (empty($tgl_mulai) || empty($tgl_akhir)) {
            return [
                'valid' => false,
                'message' => 'Tanggal awal dan tanggal akhir periode wajib diisi!',
            ];
        }

        $timeMulai = strtotime($tgl_mulai);
        $timeAkhir = strtotime($tgl_akhir);

        if ($timeMulai === false || $timeAkhir === false) {
            return [
                'valid' => false,
                'message' => 'Format tanggal yang dimasukkan tidak valid!',
            ];
        }

        $tglMulaiNorm  = date('Y-m-d', $timeMulai);
        $tglAkhirNorm  = date('Y-m-d', $timeAkhir);
        $hariIni       = date('Y-m-d');
        $jamSekarang   = (int) date('H');
        $waktuSekarang = date('H:i');

        if ($tglMulaiNorm > $tglAkhirNorm) {
            return [
                'valid' => false,
                'message' => 'Tanggal mulai tidak boleh lebih besar dari tanggal akhir periode!',
            ];
        }

        if ($tglMulaiNorm > $hariIni || $tglAkhirNorm > $hariIni) {
            return [
                'valid' => false,
                'message' => 'Periode tidak valid: Tanggal yang dipilih melewati tanggal hari ini sehingga data penjualan belum tersedia!',
            ];
        }

        // Penjualan harian dimulai pukul 06:00 pagi dan selesai pukul 21:00 malam
        if (($tglAkhirNorm === $hariIni || $tglMulaiNorm === $hariIni) && $jamSekarang < 21) {
            return [
                'valid' => false,
                'message' => "Periode tidak valid: Penjualan harian dimulai pukul 06:00 pagi dan selesai pukul 21:00 malam. Saat ini pukul {$waktuSekarang} WIB sehingga data penjualan dan laba untuk tanggal hari ini belum tersedia!",
            ];
        }

        return [
            'valid' => true,
            'message' => 'Periode valid.',
        ];
    }

    /**
     * [SD-06 / penjualan_model.get_transaksi_dan_hpp] Mendelegasikan pengambilan transaksi & HPP ke Model
     */
    public function get_transaksi_dan_hpp($tgl_mulai, $tgl_akhir): array
    {
        $this->data_transaksi_dan_hpp = $this->model->get_transaksi_dan_hpp((string) $tgl_mulai, (string) $tgl_akhir);
        return $this->data_transaksi_dan_hpp;
    }

    /**
     * [PC-03.04 / SD-06] Menghitung total penjualan, HPP, laba kotor, dan margin laba kotor
     */
    public function hitung_ringkasan_penjualan_dan_laba($dataTransaksi, $tgl_mulai, $tgl_akhir): array
    {
        $totalPenjualan = 0;
        $totalHpp = 0;
        $jumlahProduk = 0;
        $produkUnik = [];
        $transaksiUnik = [];

        foreach ($dataTransaksi as $detail) {
            $totalPenjualan += (float) $detail['subtotal'];
            $totalHpp       += (float) $detail['hpp_total'];
            $jumlahProduk   += (int) $detail['jumlah_beli'];
            $produkUnik[$detail['id_produk']] = true;
            $transaksiUnik[$detail['id_transaksi']] = true;
        }

        $labaKotor = $totalPenjualan - $totalHpp;
        $margin = ($totalPenjualan > 0) ? round(($labaKotor / $totalPenjualan) * 100, 2) : 0;
        $days = (int) round((strtotime($tgl_akhir) - strtotime($tgl_mulai)) / 86400) + 1;

        $this->ringkasan = [
            'penjualan_bersih' => $totalPenjualan,
            'total_hpp'        => $totalHpp,
            'laba_kotor'       => $labaKotor,
            'margin'           => $margin,
            'total_qty'        => $jumlahProduk,
            'total_sku'        => count($produkUnik),
            'total_transaksi'  => count($transaksiUnik),
            'jumlah_hari'      => $days,
        ];

        return $this->ringkasan;
    }

    /**
     * [PC-03.02 / SD-06] Mengelola pengambilan laporan penjualan (Ringkasan & Tren)
     */
    public function get_laporan_penjualan($tgl_mulai, $tgl_akhir): array
    {
        $this->periode = ['mulai' => $tgl_mulai, 'akhir' => $tgl_akhir];
        $validasi = $this->validasi_periode($tgl_mulai, $tgl_akhir);

        if (!$validasi['valid']) {
            return [
                'status' => false,
                'message' => $validasi['message'],
                'data_kosong' => false,
                'ringkasan' => [
                    'penjualan_bersih' => 0,
                    'total_hpp' => 0,
                    'laba_kotor' => 0,
                    'margin' => 0,
                    'total_qty' => 0,
                    'total_sku' => 0,
                    'total_transaksi' => 0,
                    'jumlah_hari' => 0,
                ],
                'tren_harian' => [],
                'detail_produk' => [],
            ];
        }

        $dataTransaksi = $this->get_transaksi_dan_hpp($tgl_mulai, $tgl_akhir);
        if (empty($dataTransaksi)) {
            $days = (int) round((strtotime($tgl_akhir) - strtotime($tgl_mulai)) / 86400) + 1;
            return [
                'status' => true,
                'message' => 'Data penjualan tidak tersedia pada periode yang dipilih.',
                'data_kosong' => true,
                'periode' => $this->periode,
                'ringkasan' => [
                    'penjualan_bersih' => 0,
                    'total_hpp' => 0,
                    'laba_kotor' => 0,
                    'margin' => 0,
                    'total_qty' => 0,
                    'total_sku' => 0,
                    'total_transaksi' => 0,
                    'jumlah_hari' => $days,
                ],
                'tren_harian' => [],
                'detail_produk' => [],
            ];
        }

        $ringkasan = $this->hitung_ringkasan_penjualan_dan_laba($dataTransaksi, $tgl_mulai, $tgl_akhir);
        $trenHarian = $this->get_tren_harian($tgl_mulai, $tgl_akhir);
        $detailProduk = $this->susun_detail_laporan($dataTransaksi, 'produk');

        return [
            'status' => true,
            'message' => 'Laporan penjualan dan laba kotor berhasil dimuat.',
            'data_kosong' => false,
            'periode' => $this->periode,
            'ringkasan' => $ringkasan,
            'tren_harian' => $trenHarian,
            'detail_produk' => $detailProduk,
        ];
    }

    /**
     * [SD-06 / penjualan_model.get_data_detail] Mendelegasikan pengambilan baris detail ke Model
     */
    public function get_data_detail($tgl_mulai, $tgl_akhir, $jenis_detail = 'produk'): array
    {
        $this->data_detail = $this->model->get_data_detail((string) $tgl_mulai, (string) $tgl_akhir, (string) $jenis_detail);
        return $this->data_detail;
    }

    /**
     * [PC-03.06 / SD-06] Menyusun detail laporan penjualan berdasarkan jenis_detail ('produk' atau 'transaksi')
     */
    public function susun_detail_laporan($dataDetail, $jenis_detail = 'produk'): array
    {
        if ($jenis_detail === 'transaksi') {
            $kelompokTransaksi = [];
            foreach ($dataDetail as $baris) {
                $idTrx = $baris['id_transaksi'];
                if (!isset($kelompokTransaksi[$idTrx])) {
                    $kelompokTransaksi[$idTrx] = [
                        'id_transaksi'      => $idTrx,
                        'tanggal_transaksi' => $baris['tanggal_transaksi'],
                        'metode_bayar'      => $baris['metode_bayar'] ?? 'Tunai',
                        'total_qty'         => 0,
                        'total_penjualan'   => 0,
                        'total_hpp'         => 0,
                        'laba_kotor'        => 0,
                        'margin'            => 0,
                    ];
                }
                $kelompokTransaksi[$idTrx]['total_qty']       += (int) $baris['jumlah_beli'];
                $kelompokTransaksi[$idTrx]['total_penjualan'] += (float) $baris['subtotal'];
                $kelompokTransaksi[$idTrx]['total_hpp']       += (float) $baris['hpp_total'];
            }

            $hasil = [];
            foreach ($kelompokTransaksi as $trx) {
                $trx['laba_kotor'] = $trx['total_penjualan'] - $trx['total_hpp'];
                $trx['margin'] = ($trx['total_penjualan'] > 0)
                    ? round(($trx['laba_kotor'] / $trx['total_penjualan']) * 100, 2)
                    : 0;
                $hasil[] = $trx;
            }
            $this->detail_laporan = $hasil;
            return $hasil;
        }

        // Default PC-03.06: Pengelompokan berdasarkan ID_Produk
        $kelompokProduk = [];
        foreach ($dataDetail as $baris) {
            $idProd = $baris['id_produk'];
            if (!isset($kelompokProduk[$idProd])) {
                $kelompokProduk[$idProd] = [
                    'id_produk'       => $idProd,
                    'nama_produk'     => $baris['nama_produk'],
                    'kategori'        => $baris['kategori'] ?? '-',
                    'total_qty'       => 0,
                    'total_penjualan' => 0,
                    'total_hpp'       => 0,
                    'laba_kotor'      => 0,
                    'margin'          => 0,
                ];
            }
            $kelompokProduk[$idProd]['total_qty']       += (int) $baris['jumlah_beli'];
            $kelompokProduk[$idProd]['total_penjualan'] += (float) $baris['subtotal'];
            $kelompokProduk[$idProd]['total_hpp']       += (float) $baris['hpp_total'];
        }

        $hasil = [];
        foreach ($kelompokProduk as $prod) {
            $prod['laba_kotor'] = $prod['total_penjualan'] - $prod['total_hpp'];
            $prod['margin'] = ($prod['total_penjualan'] > 0)
                ? round(($prod['laba_kotor'] / $prod['total_penjualan']) * 100, 2)
                : 0;
            $hasil[] = $prod;
        }

        usort($hasil, function ($a, $b) {
            return strcasecmp($a['nama_produk'], $b['nama_produk']);
        });

        $this->detail_laporan = $hasil;
        return $hasil;
    }

    /**
     * [PC-03.05 / SD-06] Mengelola pengambilan detail laporan saat pengguna memilih detail laporan
     */
    public function get_detail_laporan($tgl_mulai, $tgl_akhir, $jenis_detail = 'produk'): array
    {
        $validasi = $this->validasi_periode($tgl_mulai, $tgl_akhir);
        $jenisValid = in_array($jenis_detail, ['produk', 'transaksi'], true);

        if (!$validasi['valid'] || !$jenisValid) {
            return [
                'status' => false,
                'message' => !$validasi['valid'] ? $validasi['message'] : 'Jenis detail laporan tidak dikenali!',
                'jenis_detail' => $jenis_detail,
                'data' => [],
            ];
        }

        $dataDetail = $this->get_data_detail($tgl_mulai, $tgl_akhir, $jenis_detail);
        if (empty($dataDetail)) {
            return [
                'status' => true,
                'message' => 'Data detail penjualan tidak tersedia pada periode ini.',
                'jenis_detail' => $jenis_detail,
                'data' => [],
            ];
        }

        $tersusun = $this->susun_detail_laporan($dataDetail, $jenis_detail);
        return [
            'status' => true,
            'message' => 'Detail laporan berhasil disusun.',
            'jenis_detail' => $jenis_detail,
            'data' => $tersusun,
        ];
    }

    /**
     * Menyusun seri tanggal harian untuk visualisasi kurva grafik tren SVG
     */
    private function get_tren_harian($tgl_mulai, $tgl_akhir): array
    {
        $rows = $this->model->get_tren_harian_raw((string) $tgl_mulai, (string) $tgl_akhir);

        $lookup = [];
        foreach ($rows as $r) {
            $lookup[$r['tgl']] = $r;
        }

        $list = [];
        $cur = strtotime($tgl_mulai);
        $last = strtotime($tgl_akhir);
        $stepDays = 1;
        $totalDays = (int) round(($last - $cur) / 86400) + 1;
        if ($totalDays > 31) {
            $stepDays = (int) ceil($totalDays / 31);
        }

        while ($cur <= $last) {
            $dStr = date('Y-m-d', $cur);
            if (isset($lookup[$dStr])) {
                $list[] = [
                    'tanggal'   => $dStr,
                    'label'     => date('d M', $cur),
                    'penjualan' => (float) $lookup[$dStr]['penjualan'],
                    'hpp'       => (float) $lookup[$dStr]['hpp'],
                    'laba'      => (float) $lookup[$dStr]['laba'],
                ];
            } else {
                $list[] = [
                    'tanggal'   => $dStr,
                    'label'     => date('d M', $cur),
                    'penjualan' => 0,
                    'hpp'       => 0,
                    'laba'      => 0,
                ];
            }
            $cur = strtotime("+{$stepDays} days", $cur);
        }

        return $list;
    }

    /**
     * Mengunduh berkas laporan penjualan dan laba kotor dalam format PDF
     */
    public function unduh_pdf($tgl_mulai, $tgl_akhir): void
    {
        $data = $this->get_laporan_penjualan($tgl_mulai, $tgl_akhir);
        ReportPdfHelper::generateLaporanPenjualan(
            $tgl_mulai,
            $tgl_akhir,
            $data['ringkasan'],
            $data['detail_produk']
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

        $defaultPeriode = $this->buka_laporan_penjualan();
        $tgl_mulai = isset($query['tgl_mulai']) ? trim((string) $query['tgl_mulai']) : $defaultPeriode['tgl_mulai'];
        $tgl_akhir = isset($query['tgl_akhir']) ? trim((string) $query['tgl_akhir']) : $defaultPeriode['tgl_akhir'];

        $validasi = $this->validasi_periode($tgl_mulai, $tgl_akhir);
        if ($validasi['valid']) {
            $this->unduh_pdf($tgl_mulai, $tgl_akhir);
            exit;
        }
    }

    /**
     * Menyiapkan seluruh data untuk View `views/laporan_penjualan/index.php`.
     */
    public function index(array $query = []): array
    {
        $defaultPeriode = $this->buka_laporan_penjualan();
        $tgl_mulai = isset($query['tgl_mulai']) ? trim((string) $query['tgl_mulai']) : $defaultPeriode['tgl_mulai'];
        $tgl_akhir = isset($query['tgl_akhir']) ? trim((string) $query['tgl_akhir']) : $defaultPeriode['tgl_akhir'];

        $jenis_detail = isset($query['jenis_detail']) ? trim((string) $query['jenis_detail']) : '';
        $tampilkanDetail = in_array($jenis_detail, ['produk', 'transaksi'], true);

        $laporanData = $this->get_laporan_penjualan($tgl_mulai, $tgl_akhir);
        $isValid = $laporanData['status'];
        $pesanPeringatan = $laporanData['message'];
        $dataKosong = $laporanData['data_kosong'] ?? false;

        $ringkasan = $laporanData['ringkasan'];
        $trenHarian = $laporanData['tren_harian'];

        $detailData = [];
        if ($isValid && !$dataKosong && $tampilkanDetail) {
            $hasilDetail = $this->get_detail_laporan($tgl_mulai, $tgl_akhir, $jenis_detail);
            if ($hasilDetail['status']) {
                $detailData = $hasilDetail['data'];
            }
        }

        return [
            'activePage'       => 'laporan_penjualan',
            'pageTitle'        => 'Laporan Penjualan & Laba',
            'moduleBreadcrumb' => 'REPORTS / SALES & PROFIT ANALYTICS',
            'tgl_mulai'        => $tgl_mulai,
            'tgl_akhir'        => $tgl_akhir,
            'jenis_detail'     => $jenis_detail,
            'tampilkanDetail'  => $tampilkanDetail,
            'isValid'          => $isValid,
            'pesanPeringatan'  => $pesanPeringatan,
            'dataKosong'       => $dataKosong,
            'ringkasan'        => $ringkasan,
            'trenHarian'       => $trenHarian,
            'detailData'       => $detailData,
        ];
    }
}
