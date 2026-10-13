<?php

namespace App\Services;

use App\Models\Village;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DptStatisticsService
{
    private const AGE_BANDS = [
        '0_14' => '0–14 tahun',
        '15_24' => '15–24 tahun',
        '25_34' => '25–34 tahun',
        '35_44' => '35–44 tahun',
        '45_54' => '45–54 tahun',
        '55_64' => '55–64 tahun',
        '65_plus' => '65 tahun ke atas',
        'unknown' => 'Usia belum valid',
    ];

    public function dashboard(array $filters, ?Village $village = null): array
    {
        if (! Schema::hasView('dpt_demografi') || ! Schema::hasView('dpt_potensi_desa')) {
            return ['available' => false];
        }

        $villages = DB::table('dpt_potensi_desa')
            ->orderBy('kecamatan')->orderBy('desa_kelurahan')->get()
            ->map(fn ($row) => [...(array) $row, 'key' => implode('|', [$row->kabupaten, $row->kecamatan, $row->desa_kelurahan])]);
        $districts = $villages->pluck('kecamatan')->unique()->values();

        if ($filters === []) {
            $name = Str::upper(preg_replace('/^(desa|kelurahan)\s+/i', '', trim($village?->name ?? 'Simare')));
            $district = Str::upper(trim($village?->district ?? 'Borbor'));
            $regency = Str::upper(trim($village?->regency ?? 'Toba'));
            $default = $villages->first(fn ($row) => $row['desa_kelurahan'] === $name && $row['kecamatan'] === $district && $row['kabupaten'] === $regency);
            $filters = ['dpt_kecamatan' => $default['kecamatan'] ?? '', 'dpt_desa' => $default['key'] ?? ''];
        }
        $selectedDistrict = $filters['dpt_kecamatan'] ?? '';
        $selectedVillage = $filters['dpt_desa'] ?? '';
        abort_unless($selectedDistrict === '' || $districts->contains($selectedDistrict), 422, 'Kecamatan DPT tidak tersedia.');
        $selected = $selectedVillage === '' ? null : $villages->firstWhere('key', $selectedVillage);
        abort_unless($selectedVillage === '' || $selected !== null, 422, 'Desa DPT tidak tersedia.');
        abort_if($selected && $selectedDistrict !== '' && $selected['kecamatan'] !== $selectedDistrict, 422, 'Desa dan kecamatan DPT tidak sesuai.');

        $scope = $villages->filter(fn ($row) => ($selectedDistrict === '' || $row['kecamatan'] === $selectedDistrict)
            && ($selectedVillage === '' || $row['key'] === $selectedVillage)
        );
        $summary = [
            'total' => (int) $scope->sum('jumlah_dpt'),
            'male' => (int) $scope->sum('laki_laki'),
            'female' => (int) $scope->sum('perempuan'),
            'unknown_gender' => (int) $scope->sum('gender_belum_valid'),
            'working_age' => (int) $scope->sum('usia_15_64'),
            'senior' => (int) $scope->sum('usia_65_ke_atas'),
            'unknown_age' => (int) $scope->sum('usia_belum_valid'),
            'tps' => (int) $scope->sum('jumlah_tps'),
            'villages' => $scope->count(),
        ];
        $query = DB::table('dpt_demografi')->where('baris_judul', 0)
            ->when($selectedDistrict !== '', fn ($query) => $query->where('kecamatan', $selectedDistrict))
            ->when($selected, fn ($query) => $query->where('kabupaten', $selected['kabupaten'])
                ->where('kecamatan', $selected['kecamatan'])->where('desa_kelurahan', $selected['desa_kelurahan']));
        $ageSql = "CASE WHEN usia_tercatat BETWEEN 0 AND 14 THEN '0_14'
            WHEN usia_tercatat BETWEEN 15 AND 24 THEN '15_24'
            WHEN usia_tercatat BETWEEN 25 AND 34 THEN '25_34'
            WHEN usia_tercatat BETWEEN 35 AND 44 THEN '35_44'
            WHEN usia_tercatat BETWEEN 45 AND 54 THEN '45_54'
            WHEN usia_tercatat BETWEEN 55 AND 64 THEN '55_64'
            WHEN usia_tercatat >= 65 THEN '65_plus' ELSE 'unknown' END";
        $counts = $query->selectRaw($ageSql.' AS age_band, jenis_kelamin, COUNT(*) AS total')
            ->groupByRaw($ageSql)->groupBy('jenis_kelamin')->get();
        $ages = collect(self::AGE_BANDS)->map(function ($label, $key) use ($counts, $summary) {
            $rows = $counts->where('age_band', $key);
            $total = (int) $rows->sum('total');

            return [
                'key' => $key, 'label' => $label, 'total' => $total,
                'male' => (int) $rows->where('jenis_kelamin', 'L')->sum('total'),
                'female' => (int) $rows->where('jenis_kelamin', 'P')->sum('total'),
                'unknown' => (int) $rows->whereNotIn('jenis_kelamin', ['L', 'P'])->sum('total'),
                'percent' => $this->percent($total, $summary['total']),
            ];
        })->values();
        $summary['young_adults'] = (int) $ages->whereIn('key', ['15_24', '25_34'])->sum('total');
        $summary['working_age_percent'] = $this->percent($summary['working_age'], $summary['total']);
        $summary['young_adults_percent'] = $this->percent($summary['young_adults'], $summary['total']);
        $summary['senior_percent'] = $this->percent($summary['senior'], $summary['total']);
        $genders = collect([
            ['label' => 'Laki-laki', 'key' => 'male'],
            ['label' => 'Perempuan', 'key' => 'female'],
            ['label' => 'Belum valid', 'key' => 'unknown_gender'],
        ])->map(fn ($row) => [...$row, 'total' => $summary[$row['key']], 'percent' => $this->percent($summary[$row['key']], $summary['total'])]);
        $source = Schema::hasTable('dpt_imports')
            ? DB::table('dpt_imports')->first(['source_filename', 'source_generated_at']) : null;

        return [
            'available' => true, 'summary' => $summary, 'ages' => $ages, 'genders' => $genders,
            'districts' => $districts, 'villages' => $villages->map(fn ($row) => [
                'key' => $row['key'], 'district' => $row['kecamatan'], 'name' => $row['desa_kelurahan'],
            ])->values(),
            'selectedDistrict' => $selectedDistrict, 'selectedVillage' => $selectedVillage,
            'label' => $selected ? Str::title($selected['desa_kelurahan']).' · '.Str::title($selected['kecamatan'])
                : ($selectedDistrict !== '' ? 'Kecamatan '.Str::title($selectedDistrict) : 'Seluruh desa / kelurahan'),
            'source' => $source,
        ];
    }

    private function percent(int $count, int $total): float
    {
        return $total > 0 ? round($count / $total * 100, 1) : 0.0;
    }

    public function records(array $dashboard, array $filters, int $page = 1): LengthAwarePaginator
    {
        abort_unless($dashboard['available'] && $dashboard['selectedVillage'] !== '', 422, 'Pilih satu desa untuk melihat daftar DPT.');
        [$regency, $district, $village] = explode('|', $dashboard['selectedVillage']);
        $query = DB::table('dpt_demografi as population')
            ->join('dpt as source', 'source.iddpt', '=', 'population.iddpt')
            ->where('population.baris_judul', 0)
            ->where('population.kabupaten', $regency)
            ->where('population.kecamatan', $district)
            ->where('population.desa_kelurahan', $village);
        $name = trim($filters['dpt_nama'] ?? '');
        if ($name !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::upper($name)).'%';
            $query->whereRaw("UPPER(source.nama) LIKE ? ESCAPE '!'", [$pattern]);
        }
        if (isset($filters['dpt_usia_min'])) {
            $query->where('population.usia_tercatat', '>=', (int) $filters['dpt_usia_min']);
        }
        if (isset($filters['dpt_usia_max'])) {
            $query->where('population.usia_tercatat', '<=', (int) $filters['dpt_usia_max']);
        }
        $gender = $filters['dpt_gender'] ?? '';
        if ($gender === 'unknown') {
            $query->whereNotIn('population.jenis_kelamin', ['L', 'P']);
        } elseif ($gender !== '') {
            $query->where('population.jenis_kelamin', $gender);
        }

        return $query->orderByRaw('UPPER(source.nama)')->orderBy('source.iddpt')->paginate(25, [
            'source.iddpt', 'source.nama', 'population.usia_tercatat',
            'population.jenis_kelamin', 'population.tps',
        ], 'dpt_page', $page)->appends([
            'dpt_kecamatan' => $dashboard['selectedDistrict'], 'dpt_desa' => $dashboard['selectedVillage'],
            ...array_filter($filters, fn ($value) => $value !== null && $value !== ''),
        ])->fragment('dpt-list');
    }
}
