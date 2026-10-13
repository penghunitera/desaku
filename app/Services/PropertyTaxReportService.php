<?php

namespace App\Services;

use App\Models\PropertyTaxAssessment;
use App\Models\PropertyTaxSnapshot;
use App\Models\Resident;
use Illuminate\Support\Collection;

class PropertyTaxReportService
{
    public function yearly(int $villageId): Collection
    {
        return PropertyTaxAssessment::query()
            ->whereHas('taxObject', fn ($q) => $q->where('village_id', $villageId))
            ->selectRaw('tax_year, COUNT(*) AS object_count, SUM(assessed_amount) AS potential, SUM(paid_amount) AS realized')
            ->selectRaw('SUM(CASE WHEN assessed_amount > paid_amount THEN assessed_amount - paid_amount ELSE 0 END) AS outstanding')
            ->selectRaw("SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) AS verified_count")
            ->selectRaw("SUM(CASE WHEN status = 'verified' AND due_date < ? AND assessed_amount > paid_amount THEN assessed_amount - paid_amount ELSE 0 END) AS overdue", [today()->toDateString()])
            ->groupBy('tax_year')->orderBy('tax_year')->get()
            ->map(fn ($row) => [
                'year' => (int) $row->tax_year, 'object_count' => (int) $row->object_count,
                'verified_count' => (int) $row->verified_count,
                'potential' => (float) $row->potential, 'realized' => (float) $row->realized,
                'outstanding' => (float) $row->outstanding, 'overdue' => (float) $row->overdue,
            ]);
    }

    public function forYear(Collection $yearly, int $year): array
    {
        return $yearly->firstWhere('year', $year) ?? ['year' => $year, 'object_count' => 0, 'verified_count' => 0, 'potential' => 0, 'realized' => 0, 'outstanding' => 0, 'overdue' => 0];
    }

    public function apiCoverage(int $villageId): array
    {
        $residents = Resident::where('village_id', $villageId)->whereIn('resident_status', ['tetap', 'sementara']);
        $eligible = (clone $residents)->whereNotNull('nik_hash')->whereNotNull('nik_encrypted')->where('nik_encrypted', '!=', '')->count();
        $snapshots = PropertyTaxSnapshot::where('village_id', $villageId)
            ->whereHas('resident', fn ($q) => $q->where('village_id', $villageId)->whereIn('resident_status', ['tetap', 'sementara'])
                ->whereColumn('residents.nik_hash', 'property_tax_snapshots.nik_hash'))
            ->get();
        // Count a NIK only once even if legacy duplicate resident records exist.
        $successful = $snapshots->whereNotNull('checked_at')->sortByDesc('checked_at')->unique('nik_hash');

        return [
            'resident_count' => (clone $residents)->count(), 'eligible' => $eligible,
            'checked' => $successful->count(), 'total' => (float) $successful->sum('total_outstanding'),
            'fresh' => $successful->filter(fn ($s) => $s->checked_at->gte(now()->subDay()))->count(),
            'queued' => $snapshots->where('sync_status', 'queued')->count(),
            'failed' => $snapshots->where('sync_status', 'failed')->count(),
            'latest' => $successful->first()?->checked_at,
        ];
    }
}
