<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Village;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DptDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_simare_is_default_and_directory_contains_only_requested_identity_fields(): void
    {
        $this->source();
        $response = $this->actingAs($this->user())->get(route('admin.dashboard'));
        $response->assertOk()->assertSee('Potensi SDM dari DPT')->assertSee('Simare · Borbor');
        $response->assertSee('Andi Simare')->assertDontSee('089999999999')->assertDontSee('Only Balige Shared');
        $response->assertViewHas('dpt', function ($dpt) {
            $summary = $dpt['summary'];

            return $dpt['selectedDistrict'] === 'BORBOR'
                && $dpt['selectedVillage'] === 'TOBA|BORBOR|SIMARE'
                && $summary['total'] === 5 && $summary['male'] === 2 && $summary['female'] === 2
                && $summary['unknown_gender'] === 1 && $summary['working_age'] === 2
                && $summary['senior'] === 1 && $summary['unknown_age'] === 1
                && $summary['young_adults'] === 1 && $summary['tps'] === 2
                && $dpt['ages']->sum('total') === 5
                && $dpt['ages']->sum('male') + $dpt['ages']->sum('female') + $dpt['ages']->sum('unknown') === 5;
        });
    }

    public function test_filters_include_all_districts_and_disambiguate_same_village_name(): void
    {
        $this->source();
        $this->actingAs($this->user());
        $this->get(route('admin.dashboard', ['dpt_kecamatan' => '', 'dpt_desa' => '']))
            ->assertOk()->assertViewHas('dpt', fn ($dpt) => $dpt['summary']['total'] === 8
                && $dpt['summary']['villages'] === 3 && $dpt['summary']['tps'] === 5);
        $this->get(route('admin.dashboard', ['dpt_kecamatan' => 'BORBOR', 'dpt_desa' => '']))
            ->assertOk()->assertViewHas('dpt', fn ($dpt) => $dpt['summary']['total'] === 6 && $dpt['summary']['villages'] === 2);
        $this->get(route('admin.dashboard', ['dpt_kecamatan' => 'BALIGE', 'dpt_desa' => 'TOBA|BALIGE|SHARED']))
            ->assertOk()->assertViewHas('dpt', fn ($dpt) => $dpt['summary']['total'] === 2
                && $dpt['label'] === 'Shared · Balige' && $dpt['summary']['male'] === 2);
        $this->get(route('admin.dashboard', ['dpt_kecamatan' => '', 'dpt_desa' => 'TOBA|BORBOR|SHARED']))
            ->assertOk()->assertViewHas('dpt', fn ($dpt) => $dpt['summary']['total'] === 1 && $dpt['summary']['female'] === 1);
    }

    public function test_unknown_and_mismatched_filter_values_are_rejected(): void
    {
        $this->source();
        $this->actingAs($this->user());
        $this->get(route('admin.dashboard', ['dpt_kecamatan' => 'UNKNOWN']))->assertStatus(422);
        $this->get(route('admin.dashboard', ['dpt_desa' => 'SIMARE']))->assertStatus(422);
        $this->get(route('admin.dashboard', ['dpt_kecamatan' => 'BALIGE', 'dpt_desa' => 'TOBA|BORBOR|SIMARE']))->assertStatus(422);
    }

    public function test_unimported_database_and_empty_source_keep_dashboard_available(): void
    {
        $this->actingAs($this->user())->get(route('admin.dashboard'))
            ->assertOk()->assertSee('Data DPT belum tersedia');
        $this->source();
        DB::table('dpt')->delete();
        $this->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('dpt', fn ($dpt) => $dpt['summary']['total'] === 0
                && $dpt['summary']['working_age_percent'] === 0.0 && $dpt['ages']->sum('total') === 0);
    }

    public function test_dashboard_requires_login_and_dpt_aggregates_follow_existing_role_permissions(): void
    {
        $this->source();
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->actingAs($this->user('unassigned'))->get(route('admin.dashboard'))
            ->assertOk()->assertDontSee('Potensi SDM dari DPT');
        $this->actingAs($this->user('operator_bumdes'))->get(route('admin.dashboard'))
            ->assertOk()->assertSee('Potensi SDM dari DPT')->assertDontSee('Andi Simare');
        $inactive = $this->user();
        $inactive->update(['active' => false]);
        $this->actingAs($inactive)->get(route('admin.dashboard'))->assertDontSee('Potensi SDM dari DPT');
    }

    private function user(string $role = 'admin_desa'): User
    {
        $village = Village::firstOrCreate(['code' => 'SIMARE'], [
            'name' => 'Desa Simare', 'district' => 'Borbor', 'regency' => 'Toba', 'province' => 'Sumatera Utara',
        ]);

        return User::factory()->create(['role' => $role, 'active' => true, 'village_id' => $village->id]);
    }

    public function test_name_age_and_gender_filters_combine_without_changing_village_statistics(): void
    {
        $this->source();
        $this->actingAs($this->user())->get(route('admin.dashboard', [
            'dpt_nama' => 'simare', 'dpt_usia_min' => 15, 'dpt_usia_max' => 64, 'dpt_gender' => 'P',
        ]))->assertOk()->assertSee('Berta Simare')->assertDontSee('Citra Simare')
            ->assertDontSee('Andi Simare')->assertDontSee('Doni Simare')
            ->assertViewHas('dpt', fn ($dpt) => $dpt['summary']['total'] === 5
                && $dpt['records']->total() === 1 && $dpt['records']->first()->usia_tercatat === 64);
    }

    public function test_directory_follows_full_village_key_and_does_not_list_all_districts(): void
    {
        $this->source();
        $this->actingAs($this->user());
        $this->get(route('admin.dashboard', ['dpt_kecamatan' => '', 'dpt_desa' => 'TOBA|BORBOR|SHARED']))
            ->assertOk()->assertSee('Only Borbor Shared')->assertDontSee('Only Balige Shared')
            ->assertViewHas('dpt', fn ($dpt) => $dpt['records']->total() === 1);
        $this->get(route('admin.dashboard', ['dpt_kecamatan' => 'BALIGE', 'dpt_desa' => 'TOBA|BALIGE|SHARED']))
            ->assertOk()->assertSee('Only Balige Shared')->assertDontSee('Only Borbor Shared');
        $this->get(route('admin.dashboard', ['dpt_kecamatan' => '', 'dpt_desa' => '']))
            ->assertOk()->assertSee('Pilih satu desa / kelurahan')->assertDontSee('Andi Simare')
            ->assertViewHas('dpt', fn ($dpt) => $dpt['records'] === null);
    }

    public function test_pagination_preserves_all_filters_and_stable_order_for_same_names(): void
    {
        $this->source();
        for ($id = 1001; $id <= 1030; $id++) {
            DB::table('dpt')->insert([
                'iddpt' => $id, 'kabupaten' => 'TOBA', 'kecamatan' => 'BORBOR', 'desa_kelurahan' => 'SIMARE',
                'tps' => '001', 'nama' => 'Paging Example', 'usia' => '30', 'jenis_kelamin' => 'L',
            ]);
        }
        $response = $this->actingAs($this->user())->get(route('admin.dashboard', [
            'dpt_nama' => 'Paging', 'dpt_usia_min' => 30, 'dpt_usia_max' => 30, 'dpt_gender' => 'L', 'dpt_page' => 2,
        ]))->assertOk();
        $dpt = $response->viewData('dpt');
        $this->assertSame(35, $dpt['summary']['total']);
        $this->assertSame(30, $dpt['records']->total());
        $this->assertCount(5, $dpt['records']->items());
        $this->assertSame([1026, 1027, 1028, 1029, 1030], collect($dpt['records']->items())->pluck('iddpt')->all());
        parse_str(parse_url($dpt['records']->previousPageUrl(), PHP_URL_QUERY), $query);
        foreach (['dpt_kecamatan' => 'BORBOR', 'dpt_desa' => 'TOBA|BORBOR|SIMARE', 'dpt_nama' => 'Paging', 'dpt_usia_min' => '30', 'dpt_usia_max' => '30', 'dpt_gender' => 'L', 'dpt_page' => '1'] as $key => $value) {
            $this->assertSame($value, $query[$key]);
        }
        $this->assertSame('dpt-list', parse_url($dpt['records']->previousPageUrl(), PHP_URL_FRAGMENT));
        $this->assertFalse(property_exists($dpt['records']->first(), 'nowa'));
    }

    public function test_name_search_treats_wildcards_literally_and_binds_quotes(): void
    {
        $this->source();
        DB::table('dpt')->where('iddpt', 1)->update(['nama' => "Andi %_ O'Connor"]);
        $this->actingAs($this->user())->get(route('admin.dashboard', ['dpt_nama' => '%_']))
            ->assertOk()->assertViewHas('dpt', fn ($dpt) => $dpt['records']->total() === 1);
        $this->get(route('admin.dashboard', ['dpt_nama' => "O'Connor"]))
            ->assertOk()->assertViewHas('dpt', fn ($dpt) => $dpt['records']->total() === 1);
        $this->get(route('admin.dashboard', ['dpt_nama' => "' OR 1=1 --"]))
            ->assertOk()->assertViewHas('dpt', fn ($dpt) => $dpt['records']->total() === 0);
    }

    public function test_filter_validation_and_empty_results_are_handled(): void
    {
        $this->source();
        $this->actingAs($this->user());
        $this->get(route('admin.dashboard', ['dpt_usia_min' => 65, 'dpt_usia_max' => 20]))
            ->assertSessionHasErrors('dpt_usia_max');
        $this->get(route('admin.dashboard', ['dpt_usia_min' => -1]))->assertSessionHasErrors('dpt_usia_min');
        $this->get(route('admin.dashboard', ['dpt_gender' => 'invalid']))->assertSessionHasErrors('dpt_gender');
        $this->get(route('admin.dashboard', ['dpt_nama' => 'NotInFixture']))
            ->assertOk()->assertSee('Tidak ada DPT pada halaman ini.')
            ->assertViewHas('dpt', fn ($dpt) => $dpt['records']->total() === 0 && $dpt['summary']['total'] === 5);
    }

    public function test_invalid_age_is_marked_and_header_rows_are_excluded_from_directory(): void
    {
        $this->source();
        $response = $this->actingAs($this->user())->get(route('admin.dashboard'))->assertOk();
        $records = collect($response->viewData('dpt')['records']->items());
        $this->assertCount(5, $records);
        $this->assertNull($records->firstWhere('iddpt', 4)->usia_tercatat);
        $this->assertFalse($records->contains('iddpt', 9));
        $response->assertDontSee('not an age');
        $this->get(route('admin.dashboard', ['dpt_gender' => 'unknown']))->assertOk()
            ->assertViewHas('dpt', fn ($dpt) => $dpt['records']->total() === 1 && $dpt['records']->first()->iddpt === 5);
    }

    private function source(): void
    {
        Schema::create('dpt', function (Blueprint $table) {
            $table->integer('iddpt')->primary();
            foreach (['kabupaten', 'kecamatan', 'desa_kelurahan', 'tps', 'nama', 'jenis_kelamin', 'usia', 'nowa'] as $column) {
                $table->text($column)->nullable();
            }
        });
        DB::unprepared(file_get_contents(database_path('queries/dpt_potensi.sql')));
        $rows = [
            [1, 'BORBOR', 'SIMARE', '001', 'L', '15'],
            [2, 'BORBOR', 'SIMARE', '001', 'P', '64'],
            [3, 'BORBOR', 'SIMARE', '002', 'P', '65'],
            [4, 'BORBOR', 'SIMARE', '002', 'L', 'not an age'],
            [5, 'BORBOR', 'SIMARE', '002', '?', '14'],
            [6, 'BORBOR', 'SHARED', '001', 'P', '25'],
            [7, 'BALIGE', 'SHARED', '001', 'L', '35'],
            [8, 'BALIGE', 'SHARED', '002', 'L', '55'],
            [9, 'BORBOR', 'SIMARE', '001', 'JENIS KELAMIN', 'USIA'],
        ];
        foreach ($rows as [$id, $district, $village, $tps, $gender, $age]) {
            $names = [1 => 'Andi Simare', 2 => 'Berta Simare', 3 => 'Citra Simare', 4 => 'Doni Simare', 5 => 'Eka Simare', 6 => 'Only Borbor Shared', 7 => 'Only Balige Shared', 8 => 'Andi Balige Shared', 9 => 'NAMA'];
            DB::table('dpt')->insert([
                'iddpt' => $id, 'kabupaten' => 'TOBA', 'kecamatan' => $district, 'desa_kelurahan' => $village,
                'tps' => $tps, 'nama' => $names[$id],
                'jenis_kelamin' => $gender, 'usia' => $age, 'nowa' => '089999999999',
            ]);
        }
    }
}
