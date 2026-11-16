<?php
/**
 * Konfigurasi Pembayaran QRIS - OmniStok POS
 *
 * Menyimpan identitas merchant dan lokasi penyimpanan payload QRIS statis
 * milik toko. Payload asli dibaca dari hasil pemindaian gambar QRIS pada
 * halaman `pengaturan_qris.php` (khusus Pemilik Toko).
 *
 * Catatan: payload QRIS bukan rahasia (tercetak pada poster merchant), tetapi
 * tetap tidak boleh dikarang. Nominal dinamis diturunkan dari payload statis
 * dengan mengubah tag 01 (11 -> 12), menyisipkan tag 54 (nominal), lalu
 * menghitung ulang CRC16 pada tag 63.
 */

return [
    // Identitas merchant (untuk validasi & tampilan)
    'nmid'           => 'ID1025421996014',
    'merchant_name'  => 'FERDYANSAH',
    'merchant_city'  => 'KP.CABANG LIO',
    'category'       => 'FASHION',

    // Berkas penyimpanan payload hasil pemindaian
    'storage_file'   => dirname(__DIR__) . '/database/qris_merchant.json',

    // Gambar QRIS statis: hasil unggahan pemilik toko (fallback utama)
    'uploaded_image' => 'assets/qris_merchant_qr',
    // Gambar demo bawaan bila pemilik belum mengunggah QRIS sendiri
    'fallback_image' => 'assets/qris_code.png',

    // Batas nominal yang diizinkan untuk QRIS dinamis
    'min_amount'     => 1,
    'max_amount'     => 10000000,
];
