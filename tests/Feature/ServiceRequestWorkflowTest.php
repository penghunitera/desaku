<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_can_submit_ktp_request_and_view_status(): void
    {
        Storage::fake('local');
        $response = $this->post('/layanan/ajukan', [
            'service_type' => 'ktp-el', 'full_name' => 'Martha Hutapea', 'nik' => '1201010101010001',
            'phone' => '081234567890', 'birth_date' => '1990-01-01', 'hamlet' => 'Dusun I',
            'address' => 'Desa Simare, Kecamatan Borbor', 'purpose' => 'Penggantian KTP rusak',
            'documents' => [UploadedFile::fake()->create('kartu-keluarga.pdf', 120, 'application/pdf')], 'consent' => '1',
        ]);

        $submission = ServiceRequest::with('documents')->firstOrFail();
        $response->assertRedirect(route('services.show', $submission));
        $this->assertDatabaseHas('service_requests', ['service_type' => 'ktp-el', 'status' => 'submitted']);
        $this->assertDatabaseMissing('residents', ['nik_encrypted' => '1201010101010001']);
        Storage::disk('local')->assertExists($submission->documents->first()->storage_path);
        $this->get(route('services.show', $submission))->assertOk()->assertSee($submission->tracking_code);
    }

    public function test_tracking_requires_matching_phone_number(): void
    {
        Storage::fake('local');
        $this->post('/layanan/ajukan', [
            'service_type' => 'kk-terbaru', 'full_name' => 'Joni Simare', 'nik' => '1201010101010002',
            'phone' => '081234567891', 'hamlet' => 'Dusun II', 'address' => 'Desa Simare',
            'purpose' => 'Perubahan anggota keluarga',
            'documents' => [UploadedFile::fake()->create('kk-lama.pdf', 100, 'application/pdf')], 'consent' => '1',
        ]);
        $submission = ServiceRequest::firstOrFail();

        $this->post('/layanan/lacak', ['tracking' => $submission->tracking_code, 'tracking_phone' => '081299999999'])
            ->assertSessionHasErrors('tracking');
        $this->post('/layanan/lacak', ['tracking' => strtolower($submission->tracking_code), 'tracking_phone' => '081234567891'])
            ->assertRedirect(route('services.show', $submission));
    }

    public function test_submission_rejects_invalid_nik_and_missing_consent(): void
    {
        $this->post('/layanan/ajukan', ['service_type' => 'ktp-el', 'nik' => '123'])
            ->assertSessionHasErrors(['full_name', 'nik', 'phone', 'hamlet', 'address', 'purpose', 'documents', 'consent']);
    }
}
