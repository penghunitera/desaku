<?php

namespace Tests\Feature;

use App\Models\Resident;
use App\Models\TbCase;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TbVillageProgramTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_roles_can_record_screening_contact_and_treatment(): void
    {
        $village = Village::create(['code' => 'SIMARE', 'name' => 'Desa Simare', 'district' => 'Borbor', 'regency' => 'Toba', 'province' => 'Sumatera Utara']);
        $user = User::factory()->create(['village_id' => $village->id, 'role' => 'kader_tb', 'active' => true]);
        $patient = Resident::create(['village_id' => $village->id, 'full_name' => 'Pasien Uji', 'hamlet' => 'Dusun I']);
        $contact = Resident::create(['village_id' => $village->id, 'full_name' => 'Kontak Uji', 'hamlet' => 'Dusun I']);

        $this->actingAs($user)->post(route('admin.health.tb.store'), [
            'resident_id' => $patient->id,
            'screened_at' => today()->format('Y-m-d'),
            'symptoms' => ['cough', 'weight_loss'],
            'notes' => 'Catatan klinis rahasia',
        ])->assertSessionHas('success');

        $case = TbCase::firstOrFail();
        $this->assertNotSame('Catatan klinis rahasia', $case->getRawOriginal('clinical_data_encrypted'));
        $this->actingAs($user)->post(route('admin.health.tb.contacts.store', $case), [
            'resident_id' => $contact->id,
            'screening_status' => 'pending',
            'tpt_status' => 'not_assessed',
            'priority' => '1',
        ])->assertSessionHas('success');
        $this->actingAs($user)->post(route('admin.health.tb.treatments.store', $case), [
            'recorded_at' => today()->format('Y-m-d'),
            'adherence' => 'taken',
            'visit_type' => 'home_visit',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('tb_contacts', ['resident_id' => $contact->id, 'priority' => true]);
        $this->assertDatabaseHas('tb_treatment_logs', ['adherence' => 'taken']);
        $this->actingAs($user)->get(route('admin.health.tb.index'))->assertOk()->assertSee('Kampung TB Simare')->assertSee($case->case_code);
    }

    public function test_unrelated_operator_cannot_open_health_register(): void
    {
        $user = User::factory()->create(['role' => 'operator_bumdes', 'active' => true]);
        $this->actingAs($user)->get(route('admin.health.tb.index'))->assertForbidden();
    }

    public function test_head_of_village_cannot_open_identifiable_health_register(): void
    {
        $user = User::factory()->create(['role' => 'kepala_desa', 'active' => true]);
        $this->actingAs($user)->get(route('admin.health.tb.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.health.tb.summary'))
            ->assertOk()
            ->assertSee('Ringkasan Kampung TB')
            ->assertSee('Identitas disembunyikan');
    }
}
