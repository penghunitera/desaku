<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_community_service_catalogs_are_available(): void
    {
        foreach (['kesehatan', 'bantuan-sosial', 'bphtb', 'bumdes', 'program-pemerintah'] as $category) {
            $this->get("/portal/{$category}")->assertOk()->assertSee('Ajukan sekarang');
        }
    }

    public function test_resident_can_submit_each_new_service_category(): void
    {
        $types = ['skrining-kesehatan', 'pesanan-bumdes', 'program-pemerintah'];

        foreach ($types as $index => $type) {
            $response = $this->post('/layanan/ajukan', [
                'service_type' => $type,
                'full_name' => 'Warga Simare '.($index + 1),
                'nik' => '120101010101'.str_pad((string) ($index + 10), 4, '0', STR_PAD_LEFT),
                'phone' => '0812345678'.($index + 10),
                'hamlet' => 'Dusun I',
                'address' => 'Desa Simare',
                'purpose' => 'Pengajuan layanan melalui portal DESAKU',
                'consent' => '1',
            ]);

            $submission = ServiceRequest::where('service_type', $type)->firstOrFail();
            $response->assertRedirect(route('services.show', $submission));
        }

        $this->assertDatabaseCount('service_requests', 3);
    }

    public function test_social_and_bphtb_services_require_supporting_documents(): void
    {
        foreach (['bantuan-sosial', 'validasi-bphtb'] as $type) {
            $this->post('/layanan/ajukan', [
                'service_type' => $type,
                'full_name' => 'Warga Simare',
                'nik' => '1201010101010099',
                'phone' => '081234567899',
                'hamlet' => 'Dusun I',
                'address' => 'Desa Simare',
                'purpose' => 'Pengajuan layanan',
                'consent' => '1',
            ])->assertSessionHasErrors('documents');
        }
    }
}
