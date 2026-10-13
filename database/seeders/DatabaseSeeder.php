<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Village;
use App\Models\BumdesProduct;
use App\Models\PublicDocument;
use App\Models\Resident;
use App\Models\VillageBudget;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminEmail = env('ADMIN_EMAIL', 'admin@simare.desa');
        $adminExists = User::where('email', $adminEmail)->exists();
        $adminPassword = env('ADMIN_PASSWORD');
        if (! $adminExists && (! is_string($adminPassword) || strlen($adminPassword) < 12)) {
            throw new \LogicException('Set ADMIN_PASSWORD to a unique password of at least 12 characters before seeding a new database.');
        }

        $village = Village::firstOrCreate(['code' => 'SIMARE'], ['name' => 'Desa Simare', 'district' => 'Borbor', 'regency' => 'Toba', 'province' => 'Sumatera Utara']);
        User::firstOrCreate(['email' => $adminEmail], [
            'village_id' => $village->id, 'name' => 'Administrator Desa Simare',
            'password' => $adminPassword, 'role' => 'admin_desa', 'active' => true,
        ]);

        Resident::updateOrCreate(['village_id'=>$village->id,'nik_hash'=>hash_hmac('sha256','1201010101010001',config('app.key'))],[
            'nik_encrypted'=>Crypt::encryptString('1201010101010001'),'full_name'=>'Martha Simare','gender'=>'perempuan','birth_place'=>'Simare','birth_date'=>'1992-04-17','religion'=>'Kristen','marital_status'=>'Kawin','education'=>'Tamat SMA','occupation'=>'Petani','resident_status'=>'tetap','phone'=>'6281234567890','address'=>'Desa Simare','hamlet'=>'Dusun I','rt'=>'001','rw'=>'001','citizen_pin_hash'=>Hash::make('123456'),
        ]);
        foreach ([
            ['type'=>'pendapatan','sector'=>'Pendapatan Asli Desa','name'=>'Hasil usaha desa','budget_amount'=>85000000,'realized_amount'=>52500000],
            ['type'=>'pendapatan','sector'=>'Transfer','name'=>'Dana Desa','budget_amount'=>950000000,'realized_amount'=>570000000],
            ['type'=>'belanja','sector'=>'Penyelenggaraan Pemerintahan','name'=>'Operasional pemerintahan desa','budget_amount'=>280000000,'realized_amount'=>142000000],
            ['type'=>'belanja','sector'=>'Pembangunan Desa','name'=>'Pembangunan jalan usaha tani','budget_amount'=>420000000,'realized_amount'=>236000000],
            ['type'=>'belanja','sector'=>'Pembinaan Masyarakat','name'=>'Pembinaan kelompok masyarakat','budget_amount'=>125000000,'realized_amount'=>61000000],
        ] as $row) VillageBudget::updateOrCreate(['village_id'=>$village->id,'year'=>2026,'type'=>$row['type'],'name'=>$row['name']],$row+['published'=>true]);
        foreach ([
            ['kind'=>'ppid','category'=>'berkala','title'=>'Laporan Penyelenggaraan Pemerintahan Desa 2025','year'=>2025,'summary'=>'Ringkasan penyelenggaraan pemerintahan, pembangunan, pembinaan, dan pemberdayaan masyarakat.'],
            ['kind'=>'ppid','category'=>'setiap saat','title'=>'Standar Operasional Pelayanan Informasi Publik','year'=>2026,'summary'=>'Prosedur permohonan, keberatan, waktu pelayanan, dan biaya informasi publik.'],
            ['kind'=>'regulation','category'=>'peraturan desa','title'=>'Peraturan Desa tentang APBDes Tahun Anggaran 2026','year'=>2026,'document_number'=>'01 Tahun 2026','summary'=>'Dasar hukum anggaran pendapatan dan belanja Desa Simare tahun 2026.'],
            ['kind'=>'regulation','category'=>'keputusan kepala desa','title'=>'Penetapan Pengelola Informasi dan Dokumentasi Desa','year'=>2026,'document_number'=>'04/SK/2026','summary'=>'Penetapan struktur dan tanggung jawab PPID Desa Simare.'],
        ] as $row) PublicDocument::updateOrCreate(['village_id'=>$village->id,'kind'=>$row['kind'],'title'=>$row['title']],$row+['published'=>true,'status'=>'published']);
        BumdesProduct::updateOrCreate(['sku'=>'SMR-DEMO-01'],['village_id'=>$village->id,'name'=>'Bibit babi lokal unggul','category'=>'livestock','price'=>1850000,'stock'=>8,'published'=>true]);
    }
}
