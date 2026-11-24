<?php
/**
 * Kontroler: PeringatanStokController
 * Penulis: Fauzi Al Wahid (FW - 255150400111016)
 * Referensi CD: CD-05 (Melihat Peringatan Stok Menipis) & CD-06 (Melihat Peringatan Barang Kadaluwarsa)
 * Hak Akses: Pemilik Toko (owner) & Petugas Stok (gudang)
 * Arsitektur MVC:
 * - Model : models/PeringatanStokModel.php
 * - View  : views/peringatan_stok/index.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/PeringatanStokModel.php';

class PeringatanStokController
{
    private PeringatanStokModel $model;

    public function __construct(?PDO $db = null)
    {
        if (date_default_timezone_get() !== 'Asia/Jakarta') {
            date_default_timezone_set('Asia/Jakarta');
        }
        $pdo = $db ?? Database::getInstance();
        $this->model = new PeringatanStokModel($pdo);
    }

    /**
     * [UC-05] Mendapatkan daftar produk yang stok fisiknya menipis atau habis di bawah batas minimum
     */
    public function get_peringatan_stok_menipis(): array
    {
        $items = $this->model->get_stok_menipis();

        $totalHabis = 0;
        $totalMenipis = 0;
        foreach ($items as $item) {
            if ($item['status_stok'] === 'HABIS') {
                $totalHabis++;
            } else {
                $totalMenipis++;
            }
        }

        return [
            'total_peringatan' => count($items),
            'total_habis'      => $totalHabis,
            'total_menipis'    => $totalMenipis,
            'items'            => $items,
        ];
    }

    /**
     * [UC-06] Mendapatkan daftar batch produk yang telah kadaluwarsa atau mendekati masa kadaluwarsa (<= 90 hari)
     */
    public function get_peringatan_kadaluwarsa(): array
    {
        $today = date('Y-m-d');
        $raw = $this->model->get_batch_produk();

        $items = [];
        $totalExpired = 0;
        $totalWarning = 0;
        $totalSafe = 0;

        foreach ($raw as $row) {
            $tglExp = $row['tanggal_kadaluwarsa'];
            $diffDays = (int) round((strtotime($tglExp) - strtotime($today)) / 86400);

            if ($diffDays < 0) {
                $status = 'KADALUWARSA';
                $label = 'Kedaluwarsa ' . abs($diffDays) . ' hari lalu';
                $pillClass = 'pill-red';
                $totalExpired++;
            } elseif ($diffDays === 0) {
                $status = 'HARI_INI';
                $label = 'Kedaluwarsa Hari Ini';
                $pillClass = 'pill-red';
                $totalExpired++;
            } elseif ($diffDays <= 90) {
                $status = 'MENDEKATI';
                $label = $diffDays . ' hari lagi';
                $pillClass = 'pill-orange';
                $totalWarning++;
            } else {
                $status = 'AMAN';
                $label = $diffDays . ' hari lagi';
                $pillClass = 'pill-green';
                $totalSafe++;
            }

            $row['selisih_hari'] = $diffDays;
            $row['status_exp']   = $status;
            $row['label_exp']    = $label;
            $row['pill_class']   = $pillClass;

            $items[] = $row;
        }

        return [
            'total_batch'   => count($items),
            'total_expired' => $totalExpired,
            'total_warning' => $totalWarning,
            'total_safe'    => $totalSafe,
            'items'         => $items,
        ];
    }

    /**
     * Menyiapkan seluruh data untuk View `views/peringatan_stok/index.php`.
     */
    public function index(array $query = []): array
    {
        $stokMenipisData = $this->get_peringatan_stok_menipis();
        $kadaluwarsaData = $this->get_peringatan_kadaluwarsa();
        $activeTab = (isset($query['tab']) && $query['tab'] === 'kadaluwarsa') ? 'kadaluwarsa' : 'menipis';

        return [
            'activePage'       => 'peringatan_stok',
            'pageTitle'        => 'Peringatan Stok & Kadaluwarsa',
            'moduleBreadcrumb' => 'INVENTORY & REPORTS / NOTIFIKASI PERINGATAN DINI',
            'stokMenipisData'  => $stokMenipisData,
            'kadaluwarsaData'  => $kadaluwarsaData,
            'activeTab'        => $activeTab,
        ];
    }
}
