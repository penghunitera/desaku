<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncResidentPropertyTax;
use App\Models\PropertyTaxSnapshot;
use App\Models\Resident;
use App\Models\Village;
use App\Services\PropertyTaxReportService;
use App\Support\AdminAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyTaxWorkspaceController extends Controller
{
    public function index(Request $request, PropertyTaxReportService $reports): View
    {
        $this->authorizeRead($request);
        $filters = $request->validate([
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:'.(now()->year + 1)],
            'q' => ['nullable', 'string', 'max:100'], 'hamlet' => ['nullable', 'string', 'max:80'],
            'sync' => ['nullable', Rule::in(['success', 'failed', 'queued', 'unchecked'])],
        ]);
        $village = $this->village($request);
        $year = (int) ($filters['tahun'] ?? now()->year);
        $yearly = $reports->yearly($village->id);
        $query = $this->residents($village->id)
            ->with(['propertyTaxSnapshot', 'taxObjects.assessments' => fn ($q) => $q->where('tax_year', $year)])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('full_name', 'like', '%'.$term.'%'))
            ->when($filters['hamlet'] ?? null, fn ($q, $hamlet) => $q->where('hamlet', $hamlet));
        if (isset($filters['sync'])) {
            $matchingIdentity = fn ($q) => $q->whereColumn('property_tax_snapshots.nik_hash', 'residents.nik_hash');
            if ($filters['sync'] === 'unchecked') {
                $query->whereDoesntHave('propertyTaxSnapshot', fn ($q) => $matchingIdentity($q)->whereNotNull('checked_at'));
            } else {
                $query->whereHas('propertyTaxSnapshot', fn ($q) => $matchingIdentity($q)->where('sync_status', $filters['sync']));
            }
        }

        return view('admin.property-tax.index', [
            'village' => $village, 'year' => $year, 'yearly' => $yearly,
            'availableYears' => $yearly->pluck('year')->push(now()->year)->push($year)->unique()->sortDesc(),
            'summary' => $reports->forYear($yearly, $year), 'coverage' => $reports->apiCoverage($village->id),
            'residents' => $query->orderBy('full_name')->paginate(15)->withQueryString(),
            'hamlets' => $this->residents($village->id)->whereNotNull('hamlet')->distinct()->orderBy('hamlet')->pluck('hamlet'),
            'canManage' => AdminAccess::allows($request->user(), 'tax.manage'),
        ]);
    }

    public function syncResident(Request $request, Resident $resident): RedirectResponse
    {
        abort_unless(AdminAccess::allows($request->user(), 'tax.manage'), 403);
        abort_unless($resident->village_id === $this->village($request)->id, 404);
        if (! in_array($resident->resident_status, ['tetap', 'sementara'], true) || ! $resident->nik_encrypted || ! $resident->nik_hash) {
            return back()->withErrors(['sync' => 'Warga tidak aktif atau NIK belum tersedia. Lengkapi data penduduk terlebih dahulu.']);
        }
        $queued = $this->enqueue($resident, $request);

        return back()->with('success', $queued ? 'Pengecekan PBB warga masuk antrean. Muat ulang halaman untuk melihat hasil.' : 'Pengecekan warga ini masih dalam antrean.');
    }

    public function syncVillage(Request $request): RedirectResponse
    {
        abort_unless(AdminAccess::allows($request->user(), 'tax.manage'), 403);
        $request->validate(['hamlet' => ['nullable', 'string', 'max:80']]);
        if (config('queue.default') === 'sync') {
            return back()->withErrors(['sync' => 'Sinkronisasi massal membutuhkan QUEUE_CONNECTION=database dan worker antrean aktif.']);
        }
        $village = $this->village($request);
        $residents = $this->residents($village->id)->whereNotNull('nik_encrypted')->where('nik_encrypted', '!=', '')->whereNotNull('nik_hash')
            ->when($request->filled('hamlet'), fn ($q) => $q->where('hamlet', $request->string('hamlet')->toString()))
            ->whereDoesntHave('propertyTaxSnapshot', fn ($q) => $q
                ->whereColumn('property_tax_snapshots.nik_hash', 'residents.nik_hash')
                ->where(fn ($s) => $s->where(fn ($p) => $p->where('sync_status', 'queued')->where('queued_at', '>=', now()->subHours(2)))
                    ->orWhere(fn ($p) => $p->where('sync_status', 'success')->where('checked_at', '>=', now()->subDay()))))
            ->orderBy('id')->limit(500)->get()->unique('nik_hash');
        $count = 0;
        foreach ($residents as $resident) {
            $count += $this->enqueue($resident, $request) ? 1 : 0;
        }

        return back()->with('success', $count ? "$count warga masuk antrean PBB. Maksimal 500 warga per pengiriman; NIK dicek paling banyak 30 kali per menit. Kirim kembali untuk melanjutkan warga yang tersisa." : 'Tidak ada warga yang perlu disinkronkan. Hasil sukses kurang dari 24 jam dan antrean aktif dilewati.');
    }

    public function export(Request $request, PropertyTaxReportService $reports): StreamedResponse
    {
        $this->authorizeRead($request);
        $village = $this->village($request);
        $rows = $reports->yearly($village->id);

        return response()->streamDownload(function () use ($rows) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ['Tahun', 'Objek terdata', 'SPPT terverifikasi', 'Potensi ketetapan', 'Pembayaran tercatat', 'Sisa ketetapan', 'Tunggakan SPPT jatuh tempo'], ';', '"', '');
            foreach ($rows as $row) {
                fputcsv($file, [$row['year'], $row['object_count'], $row['verified_count'], $row['potential'], $row['realized'], $row['outstanding'], $row['overdue']], ';', '"', '');
            }
            fclose($file);
        }, 'rekap-pbb-desa-'.$village->id.'-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function enqueue(Resident $resident, Request $request): bool
    {
        $queued = DB::transaction(function () use ($resident, $request) {
            // Serialize dispatches in the village and avoid duplicate jobs from concurrent clicks.
            Village::whereKey($resident->village_id)->lockForUpdate()->firstOrFail();
            $snapshot = PropertyTaxSnapshot::firstOrNew(['resident_id' => $resident->id]);
            if ($snapshot->sync_status === 'queued' && $snapshot->queued_at?->gte(now()->subHours(2))) {
                return false;
            }
            if ($snapshot->nik_hash !== $resident->nik_hash) {
                $snapshot->total_outstanding = null;
                $snapshot->checked_at = null;
            }
            $snapshot->fill([
                'village_id' => $resident->village_id, 'nik_hash' => $resident->nik_hash,
                'sync_status' => 'queued', 'queued_at' => now(), 'error_code' => null, 'requested_by' => $request->user()->id,
            ])->save();
            DB::table('audit_logs')->insert([
                'village_id' => $resident->village_id, 'user_id' => $request->user()->id,
                'action' => 'property_tax.sync_queued', 'subject_type' => Resident::class,
                'subject_id' => $resident->id, 'ip_address' => $request->ip(), 'created_at' => now(),
            ]);

            return true;
        });
        if ($queued) {
            try {
                SyncResidentPropertyTax::dispatch($resident->id, $resident->village_id);
            } catch (\Throwable $exception) {
                PropertyTaxSnapshot::where('resident_id', $resident->id)->update(['sync_status' => 'failed', 'error_code' => 'worker_failed']);
                throw $exception;
            }
        }

        return $queued;
    }

    private function residents(int $villageId): Builder
    {
        return Resident::where('village_id', $villageId)->whereIn('resident_status', ['tetap', 'sementara']);
    }

    private function authorizeRead(Request $request): void
    {
        abort_unless(AdminAccess::allows($request->user(), 'tax.manage') || AdminAccess::allows($request->user(), 'reports.view'), 403);
    }

    private function village(Request $request): Village
    {
        if ($request->user()->village_id) {
            return Village::findOrFail($request->user()->village_id);
        }

        return Village::firstOrCreate(['code' => 'SIMARE'], ['name' => 'Desa Simare', 'district' => 'Borbor', 'regency' => 'Toba', 'province' => 'Sumatera Utara']);
    }
}
