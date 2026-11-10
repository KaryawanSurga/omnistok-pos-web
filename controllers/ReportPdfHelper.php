<?php
/**
 * Helper: ReportPdfHelper
 * Penulis: Fauzi Al Wahid (FW - 255150400111016)
 * Generator PDF Mandiri (Pure PHP) untuk Laporan Penjualan dan Laporan Mutasi Stok
 * Memenuhi kebutuhan cetak/unduh PDF tanpa dependensi eksternal.
 */

class ReportPdfHelper {
    private $w = 595.28; // A4 Width in points
    private $h = 841.89; // A4 Height in points
    private $pages = [];
    private $curPage = 0;
    private $y = 800;
    private $m = 36; // Margins: 36pt (0.5 inch)

    public function __construct() {
        $this->addPage();
    }

    public function addPage() {
        $this->curPage++;
        $this->pages[$this->curPage] = '';
        $this->y = $this->h - $this->m;
    }

    public function getY() {
        return $this->y;
    }

    public function setY($y) {
        $this->y = $y;
    }

    public function checkPageBreak($neededHeight) {
        if ($this->y - $neededHeight < ($this->m + 28)) {
            $this->addPage();
            return true;
        }
        return false;
    }

    private function escape($str) {
        $str = iconv('UTF-8', 'windows-1252//TRANSLIT', (string)$str);
        return strtr($str, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
    }

    public function rect($x, $y, $w, $h, $fill = null, $stroke = null, $lineWidth = 1) {
        $pdfY = $y - $h;
        $s = "q\n";
        if ($lineWidth) $s .= "$lineWidth w\n";
        if ($fill) $s .= sprintf("%.3f %.3f %.3f rg\n", $fill[0]/255, $fill[1]/255, $fill[2]/255);
        if ($stroke) $s .= sprintf("%.3f %.3f %.3f RG\n", $stroke[0]/255, $stroke[1]/255, $stroke[2]/255);
        $s .= sprintf("%.2f %.2f %.2f %.2f re\n", $x, $pdfY, $w, $h);
        if ($fill && $stroke) $s .= "B\n";
        elseif ($fill) $s .= "f\n";
        elseif ($stroke) $s .= "S\n";
        $s .= "Q\n";
        $this->pages[$this->curPage] .= $s;
    }

    public function line($x1, $y1, $x2, $y2, $color = [200,200,200], $lineWidth = 1) {
        $s = "q\n$lineWidth w\n";
        $s .= sprintf("%.3f %.3f %.3f RG\n", $color[0]/255, $color[1]/255, $color[2]/255);
        $s .= sprintf("%.2f %.2f m %.2f %.2f l S\nQ\n", $x1, $y1, $x2, $y2);
        $this->pages[$this->curPage] .= $s;
    }

    public function text($txt, $x, $y, $size = 10, $bold = false, $color = [30,41,59], $align = 'left', $boxW = 0) {
        $txt = (string)$txt;
        $escaped = $this->escape($txt);
        $font = $bold ? 'F2' : 'F1';
        $charW = $bold ? 0.58 : 0.52;
        $textW = strlen($txt) * $size * $charW;

        $drawX = $x;
        if ($align === 'right' && $boxW > 0) {
            $drawX = $x + $boxW - $textW;
        } elseif ($align === 'center' && $boxW > 0) {
            $drawX = $x + ($boxW - $textW) / 2;
        }

        $s = "q\n";
        $s .= sprintf("%.3f %.3f %.3f rg\n", $color[0]/255, $color[1]/255, $color[2]/255);
        $s .= "BT\n/$font $size Tf\n";
        $s .= sprintf("1 0 0 1 %.2f %.2f Tm\n", $drawX, $y);
        $s .= "($escaped) Tj\nET\nQ\n";
        $this->pages[$this->curPage] .= $s;
    }

    public function render() {
        $totalPages = count($this->pages);
        for ($i = 1; $i <= $totalPages; $i++) {
            $footerY = 22;
            $s = "q\n0.5 w\n0.8 0.8 0.8 RG\n";
            $s .= sprintf("%.2f %.2f m %.2f %.2f l S\nQ\n", $this->m, $footerY + 12, $this->w - $this->m, $footerY + 12);
            $this->pages[$i] .= $s;

            // Footer left
            $escapedLeft = $this->escape('OmniStok POS System • Dokumen Laporan Resmi • Penulis: Fauzi Al Wahid (FW)');
            $sLeft = "q\n0.45 0.45 0.45 rg\nBT\n/F1 8 Tf\n";
            $sLeft .= sprintf("1 0 0 1 %.2f %.2f Tm\n(%s) Tj\nET\nQ\n", $this->m, $footerY, $escapedLeft);
            $this->pages[$i] .= $sLeft;

            // Footer right
            $rightTxt = "Halaman $i dari $totalPages";
            $escapedRight = $this->escape($rightTxt);
            $sRight = "q\n0.45 0.45 0.45 rg\nBT\n/F1 8 Tf\n";
            $sRight .= sprintf("1 0 0 1 %.2f %.2f Tm\n(%s) Tj\nET\nQ\n", $this->w - $this->m - 75, $footerY, $escapedRight);
            $this->pages[$i] .= $sRight;
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        $offsets[1] = strlen($pdf);
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        $kids = [];
        for ($i = 1; $i <= $totalPages; $i++) {
            $kids[] = (2 + $i) . " 0 R";
        }
        $offsets[2] = strlen($pdf);
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count $totalPages >>\nendobj\n";

        $fontBoldId = 2 + $totalPages * 2 + 1;
        $fontRegId  = $fontBoldId + 1;

        for ($i = 1; $i <= $totalPages; $i++) {
            $pageObjId = 2 + $i;
            $contentObjId = 2 + $totalPages + $i;
            $offsets[$pageObjId] = strlen($pdf);
            $pdf .= "$pageObjId 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->w} {$this->h}] /Resources << /Font << /F1 $fontRegId 0 R /F2 $fontBoldId 0 R >> >> /Contents $contentObjId 0 R >>\nendobj\n";
        }

        for ($i = 1; $i <= $totalPages; $i++) {
            $contentObjId = 2 + $totalPages + $i;
            $stream = $this->pages[$i];
            $len = strlen($stream);
            $offsets[$contentObjId] = strlen($pdf);
            $pdf .= "$contentObjId 0 obj\n<< /Length $len >>\nstream\n" . $stream . "endstream\nendobj\n";
        }

        $offsets[$fontBoldId] = strlen($pdf);
        $pdf .= "$fontBoldId 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n";

        $offsets[$fontRegId] = strlen($pdf);
        $pdf .= "$fontRegId 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

        $xrefOffset = strlen($pdf);
        $totalObjs = $fontRegId + 1;
        $pdf .= "xref\n0 $totalObjs\n0000000000 65535 f \n";
        for ($i = 1; $i < $totalObjs; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $pdf .= "trailer\n<< /Size $totalObjs /Root 1 0 R >>\nstartxref\n$xrefOffset\n%%EOF";
        return $pdf;
    }

    public static function sendDownload($pdfContent, $filename) {
        // Bersihkan output buffer sebelumnya agar file PDF tidak korup
        if (ob_get_length()) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $pdfContent;
        exit;
    }

    /**
     * Generate PDF Laporan Penjualan & Laba
     */
    public static function generateLaporanPenjualan($tgl_mulai, $tgl_akhir, $ringkasan, $detail) {
        $pdf = new self();
        $m = 36;
        $contentW = 595.28 - ($m * 2);

        // Header Banner
        $pdf->rect($m, 805, $contentW, 52, [15, 118, 110]);
        $pdf->text('OMNISTOK POS & INVENTORY MANAGEMENT SYSTEM', $m + 14, 783, 14, true, [255, 255, 255]);
        $pdf->text('LAPORAN PENJUALAN & LABA KOTOR [UI-05]', $m + 14, 768, 10, false, [204, 251, 241]);

        // Metadata box
        $curY = 742;
        $pdf->rect($m, $curY, $contentW, 36, [248, 250, 252], [226, 232, 240]);
        $pdf->text('Periode Laporan: ' . date('d/m/Y', strtotime($tgl_mulai)) . ' s/d ' . date('d/m/Y', strtotime($tgl_akhir)), $m + 10, $curY - 14, 9, true, [15, 23, 42]);
        $pdf->text('Penanggung Jawab: Fauzi Al Wahid (Pemilik Toko - FW)', $m + 10, $curY - 26, 8, false, [100, 116, 139]);
        $pdf->text('Dicetak pada: ' . date('d/m/Y H:i') . ' WIB', $m + $contentW - 150, $curY - 14, 8, false, [100, 116, 139]);
        $pdf->text('Total Transaksi: ' . (int)($ringkasan['total_transaksi'] ?? 0) . ' Transaksi', $m + $contentW - 150, $curY - 26, 8, true, [15, 118, 110]);

        $curY -= 46;

        // 4 Summary Metric Cards
        $cardW = ($contentW - 18) / 4;
        $cardH = 46;

        // Card 1: Penjualan Bersih
        $pdf->rect($m + ($cardW + 6) * 0, $curY, $cardW, $cardH, [255, 255, 255], [226, 232, 240]);
        $pdf->text('PENJUALAN BERSIH', $m + ($cardW + 6) * 0 + 8, $curY - 13, 7, true, [100, 116, 139]);
        $pdf->text('Rp ' . number_format($ringkasan['penjualan_bersih'], 0, ',', '.'), $m + ($cardW + 6) * 0 + 8, $curY - 28, 9.5, true, [15, 23, 42]);
        $pdf->text('Setelah diskon', $m + ($cardW + 6) * 0 + 8, $curY - 38, 6.5, false, [148, 163, 184]);

        // Card 2: Total HPP
        $pdf->rect($m + ($cardW + 6) * 1, $curY, $cardW, $cardH, [255, 255, 255], [226, 232, 240]);
        $pdf->text('TOTAL HPP', $m + ($cardW + 6) * 1 + 8, $curY - 13, 7, true, [100, 116, 139]);
        $pdf->text('Rp ' . number_format($ringkasan['total_hpp'], 0, ',', '.'), $m + ($cardW + 6) * 1 + 8, $curY - 28, 9.5, true, [15, 23, 42]);
        $pdf->text('Biaya perolehan', $m + ($cardW + 6) * 1 + 8, $curY - 38, 6.5, false, [148, 163, 184]);

        // Card 3: Laba Kotor
        $pdf->rect($m + ($cardW + 6) * 2, $curY, $cardW, $cardH, [255, 255, 255], [226, 232, 240]);
        $pdf->text('LABA KOTOR', $m + ($cardW + 6) * 2 + 8, $curY - 13, 7, true, [100, 116, 139]);
        $pdf->text('Rp ' . number_format($ringkasan['laba_kotor'], 0, ',', '.'), $m + ($cardW + 6) * 2 + 8, $curY - 28, 9.5, true, [15, 118, 110]);
        $pdf->text('Penjualan - HPP', $m + ($cardW + 6) * 2 + 8, $curY - 38, 6.5, false, [148, 163, 184]);

        // Card 4: Margin
        $pdf->rect($m + ($cardW + 6) * 3, $curY, $cardW, $cardH, [255, 255, 255], [226, 232, 240]);
        $pdf->text('MARGIN LABA', $m + ($cardW + 6) * 3 + 8, $curY - 13, 7, true, [100, 116, 139]);
        $pdf->text(number_format($ringkasan['margin'], 2, ',', '.') . '%', $m + ($cardW + 6) * 3 + 8, $curY - 28, 9.5, true, [2, 132, 199]);
        $pdf->text('Laba / Penjualan', $m + ($cardW + 6) * 3 + 8, $curY - 38, 6.5, false, [148, 163, 184]);

        $curY -= ($cardH + 16);

        // Section Title: Rincian Penjualan per Produk
        $pdf->text('Rincian Penjualan per Produk', $m, $curY, 11, true, [15, 23, 42]);
        $pdf->text('Total ' . count($detail) . ' SKU terdata', $m + $contentW - 90, $curY, 8, false, [100, 116, 139]);
        $curY -= 14;

        // Table Columns
        $cols = [
            ['title' => 'NO', 'w' => 26, 'align' => 'center'],
            ['title' => 'SKU', 'w' => 54, 'align' => 'left'],
            ['title' => 'NAMA PRODUK', 'w' => 150, 'align' => 'left'],
            ['title' => 'KATEGORI', 'w' => 65, 'align' => 'left'],
            ['title' => 'QTY', 'w' => 42, 'align' => 'center'],
            ['title' => 'PENJUALAN (RP)', 'w' => 66, 'align' => 'right'],
            ['title' => 'HPP TOTAL (RP)', 'w' => 64, 'align' => 'right'],
            ['title' => 'LABA KOTOR (RP)', 'w' => 56, 'align' => 'right'],
        ];

        // Header Table
        $pdf->rect($m, $curY, $contentW, 19, [15, 118, 110]);
        $colX = $m;
        foreach ($cols as $c) {
            $pdf->text($c['title'], $colX + 3, $curY - 13, 7, true, [255, 255, 255], $c['align'], $c['w'] - 6);
            $colX += $c['w'];
        }
        $curY -= 19;

        if (empty($detail)) {
            $pdf->rect($m, $curY, $contentW, 28, [255, 255, 255], [226, 232, 240]);
            $pdf->text('Data penjualan tidak tersedia pada periode yang dipilih.', $m + 10, $curY - 18, 9, false, [148, 163, 184], 'center', $contentW - 20);
            $curY -= 28;
        } else {
            $no = 1;
            $sumQty = 0;
            $sumPenjualan = 0;
            $sumHpp = 0;
            $sumLaba = 0;

            foreach ($detail as $row) {
                $rowH = 18;
                if ($pdf->checkPageBreak($rowH + 30)) {
                    $curY = $pdf->getY();
                    // Redraw Table Header on new page
                    $pdf->rect($m, $curY, $contentW, 19, [15, 118, 110]);
                    $colX = $m;
                    foreach ($cols as $c) {
                        $pdf->text($c['title'], $colX + 3, $curY - 13, 7, true, [255, 255, 255], $c['align'], $c['w'] - 6);
                        $colX += $c['w'];
                    }
                    $curY -= 19;
                }

                $bg = ($no % 2 === 0) ? [248, 250, 252] : [255, 255, 255];
                $pdf->rect($m, $curY, $contentW, $rowH, $bg, [241, 245, 249], 0.5);

                $sku = 'PRD-' . str_pad($row['id_produk'], 3, '0', STR_PAD_LEFT);
                $nama = $row['nama_produk'];
                if (strlen($nama) > 28) $nama = substr($nama, 0, 26) . '..';

                $kategori = $row['kategori'] ?? '-';
                if (strlen($kategori) > 13) $kategori = substr($kategori, 0, 11) . '..';

                $colX = $m;
                $pdf->text($no, $colX, $curY - 12, 7.5, false, [71, 85, 105], 'center', $cols[0]['w']);
                $colX += $cols[0]['w'];

                $pdf->text($sku, $colX + 3, $curY - 12, 7.5, true, [15, 118, 110], 'left', $cols[1]['w'] - 6);
                $colX += $cols[1]['w'];

                $pdf->text($nama, $colX + 3, $curY - 12, 7.5, true, [15, 23, 42], 'left', $cols[2]['w'] - 6);
                $colX += $cols[2]['w'];

                $pdf->text($kategori, $colX + 3, $curY - 12, 7, false, [100, 116, 139], 'left', $cols[3]['w'] - 6);
                $colX += $cols[3]['w'];

                $pdf->text($row['total_qty'], $colX, $curY - 12, 7.5, false, [15, 23, 42], 'center', $cols[4]['w']);
                $colX += $cols[4]['w'];

                $pdf->text(number_format($row['total_penjualan'], 0, ',', '.'), $colX, $curY - 12, 7.5, false, [15, 23, 42], 'right', $cols[5]['w'] - 4);
                $colX += $cols[5]['w'];

                $pdf->text(number_format($row['total_hpp'], 0, ',', '.'), $colX, $curY - 12, 7.5, false, [100, 116, 139], 'right', $cols[6]['w'] - 4);
                $colX += $cols[6]['w'];

                $pdf->text(number_format($row['laba_kotor'], 0, ',', '.'), $colX, $curY - 12, 7.5, true, [15, 118, 110], 'right', $cols[7]['w'] - 4);

                $sumQty += $row['total_qty'];
                $sumPenjualan += $row['total_penjualan'];
                $sumHpp += $row['total_hpp'];
                $sumLaba += $row['laba_kotor'];

                $curY -= $rowH;
                $no++;
            }

            // TOTAL ROW
            $totalRowH = 20;
            $pdf->rect($m, $curY, $contentW, $totalRowH, [241, 245, 249], [203, 213, 225], 1);
            $totalColW = $cols[0]['w'] + $cols[1]['w'] + $cols[2]['w'] + $cols[3]['w'];
            $pdf->text('TOTAL KESELURUHAN', $m + 6, $curY - 14, 8, true, [15, 23, 42], 'left');

            $colX = $m + $totalColW;
            $pdf->text($sumQty, $colX, $curY - 14, 8, true, [15, 23, 42], 'center', $cols[4]['w']);
            $colX += $cols[4]['w'];

            $pdf->text(number_format($sumPenjualan, 0, ',', '.'), $colX, $curY - 14, 8, true, [15, 23, 42], 'right', $cols[5]['w'] - 4);
            $colX += $cols[5]['w'];

            $pdf->text(number_format($sumHpp, 0, ',', '.'), $colX, $curY - 14, 8, true, [100, 116, 139], 'right', $cols[6]['w'] - 4);
            $colX += $cols[6]['w'];

            $pdf->text(number_format($sumLaba, 0, ',', '.'), $colX, $curY - 14, 8, true, [15, 118, 110], 'right', $cols[7]['w'] - 4);
            $curY -= $totalRowH;
        }

        $filename = 'Laporan_Penjualan_' . $tgl_mulai . '_sd_' . $tgl_akhir . '.pdf';
        self::sendDownload($pdf->render(), $filename);
    }

    /**
     * Generate PDF Laporan Mutasi Stok
     */
    public static function generateLaporanMutasi($tgl_mulai, $tgl_akhir, $hasilMutasi, $ringkasanStok) {
        $pdf = new self();
        $m = 36;
        $contentW = 595.28 - ($m * 2);

        // Header Banner
        $pdf->rect($m, 805, $contentW, 52, [15, 118, 110]);
        $pdf->text('OMNISTOK POS & INVENTORY MANAGEMENT SYSTEM', $m + 14, 783, 14, true, [255, 255, 255]);
        $pdf->text('LAPORAN MUTASI STOK PERSEDIAAN [UI-01]', $m + 14, 768, 10, false, [204, 251, 241]);

        // Metadata box
        $curY = 742;
        $pdf->rect($m, $curY, $contentW, 36, [248, 250, 252], [226, 232, 240]);
        $pdf->text('Periode Laporan: ' . date('d/m/Y', strtotime($tgl_mulai)) . ' s/d ' . date('d/m/Y', strtotime($tgl_akhir)), $m + 10, $curY - 14, 9, true, [15, 23, 42]);
        $pdf->text('Penanggung Jawab: Fauzi Al Wahid (Pemilik Toko - FW)', $m + 10, $curY - 26, 8, false, [100, 116, 139]);
        $pdf->text('Dicetak pada: ' . date('d/m/Y H:i') . ' WIB', $m + $contentW - 150, $curY - 14, 8, false, [100, 116, 139]);
        $pdf->text('Total Mutasi: ' . (int)($hasilMutasi['total_data'] ?? 0) . ' Catatan', $m + $contentW - 150, $curY - 26, 8, true, [15, 118, 110]);

        $curY -= 46;

        // Hitung total masuk, keluar, penyesuaian
        $totalMasuk = 0;
        $totalKeluar = 0;
        $totalKoreksi = 0;
        $mutasiData = $hasilMutasi['data'] ?? [];

        foreach ($mutasiData as $mRow) {
            if ($mRow['jenis_mutasi'] === 'Masuk') {
                $totalMasuk += abs($mRow['jumlah']);
            } elseif ($mRow['jenis_mutasi'] === 'Keluar') {
                $totalKeluar += abs($mRow['jumlah']);
            } else {
                $totalKoreksi += abs($mRow['jumlah']);
            }
        }

        // 3 Metric Cards
        $cardW = ($contentW - 12) / 3;
        $cardH = 46;

        // Card 1: Barang Masuk
        $pdf->rect($m + ($cardW + 6) * 0, $curY, $cardW, $cardH, [255, 255, 255], [226, 232, 240]);
        $pdf->text('BARANG MASUK (PENERIMAAN)', $m + ($cardW + 6) * 0 + 8, $curY - 13, 7, true, [22, 101, 52]);
        $pdf->text('+' . $totalMasuk . ' Unit', $m + ($cardW + 6) * 0 + 8, $curY - 28, 11, true, [22, 101, 52]);
        $pdf->text('Periode Terpilih', $m + ($cardW + 6) * 0 + 8, $curY - 38, 6.5, false, [148, 163, 184]);

        // Card 2: Barang Keluar
        $pdf->rect($m + ($cardW + 6) * 1, $curY, $cardW, $cardH, [255, 255, 255], [226, 232, 240]);
        $pdf->text('BARANG KELUAR (PENJUALAN)', $m + ($cardW + 6) * 1 + 8, $curY - 13, 7, true, [153, 27, 27]);
        $pdf->text('-' . $totalKeluar . ' Unit', $m + ($cardW + 6) * 1 + 8, $curY - 28, 11, true, [153, 27, 27]);
        $pdf->text('Periode Terpilih', $m + ($cardW + 6) * 1 + 8, $curY - 38, 6.5, false, [148, 163, 184]);

        // Card 3: Penyesuaian
        $pdf->rect($m + ($cardW + 6) * 2, $curY, $cardW, $cardH, [255, 255, 255], [226, 232, 240]);
        $pdf->text('PENYESUAIAN / OPNAME', $m + ($cardW + 6) * 2 + 8, $curY - 13, 7, true, [194, 65, 12]);
        $pdf->text($totalKoreksi . ' Unit', $m + ($cardW + 6) * 2 + 8, $curY - 28, 11, true, [194, 65, 12]);
        $pdf->text('Periode Terpilih', $m + ($cardW + 6) * 2 + 8, $curY - 38, 6.5, false, [148, 163, 184]);

        $curY -= ($cardH + 16);

        // Section Title
        $pdf->text('Rincian Log Riwayat Mutasi Stok', $m, $curY, 11, true, [15, 23, 42]);
        $pdf->text('Total ' . count($mutasiData) . ' Catatan', $m + $contentW - 90, $curY, 8, false, [100, 116, 139]);
        $curY -= 14;

        // Table Columns
        $cols = [
            ['title' => 'NO', 'w' => 26, 'align' => 'center'],
            ['title' => 'ID LOG', 'w' => 45, 'align' => 'center'],
            ['title' => 'WAKTU KEJADIAN', 'w' => 90, 'align' => 'left'],
            ['title' => 'NAMA PRODUK', 'w' => 135, 'align' => 'left'],
            ['title' => 'JENIS', 'w' => 52, 'align' => 'center'],
            ['title' => 'KUANTITAS', 'w' => 50, 'align' => 'center'],
            ['title' => 'ALASAN / RUJUKAN', 'w' => 125, 'align' => 'left'],
        ];

        // Header Table
        $pdf->rect($m, $curY, $contentW, 19, [15, 118, 110]);
        $colX = $m;
        foreach ($cols as $c) {
            $pdf->text($c['title'], $colX + 3, $curY - 13, 7, true, [255, 255, 255], $c['align'], $c['w'] - 6);
            $colX += $c['w'];
        }
        $curY -= 19;

        if (empty($mutasiData)) {
            $pdf->rect($m, $curY, $contentW, 28, [255, 255, 255], [226, 232, 240]);
            $pdf->text('Data mutasi stok tidak tersedia pada periode yang dipilih.', $m + 10, $curY - 18, 9, false, [148, 163, 184], 'center', $contentW - 20);
            $curY -= 28;
        } else {
            $no = 1;
            foreach ($mutasiData as $mut) {
                $rowH = 18;
                if ($pdf->checkPageBreak($rowH + 30)) {
                    $curY = $pdf->getY();
                    $pdf->rect($m, $curY, $contentW, 19, [15, 118, 110]);
                    $colX = $m;
                    foreach ($cols as $c) {
                        $pdf->text($c['title'], $colX + 3, $curY - 13, 7, true, [255, 255, 255], $c['align'], $c['w'] - 6);
                        $colX += $c['w'];
                    }
                    $curY -= 19;
                }

                $bg = ($no % 2 === 0) ? [248, 250, 252] : [255, 255, 255];
                $pdf->rect($m, $curY, $contentW, $rowH, $bg, [241, 245, 249], 0.5);

                $nama = $mut['nama_produk'];
                if (strlen($nama) > 26) $nama = substr($nama, 0, 24) . '..';

                $alasan = $mut['alasan'] ?? '-';
                if (strlen($alasan) > 28) $alasan = substr($alasan, 0, 26) . '..';

                $waktu = date('d/m/Y H:i', strtotime($mut['tanggal_mutasi']));

                $colX = $m;
                $pdf->text($no, $colX, $curY - 12, 7.5, false, [71, 85, 105], 'center', $cols[0]['w']);
                $colX += $cols[0]['w'];

                $pdf->text('#' . $mut['id_mutasi'], $colX, $curY - 12, 7.5, false, [100, 116, 139], 'center', $cols[1]['w']);
                $colX += $cols[1]['w'];

                $pdf->text($waktu, $colX + 3, $curY - 12, 7, false, [15, 23, 42], 'left', $cols[2]['w'] - 6);
                $colX += $cols[2]['w'];

                $pdf->text($nama, $colX + 3, $curY - 12, 7.5, true, [15, 23, 42], 'left', $cols[3]['w'] - 6);
                $colX += $cols[3]['w'];

                $jenisColor = ($mut['jenis_mutasi'] === 'Masuk') ? [22, 101, 52] : (($mut['jenis_mutasi'] === 'Keluar') ? [153, 27, 27] : [194, 65, 12]);
                $pdf->text($mut['jenis_mutasi'], $colX, $curY - 12, 7.5, true, $jenisColor, 'center', $cols[4]['w']);
                $colX += $cols[4]['w'];

                $qtyStr = ($mut['jumlah'] > 0 ? '+' : '') . $mut['jumlah'] . ' unit';
                $pdf->text($qtyStr, $colX, $curY - 12, 7.5, true, [15, 23, 42], 'center', $cols[5]['w']);
                $colX += $cols[5]['w'];

                $pdf->text($alasan, $colX + 3, $curY - 12, 7, false, [100, 116, 139], 'left', $cols[6]['w'] - 6);

                $curY -= $rowH;
                $no++;
            }
        }

        $filename = 'Laporan_Mutasi_Stok_' . $tgl_mulai . '_sd_' . $tgl_akhir . '.pdf';
        self::sendDownload($pdf->render(), $filename);
    }
}
