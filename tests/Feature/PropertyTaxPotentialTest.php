<?php

namespace Tests\Feature;

use App\Models\PropertyTaxAssessment;
use App\Models\Resident;
use App\Models\TaxObject;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class PropertyTaxPotentialTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_aggregate_without_identity(): void
    {
        [$resident, $taxObject] = $this->taxData();
        PropertyTaxAssessment::create(['tax_object_id' => $taxObject->id, 'tax_year' => 2026, 'assessed_amount' => 250000, 'paid_amount' => 100000]);
        $this->get('/pbb/potensi?tahun=2026')->assertOk()->assertSee('Rp250.000')->assertSee('Rp100.000')->assertDontSee($resident->full_name)->assertDontSee('1201010101010001');
    }

    public function test_resident_can_check_annual_estimate_by_nik(): void
    {
        [, $taxObject] = $this->taxData();
        PropertyTaxAssessment::create(['tax_object_id' => $taxObject->id, 'tax_year' => 2026, 'assessed_amount' => 325000, 'paid_amount' => 0]);
        $this->post('/pbb/potensi/warga', ['nik' => '1201010101010001', 'tax_year' => 2026])->assertOk()->assertSee('TOTAL ESTIMASI 2026')->assertSee('Rp325.000');
    }

    private function taxData(): array
    {
        $village = Village::create(['code' => 'SIMARE', 'name' => 'Desa Simare', 'district' => 'Borbor', 'regency' => 'Toba', 'province' => 'Sumatera Utara']);
        $nik = '1201010101010001';
        $resident = Resident::create(['village_id' => $village->id, 'nik_encrypted' => Crypt::encryptString($nik), 'nik_hash' => hash_hmac('sha256', $nik, config('app.key')), 'full_name' => 'Warga Rahasia']);
        $taxObject = TaxObject::create(['village_id' => $village->id, 'resident_id' => $resident->id, 'nop_hash' => hash('sha256', '12.34.567.890.123-0001.0'), 'land_area' => 500, 'building_area' => 80]);

        return [$resident, $taxObject];
    }
}
