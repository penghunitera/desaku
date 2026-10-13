<?php

namespace Tests\Feature;

use App\Models\BumdesProduct;
use App\Models\PublicDocument;
use App\Models\Resident;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\Village;
use App\Models\VillageBudget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OpenSidAdaptedModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_information_modules_only_show_published_data(): void
    {
        $village = Village::create(['code'=>'SIMARE','name'=>'Desa Simare','district'=>'Borbor','regency'=>'Toba','province'=>'Sumatera Utara']);
        Resident::create(['village_id'=>$village->id,'full_name'=>'Warga Statistik','gender'=>'perempuan','occupation'=>'Petani','education'=>'Tamat SMA','hamlet'=>'Dusun I','resident_status'=>'tetap']);
        VillageBudget::create(['village_id'=>$village->id,'year'=>2026,'type'=>'belanja','sector'=>'Pembangunan','name'=>'Jalan Desa','budget_amount'=>100000000,'realized_amount'=>40000000,'published'=>true]);
        PublicDocument::create(['village_id'=>$village->id,'kind'=>'ppid','category'=>'berkala','title'=>'Laporan Desa','year'=>2026,'published'=>true]);
        PublicDocument::create(['village_id'=>$village->id,'kind'=>'regulation','category'=>'perdes','title'=>'Peraturan Desa Nomor 1','year'=>2026,'published'=>true]);
        BumdesProduct::create(['village_id'=>$village->id,'sku'=>'TEST-1','name'=>'Produk Lokal','category'=>'food','price'=>25000,'stock'=>5,'published'=>true]);

        $this->get(route('public.statistics'))->assertOk()->assertDontSee('Warga Statistik')->assertSee('Petani');
        $this->get(route('public.budget'))->assertOk()->assertSee('Jalan Desa')->assertSee('40.000.000');
        $this->get(route('public.ppid'))->assertOk()->assertSee('Laporan Desa');
        $this->get(route('public.regulations'))->assertOk()->assertSee('Peraturan Desa Nomor 1');
        $this->get(route('public.marketplace'))->assertOk()->assertSee('Produk Lokal');
    }

    public function test_citizen_can_login_with_nik_and_pin_and_only_see_own_request(): void
    {
        $village = Village::create(['code'=>'SIMARE','name'=>'Desa Simare','district'=>'Borbor','regency'=>'Toba','province'=>'Sumatera Utara']);
        $nik='1201010101010001';
        $resident=Resident::create(['village_id'=>$village->id,'nik_hash'=>hash_hmac('sha256',$nik,config('app.key')),'nik_encrypted'=>Crypt::encryptString($nik),'full_name'=>'Martha Simare','resident_status'=>'tetap','citizen_pin_hash'=>Hash::make('123456')]);
        $submission=ServiceRequest::create(['public_id'=>Str::uuid(),'tracking_code'=>'SMR-TEST-001','village_id'=>$village->id,'resident_id'=>$resident->id,'category'=>'dukcapil','service_type'=>'ktp-el','status'=>'submitted']);
        $submission->histories()->create(['status'=>'submitted','title'=>'Permohonan diajukan']);

        $this->post(route('citizen.authenticate'),['nik'=>$nik,'pin'=>'123456'])->assertRedirect(route('citizen.dashboard'));
        $this->get(route('citizen.dashboard'))->assertOk()->assertSee('Martha Simare')->assertSee('SMR-TEST-001');
        $this->get(route('citizen.request',$submission))->assertOk()->assertSee('Permohonan diajukan');
    }

    public function test_service_officer_can_advance_workflow_with_audit_history(): void
    {
        $village=Village::create(['code'=>'SIMARE','name'=>'Desa Simare','district'=>'Borbor','regency'=>'Toba','province'=>'Sumatera Utara']);
        $officer=User::factory()->create(['village_id'=>$village->id,'role'=>'petugas_pelayanan','active'=>true]);
        $resident=Resident::create(['village_id'=>$village->id,'full_name'=>'Pemohon','resident_status'=>'tetap']);
        $submission=ServiceRequest::create(['public_id'=>Str::uuid(),'tracking_code'=>'SMR-TEST-002','village_id'=>$village->id,'resident_id'=>$resident->id,'category'=>'dukcapil','service_type'=>'ktp-el','status'=>'submitted']);

        $this->actingAs($officer)->patch(route('admin.services.update',$submission),['status'=>'verified','notes'=>'Berkas lengkap.'])->assertSessionHas('success');
        $this->assertDatabaseHas('service_requests',['id'=>$submission->id,'status'=>'verified','assigned_to'=>$officer->id]);
        $this->assertDatabaseHas('service_request_histories',['service_request_id'=>$submission->id,'status'=>'verified']);
        $this->assertDatabaseHas('audit_logs',['action'=>'service_request.status_updated','subject_id'=>$submission->id]);
    }

    public function test_article_requires_admin_review_before_it_appears_publicly(): void
    {
        $village=Village::create(['code'=>'SIMARE','name'=>'Desa Simare','district'=>'Borbor','regency'=>'Toba','province'=>'Sumatera Utara']);
        $admin=User::factory()->create(['village_id'=>$village->id,'role'=>'admin_desa','active'=>true]);
        $this->actingAs($admin)->post(route('admin.content.article'),['title'=>'Musyawarah Desa','category'=>'Pengumuman','excerpt'=>'Agenda musyawarah','content'=>'Musyawarah desa dilaksanakan pada hari Senin.'])->assertSessionHas('success');
        $article=\App\Models\VillageArticle::firstOrFail();
        $this->get(route('public.articles'))->assertDontSee('Musyawarah Desa');
        $this->actingAs($admin)->patch(route('admin.content.publish',$article))->assertSessionHas('success');
        $this->get(route('public.articles'))->assertOk()->assertSee('Musyawarah Desa')->assertSee('Telah ditinjau');
    }
}
