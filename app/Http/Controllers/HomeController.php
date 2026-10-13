<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
            'services' => [
                ['icon' => 'ID', 'title' => 'Administrasi Kependudukan', 'description' => 'Prototype permohonan KTP-el, KK terbaru, serta surat pindah dengan pelacakan berkas.', 'meta' => '3 layanan'],
                ['icon' => 'SE', 'title' => 'Kesehatan Masyarakat', 'description' => 'Pemantauan program ibu hamil, bebas TB, hipertensi, stunting, dan penyakit prioritas.', 'meta' => '5 program'],
                ['icon' => 'BS', 'title' => 'Bantuan Sosial', 'description' => 'Pendaftaran, verifikasi kelayakan, riwayat bantuan, dan masa sanggah warga.', 'meta' => 'Pendaftaran terpadu'],
                ['icon' => 'PB', 'title' => 'PBB & BPHTB', 'description' => 'Pendataan objek pajak, status pembayaran, serta proyeksi potensi PAD dari Desa Simare.', 'meta' => 'Rekap potensi PAD'],
                ['icon' => 'BM', 'title' => 'BUMDes Simare', 'description' => 'Promosi dan transaksi produk desa, termasuk katalog serta jual-beli ternak babi.', 'meta' => 'Marketplace desa'],
                ['icon' => 'PP', 'title' => 'Program Pemerintah', 'description' => 'Satu pintu informasi dan pendaftaran program kabupaten, provinsi, maupun pusat.', 'meta' => 'Selalu diperbarui'],
            ],
            'healthPrograms' => [
                ['name' => 'Pendampingan ibu hamil', 'count' => 18, 'unit' => 'peserta', 'tone' => 'rose'],
                ['name' => 'Skrining hipertensi', 'count' => 126, 'unit' => 'warga', 'tone' => 'amber'],
                ['name' => 'Desa bebas TB', 'count' => 84, 'unit' => 'skrining', 'tone' => 'sky'],
                ['name' => 'Cegah stunting', 'count' => 42, 'unit' => 'balita', 'tone' => 'green'],
            ],
            'products' => [
                ['name' => 'Bibit babi lokal unggul', 'price' => 'Rp1.850.000', 'tag' => 'Siap ternak', 'emoji' => '🐖'],
                ['name' => 'Babi grower sehat', 'price' => 'Rp3.750.000', 'tag' => 'Bobot ±45 kg', 'emoji' => '🐷'],
                ['name' => 'Paket pakan fermentasi', 'price' => 'Rp185.000', 'tag' => 'Karung 30 kg', 'emoji' => '🌾'],
            ],
        ]);
    }
}
