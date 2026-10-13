<?php

namespace Tests\Feature;

use App\Jobs\SyncResidentPropertyTax;
use App\Models\PropertyTaxAssessment;
use App\Models\PropertyTaxSnapshot;
use App\Models\Resident;
use App\Models\TaxObject;
use App\Models\User;
use App\Models\Village;
use App\Services\PropertyTaxReportService;
use App\Services\PropertyTaxSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use UnexpectedValueException;

class PropertyTaxResidentSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_real_text_contract_links_total_to_resident_without_inventing_annual_data(): void
    {
        $resident = $this->resident();
        Http::fake(['*' => Http::response('Total Tunggakan: Rp1.250.000,50')]);
        $snapshot = app(PropertyTaxSyncService::class)->sync($resident);

        $this->assertSame('1250000.50', $snapshot->total_outstanding);
        $this->assertSame('success', $snapshot->sync_status);
        $this->assertNotNull($snapshot->checked_at);
        $this->assertDatabaseCount('property_tax_assessments', 0);
        $this->assertDatabaseCount('tax_objects', 0);
        Http::assertSent(fn ($r) => $r->method() === 'GET' && $r['NIK'] === '1201010101010001');
        $this->assertStringNotContainsString('1201010101010001', json_encode($snapshot));
        $this->assertStringNotContainsString('1201010101010001', json_encode(DB::table('audit_logs')->get()));
    }

    public function test_zero_is_a_successful_total_not_an_annual_paid_assessment(): void
    {
        Http::fake(['*' => Http::response('Total Tunggakan: 0')]);
        $snapshot = app(PropertyTaxSyncService::class)->sync($this->resident());
        $this->assertSame('0.00', $snapshot->total_outstanding);
        $this->assertSame('success', $snapshot->sync_status);
        $this->assertDatabaseCount('property_tax_assessments', 0);
    }

    public function test_json_explicit_total_is_supported_but_a_generic_total_is_not_guessed(): void
    {
        $service = app(PropertyTaxSyncService::class);
        $this->assertSame('450000.00', $service->total(['result' => ['data' => ['total_tunggakan' => 450000]]]));
        $this->assertSame('450000.25', $service->total(['result' => ['total_outstanding' => '450000.25']]));
        $this->expectException(UnexpectedValueException::class);
        $service->total(['result' => ['total' => 450000, 'tahun' => 2026]]);
    }

    public function test_failure_keeps_previous_successful_result_and_date(): void
    {
        $resident = $this->resident();
        $previous = now()->subDays(2)->startOfSecond();
        PropertyTaxSnapshot::create(['village_id' => $resident->village_id, 'resident_id' => $resident->id, 'nik_hash' => $resident->nik_hash, 'sync_status' => 'success', 'total_outstanding' => 700000, 'checked_at' => $previous]);
        Http::fake(['*' => Http::response('unavailable', 503)]);
        $snapshot = app(PropertyTaxSyncService::class)->sync($resident);
        $this->assertSame('failed', $snapshot->sync_status);
        $this->assertSame('api_unavailable', $snapshot->error_code);
        $this->assertSame('700000.00', $snapshot->total_outstanding);
        $this->assertTrue($snapshot->checked_at->equalTo($previous));
    }

    public function test_unsupported_or_empty_response_is_not_treated_as_zero(): void
    {
        Http::fake(['*' => Http::response(['data' => []])]);
        $snapshot = app(PropertyTaxSyncService::class)->sync($this->resident());
        $this->assertSame('failed', $snapshot->sync_status);
        $this->assertSame('unsupported_response', $snapshot->error_code);
        $this->assertNull($snapshot->total_outstanding);
        $this->assertNull($snapshot->checked_at);
    }

    public function test_invalid_encrypted_identity_does_not_call_api(): void
    {
        $resident = $this->resident();
        $resident->update(['nik_encrypted' => 'invalid-encryption']);
        $snapshot = app(PropertyTaxSyncService::class)->sync($resident);
        $this->assertSame('invalid_identity', $snapshot->error_code);
        Http::assertNothingSent();
    }

    public function test_operator_pbb_can_queue_resident_check_without_plaintext_identity_in_job(): void
    {
        Queue::fake();
        $resident = $this->resident();
        $user = User::factory()->create(['role' => 'operator_pbb', 'active' => true, 'village_id' => $resident->village_id]);
        $this->actingAs($user)->post(route('admin.tax.sync-resident', $resident))->assertSessionHas('success');
        Queue::assertPushed(SyncResidentPropertyTax::class, fn ($job) => $job->residentId === $resident->id && ! str_contains(serialize($job), '1201010101010001'));
        $this->assertDatabaseHas('property_tax_snapshots', ['resident_id' => $resident->id, 'sync_status' => 'queued']);
        $this->actingAs($user)->post(route('admin.tax.sync-resident', $resident))->assertSessionHas('success');
        Queue::assertPushed(SyncResidentPropertyTax::class, 1);
    }

    public function test_batch_skips_fresh_success_pending_inactive_missing_nik_and_other_village(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();
        $resident = $this->resident();
        $village = $resident->village;
        $fresh = $this->resident('1201010101010002', $village);
        $pending = $this->resident('1201010101010003', $village);
        $inactive = $this->resident('1201010101010004', $village);
        $inactive->update(['resident_status' => 'pindah']);
        $missing = $this->resident('1201010101010005', $village);
        $missing->update(['nik_encrypted' => null]);
        $other = $this->resident('1201010101010006', $this->village('OTHER'));
        PropertyTaxSnapshot::create(['village_id' => $village->id, 'resident_id' => $fresh->id, 'nik_hash' => $fresh->nik_hash, 'sync_status' => 'success', 'total_outstanding' => 123, 'checked_at' => now()]);
        PropertyTaxSnapshot::create(['village_id' => $village->id, 'resident_id' => $pending->id, 'nik_hash' => $pending->nik_hash, 'sync_status' => 'queued', 'queued_at' => now()]);
        $user = User::factory()->create(['role' => 'admin_desa', 'active' => true, 'village_id' => $village->id]);
        $this->actingAs($user)->post(route('admin.tax.sync-village'))->assertSessionHas('success');
        Queue::assertPushed(SyncResidentPropertyTax::class, 1);
        Queue::assertPushed(SyncResidentPropertyTax::class, fn ($job) => $job->residentId === $resident->id && $job->villageId === $village->id);
        $this->assertDatabaseMissing('property_tax_snapshots', ['resident_id' => $other->id]);
    }

    public function test_roles_and_village_boundaries_are_enforced(): void
    {
        Queue::fake();
        $resident = $this->resident();
        $other = $this->resident('1201010101010009', $this->village('OTHER'));
        $user = User::factory()->create(['role' => 'operator_pbb', 'active' => true, 'village_id' => $resident->village_id]);
        $this->actingAs($user)->get(route('admin.tax.index'))->assertOk()->assertSee($resident->full_name)->assertDontSee($other->full_name)->assertDontSee('1201010101010001');
        $this->actingAs($user)->post(route('admin.tax.sync-resident', $other))->assertNotFound();
        $user->update(['role' => 'operator_kependudukan']);
        $this->actingAs($user)->get(route('admin.tax.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.tax.sync-resident', $resident))->assertForbidden();
        $this->actingAs($user)->post(route('admin.tax.store'), [])->assertForbidden();
        $user->update(['role' => 'kepala_desa']);
        $this->actingAs($user)->get(route('admin.tax.index'))->assertOk()->assertDontSee('Sinkronkan warga aktif');
        $this->actingAs($user)->post(route('admin.tax.sync-village'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.tax.export'))->assertOk();
        Queue::assertNothingPushed();
    }

    public function test_annual_remaining_is_per_object_and_overdue_only_counts_verified_expired_sppt(): void
    {
        $this->travelTo(Carbon::parse('2026-10-13 12:00:00'));
        $resident = $this->resident();
        $this->assessment($resident, 'object1', 2026, 100000, 0, 'verified', '2026-09-30');
        $this->assessment($resident, 'object2', 2026, 100000, 200000, 'verified', '2026-09-30');
        $this->assessment($resident, 'object3', 2026, 300000, 50000, 'estimated', '2026-09-30');
        $this->assessment($resident, 'object4', 2026, 400000, 0, 'verified', '2026-12-31');
        $other = $this->resident('1201010101010002', $this->village('OTHER'));
        $this->assessment($other, 'foreignobject', 2026, 9000000, 0, 'verified', '2026-09-30');
        $this->assessment($resident, 'object1-oldyear', 2025, 75000, 0, 'verified', '2025-09-30');
        $reports = app(PropertyTaxReportService::class);
        $summary = $reports->forYear($reports->yearly($resident->village_id), 2026);
        $this->assertSame(4, $summary['object_count']);
        $this->assertSame(900000.0, $summary['potential']);
        $this->assertSame(250000.0, $summary['realized']);
        $this->assertSame(750000.0, $summary['outstanding']);
        $this->assertSame(100000.0, $summary['overdue']);
        $this->assertSame(3, $summary['verified_count']);
        $this->get('/pbb/potensi?tahun=2026')->assertOk()->assertSee('Rp750.000')->assertDontSee('Rp9.000.000');
    }

    public function test_coverage_excludes_old_identity_and_deduplicates_nik(): void
    {
        $resident = $this->resident();
        $duplicate = $this->resident('1201010101010001', $resident->village);
        $changed = $this->resident('1201010101010002', $resident->village);
        foreach ([[$resident, 100000, now()->subDays(2)], [$duplicate, 120000, now()], [$changed, 500000, now()]] as [$person, $amount, $date]) {
            PropertyTaxSnapshot::create(['village_id' => $person->village_id, 'resident_id' => $person->id, 'nik_hash' => $person->nik_hash, 'sync_status' => 'success', 'total_outstanding' => $amount, 'checked_at' => $date]);
        }
        $changed->update(['nik_hash' => hash_hmac('sha256', '1201010101010003', config('app.key'))]);
        $coverage = app(PropertyTaxReportService::class)->apiCoverage($resident->village_id);
        $this->assertSame(1, $coverage['checked']);
        $this->assertSame(120000.0, $coverage['total']);
    }

    public function test_manual_sppt_selects_resident_encrypts_nop_and_normalizes_duplicates(): void
    {
        $resident = $this->resident();
        $user = User::factory()->create(['role' => 'operator_pbb', 'active' => true, 'village_id' => $resident->village_id]);
        $data = ['resident_id' => $resident->id, 'nop' => '12.34.567.890.123-0001.0', 'land_area' => 100, 'building_area' => 30, 'tax_year' => 2026, 'assessed_amount' => 300000, 'paid_amount' => 50000, 'status' => 'verified', 'due_date' => '2026-09-30'];
        $this->actingAs($user)->post(route('admin.tax.store'), $data)->assertSessionHas('success');
        $object = TaxObject::firstOrFail();
        $this->assertSame('123456789012300010', Crypt::decryptString($object->nop_encrypted));
        $this->assertStringNotContainsString('123456789012300010', json_encode($object));
        $this->assertDatabaseHas('property_tax_assessments', ['tax_object_id' => $object->id, 'tax_year' => 2026, 'status' => 'verified']);
        $data['nop'] = '123456789012300010';
        $data['paid_amount'] = 100000;
        $this->actingAs($user)->post(route('admin.tax.store'), $data)->assertSessionHas('success');
        $this->assertDatabaseCount('tax_objects', 1);
        $this->assertDatabaseCount('property_tax_assessments', 1);
        $this->assertDatabaseHas('property_tax_assessments', ['paid_amount' => 100000]);
        $this->actingAs($user)->get(route('admin.data', ['bagian' => 'pbb']))->assertOk()->assertSee('Pilih penduduk terdaftar');
    }

    public function test_manual_sppt_does_not_reassign_nop_or_accept_foreign_resident(): void
    {
        $resident = $this->resident();
        $user = User::factory()->create(['role' => 'admin_desa', 'active' => true, 'village_id' => $resident->village_id]);
        $data = ['resident_id' => $resident->id, 'nop' => '123456789012300010', 'land_area' => 100, 'building_area' => 0, 'tax_year' => 2026, 'assessed_amount' => 300000];
        $this->actingAs($user)->post(route('admin.tax.store'), $data)->assertSessionHas('success');
        $second = $this->resident('1201010101010002', $resident->village);
        $data['resident_id'] = $second->id;
        $this->actingAs($user)->post(route('admin.tax.store'), $data)->assertStatus(422);
        $other = $this->resident('1201010101010003', $this->village('OTHER'));
        $data['resident_id'] = $other->id;
        $this->actingAs($user)->post(route('admin.tax.store'), $data)->assertNotFound();
        $this->assertDatabaseHas('tax_objects', ['resident_id' => $resident->id]);
    }

    public function test_csv_has_annual_aggregates_without_identity_or_api_total(): void
    {
        $resident = $this->resident();
        $this->assessment($resident, 'object1', 2026, 300000, 100000, 'verified', '2026-09-30');
        PropertyTaxSnapshot::create(['village_id' => $resident->village_id, 'resident_id' => $resident->id, 'nik_hash' => $resident->nik_hash, 'sync_status' => 'success', 'total_outstanding' => 900000, 'checked_at' => now()]);
        $user = User::factory()->create(['role' => 'auditor', 'active' => true, 'village_id' => $resident->village_id]);
        $response = $this->actingAs($user)->get(route('admin.tax.export'))->assertOk()->assertDownload();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('2026;1;1;300000;100000;200000;', $csv);
        $this->assertStringNotContainsString('1201010101010001', $csv);
        $this->assertStringNotContainsString($resident->full_name, $csv);
        $this->assertStringNotContainsString('900000', $csv);
    }

    public function test_job_skips_inactive_resident_and_does_not_contact_api(): void
    {
        $resident = $this->resident();
        PropertyTaxSnapshot::create(['village_id' => $resident->village_id, 'resident_id' => $resident->id, 'nik_hash' => $resident->nik_hash]);
        $resident->update(['resident_status' => 'meninggal']);
        (new SyncResidentPropertyTax($resident->id, $resident->village_id))->handle(app(PropertyTaxSyncService::class));
        $this->assertDatabaseHas('property_tax_snapshots', ['resident_id' => $resident->id, 'error_code' => 'resident_inactive']);
        Http::assertNothingSent();
    }

    public function test_active_resident_job_fetches_and_persists_the_linked_api_result(): void
    {
        $resident = $this->resident();
        Http::fake(['*' => Http::response('Total Tunggakan: 250000')]);
        (new SyncResidentPropertyTax($resident->id, $resident->village_id))->handle(app(PropertyTaxSyncService::class));
        $this->assertDatabaseHas('property_tax_snapshots', ['resident_id' => $resident->id, 'village_id' => $resident->village_id, 'total_outstanding' => 250000, 'sync_status' => 'success']);
        $this->assertDatabaseCount('property_tax_assessments', 0);
    }

    public function test_error_json_cannot_masquerade_as_zero_arrears(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'total_tunggakan' => 0])]);
        $snapshot = app(PropertyTaxSyncService::class)->sync($this->resident());
        $this->assertSame('failed', $snapshot->sync_status);
        $this->assertNull($snapshot->total_outstanding);
    }

    public function test_empty_dashboard_is_unknown_not_zero_and_filters_are_scoped(): void
    {
        $resident = $this->resident();
        $user = User::factory()->create(['role' => 'admin_desa', 'active' => true, 'village_id' => $resident->village_id]);
        $this->actingAs($user)->get(route('admin.tax.index'))->assertOk()->assertSee('Belum terdata')->assertSee('Belum tersedia')->assertDontSee('<h2>Rp0</h2>', false);
        $this->actingAs($user)->get(route('admin.tax.index', ['hamlet' => 'Dusun II']))->assertOk()->assertDontSee($resident->full_name);
        PropertyTaxSnapshot::create(['village_id' => $resident->village_id, 'resident_id' => $resident->id, 'nik_hash' => $resident->nik_hash, 'sync_status' => 'success', 'total_outstanding' => 0, 'checked_at' => now()]);
        $this->actingAs($user)->get(route('admin.tax.index', ['sync' => 'unchecked']))->assertOk()->assertDontSee($resident->full_name);
        $this->actingAs($user)->get(route('admin.tax.index', ['sync' => 'success']))->assertOk()->assertSee($resident->full_name)->assertSee('Rp0');
    }

    public function test_legacy_formatted_nop_hash_is_reused_without_duplicate_object(): void
    {
        $resident = $this->resident();
        $nop = '12.34.567.890.123-0001.0';
        TaxObject::create(['village_id' => $resident->village_id, 'resident_id' => $resident->id, 'nop_hash' => hash_hmac('sha256', $nop, config('app.key'))]);
        $user = User::factory()->create(['role' => 'operator_pbb', 'active' => true, 'village_id' => $resident->village_id]);
        $this->actingAs($user)->post(route('admin.tax.store'), ['resident_id' => $resident->id, 'nop' => '123456789012300010', 'land_area' => 10, 'building_area' => 0, 'tax_year' => 2026, 'assessed_amount' => 100000])->assertSessionHas('success');
        $this->assertDatabaseCount('tax_objects', 1);
    }

    private function village(string $code = 'SIMARE'): Village
    {
        return Village::firstOrCreate(['code' => $code], ['name' => 'Desa '.$code, 'district' => 'Borbor', 'regency' => 'Toba', 'province' => 'Sumatera Utara']);
    }

    private function resident(string $nik = '1201010101010001', ?Village $village = null): Resident
    {
        return Resident::create(['village_id' => ($village ?? $this->village())->id, 'nik_encrypted' => Crypt::encryptString($nik), 'nik_hash' => hash_hmac('sha256', $nik, config('app.key')), 'full_name' => 'Warga '.substr($nik, -4), 'hamlet' => 'Dusun I', 'resident_status' => 'tetap']);
    }

    private function assessment(Resident $resident, string $nop, int $year, int $amount, int $paid, string $status, ?string $due): void
    {
        $object = TaxObject::create(['village_id' => $resident->village_id, 'resident_id' => $resident->id, 'nop_hash' => hash_hmac('sha256', $nop, config('app.key'))]);
        PropertyTaxAssessment::create(['tax_object_id' => $object->id, 'tax_year' => $year, 'assessed_amount' => $amount, 'paid_amount' => $paid, 'status' => $status, 'due_date' => $due]);
    }
}
