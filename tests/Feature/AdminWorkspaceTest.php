<?php

namespace Tests\Feature;

use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_workspace_requires_login(): void
    {
        $this->get('/pengelola')->assertRedirect('/pengelola/masuk');
        $this->get('/pengelola/masuk')->assertOk()->assertSee('Selamat datang kembali');
    }

    public function test_active_operator_can_login_and_open_dashboard(): void
    {
        User::factory()->create(['email' => 'admin@simare.desa', 'password' => 'secret123', 'role' => 'admin_desa', 'active' => true]);
        $this->post('/pengelola/masuk', ['email' => 'admin@simare.desa', 'password' => 'secret123'])
            ->assertRedirect(route('admin.dashboard'));
        $this->get('/pengelola')->assertOk()->assertSee('Ringkasan desa');
    }

    public function test_operator_can_store_encrypted_resident_and_village_potential(): void
    {
        $user = User::factory()->create(['role' => 'operator', 'active' => true]);
        $this->actingAs($user)->post('/pengelola/penduduk', [
            'nik' => '1201010101010001', 'full_name' => 'Martha Simare', 'phone' => '081234567890', 'hamlet' => 'Dusun I',
        ])->assertSessionHas('success');

        $resident = Resident::firstOrFail();
        $this->assertNotSame('1201010101010001', $resident->nik_encrypted);

        $this->actingAs($user)->post('/pengelola/potensi', [
            'year' => 2026, 'category' => 'Pertanian', 'name' => 'Lahan produktif', 'value' => 125, 'unit' => 'hektar',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('village_potentials', ['name' => 'Lahan produktif', 'year' => 2026]);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_population_operator_can_use_resident_registry_and_search(): void
    {
        $user = User::factory()->create(['role' => 'operator_kependudukan', 'active' => true]);

        $response = $this->actingAs($user)->post(route('admin.residents.store'), [
            'nik' => '1201010101010022', 'full_name' => 'Rina Simare', 'gender' => 'perempuan',
            'birth_place' => 'Simare', 'birth_date' => '1994-03-12', 'resident_status' => 'tetap',
            'hamlet' => 'Dusun II', 'rt' => '002', 'rw' => '001', 'occupation' => 'Petani',
        ]);

        $resident = Resident::where('full_name', 'Rina Simare')->firstOrFail();
        $response->assertRedirect(route('admin.residents.show', $resident));
        $this->assertNotSame('1201010101010022', $resident->nik_encrypted);
        $this->actingAs($user)->get(route('admin.residents.index', ['q' => 'Rina']))
            ->assertOk()->assertSee('Rina Simare')->assertSee('Dusun II');
        $this->actingAs($user)->get(route('admin.residents.show', $resident))
            ->assertOk()->assertSee('Profil warga')->assertSee('•••• •••• •••• ••••');
        $this->assertDatabaseHas('audit_logs', ['action' => 'resident.created', 'subject_id' => $resident->id]);
    }

    public function test_unrelated_role_cannot_manage_residents(): void
    {
        $user = User::factory()->create(['role' => 'operator_bumdes', 'active' => true]);

        $this->actingAs($user)->get(route('admin.residents.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.residents.store'), [
            'nik' => '1201010101010099', 'full_name' => 'Tidak Boleh', 'resident_status' => 'tetap',
        ])->assertForbidden();
    }
}
