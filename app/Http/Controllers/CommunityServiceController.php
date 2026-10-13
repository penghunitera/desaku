<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class CommunityServiceController extends Controller
{
    private const CATEGORIES = [
        'kesehatan' => ['key' => 'health', 'title' => 'Kesehatan Masyarakat', 'eyebrow' => 'Desa sehat berbasis data', 'description' => 'Daftar layanan kesehatan masyarakat melalui desa dan Puskesmas Borbor.', 'note' => 'Informasi yang dikirim merupakan kebutuhan awal, bukan rekam medis atau diagnosis.'],
        'bantuan-sosial' => ['key' => 'social', 'title' => 'Bantuan Sosial', 'eyebrow' => 'Pendataan yang dapat ditelusuri', 'description' => 'Ajukan pendataan, pemutakhiran, atau sanggahan bantuan sosial melalui operator desa.', 'note' => 'Pengajuan tidak otomatis menetapkan penerima. Kelayakan mengikuti verifikasi dan ketentuan program.'],
        'bphtb' => ['key' => 'tax', 'title' => 'BPHTB', 'eyebrow' => 'Pendapatan daerah transparan', 'description' => 'Mulai validasi dan konsultasi Bea Perolehan Hak atas Tanah dan Bangunan dari desa.', 'note' => 'Nilai resmi dan status pembayaran ditetapkan oleh instansi pendapatan daerah.'],
        'bumdes' => ['key' => 'bumdes', 'title' => 'BUMDes Simare', 'eyebrow' => 'Belanja dan tumbuh bersama', 'description' => 'Pesan produk desa, ternak, dan kebutuhan usaha dari BUMDes Simare.', 'note' => 'Stok, harga akhir, pembayaran, dan serah terima dikonfirmasi pengelola setelah pengajuan.'],
        'program-pemerintah' => ['key' => 'government', 'title' => 'Program Pemerintah', 'eyebrow' => 'Satu pintu informasi program', 'description' => 'Daftar minat dan lengkapi persyaratan program desa hingga pemerintah pusat.', 'note' => 'Operator akan memeriksa periode pendaftaran dan kesesuaian persyaratan program.'],
    ];

    public function show(string $category): View
    {
        $page = self::CATEGORIES[$category] ?? abort(404);
        $services = collect(ServiceRequestController::SERVICES)
            ->filter(fn (array $service) => $service['category'] === $page['key']);

        return view('community-services.show', compact('page', 'services'));
    }
}
