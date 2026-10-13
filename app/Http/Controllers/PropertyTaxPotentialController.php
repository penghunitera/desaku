<?php

namespace App\Http\Controllers;

use App\Models\Resident;
use App\Models\Village;
use App\Services\PropertyTaxReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertyTaxPotentialController extends Controller
{
    public function index(Request $request): View
    {
        $reports = app(PropertyTaxReportService::class);
        $yearly = $reports->yearly($this->villageId());
        $availableYears = $yearly->pluck('year')->sortDesc()->values();
        $year = $request->integer('tahun') ?: ($availableYears->first() ?? now()->year);
        $summary = $reports->forYear($yearly, $year);

        return view('property-tax.potential', compact('availableYears', 'year', 'yearly', 'summary'));
    }

    public function resident(Request $request): View
    {
        $validated = $request->validate([
            'nik' => ['required', 'digits:16'],
            'tax_year' => ['required', 'integer', 'min:2000', 'max:'.(now()->year + 1)],
        ], ['nik.digits' => 'NIK harus terdiri dari 16 angka.', 'tax_year.max' => 'Tahun pajak belum tersedia.']);
        $resident = Resident::where('village_id', $this->villageId())->where('nik_hash', hash_hmac('sha256', $validated['nik'], config('app.key')))->first();
        $assessments = $resident?->taxObjects()
            ->with(['assessments' => fn ($query) => $query->where('tax_year', $validated['tax_year'])])
            ->get()->flatMap->assessments ?? collect();

        return $this->index($request)->with([
            'residentSearched' => true,
            'residentYear' => (int) $validated['tax_year'],
            'residentFound' => $resident !== null,
            'residentAssessment' => ['object_count' => $assessments->count(), 'potential' => (float) $assessments->sum('assessed_amount'), 'paid' => (float) $assessments->sum('paid_amount')],
        ]);
    }

    private function villageId(): int
    {
        return (int) (Village::where('code', 'SIMARE')->value('id') ?? 0);
    }
}
