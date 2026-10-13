<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BumdesProduct;
use App\Models\PropertyTaxAssessment;
use App\Models\Resident;
use App\Models\ServiceRequest;
use App\Models\TaxObject;
use App\Models\Village;
use App\Models\VillagePotential;
use App\Services\DptStatisticsService;
use App\Support\AdminAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminWorkspaceController extends Controller
{
    public function dashboard(Request $request, DptStatisticsService $dptStatistics): View
    {
        $validated = $request->validate([
            'dpt_kecamatan' => ['nullable', 'string', 'max:120'],
            'dpt_desa' => ['nullable', 'string', 'max:350'],
            'dpt_nama' => ['nullable', 'string', 'max:120'],
            'dpt_usia_min' => ['nullable', 'integer', 'between:0,120'],
            'dpt_usia_max' => ['nullable', 'integer', 'between:0,120'],
            'dpt_gender' => ['nullable', Rule::in(['L', 'P', 'unknown'])],
            'dpt_page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);
        if (isset($validated['dpt_usia_min'], $validated['dpt_usia_max']) && (int) $validated['dpt_usia_max'] < (int) $validated['dpt_usia_min']) {
            throw ValidationException::withMessages(['dpt_usia_max' => 'Usia maksimal harus sama atau lebih besar dari usia minimal.']);
        }
        $filters = array_intersect_key($validated, array_flip(['dpt_kecamatan', 'dpt_desa']));
        $dpt = AdminAccess::canViewDptStatistics($request->user())
            ? $dptStatistics->dashboard($filters, $request->user()->village_id ? Village::find($request->user()->village_id) : null) : null;
        if ($dpt && $dpt['available']) {
            $dpt['canViewRecords'] = AdminAccess::allows($request->user(), 'residents.view');
            $dpt['recordFilters'] = array_intersect_key($validated, array_flip(['dpt_nama', 'dpt_usia_min', 'dpt_usia_max', 'dpt_gender']));
            $dpt['records'] = $dpt['canViewRecords'] && $dpt['selectedVillage'] !== ''
                ? $dptStatistics->records($dpt, $dpt['recordFilters'], (int) ($validated['dpt_page'] ?? 1)) : null;
        }

        return view('admin.dashboard', [
            'dpt' => $dpt,
            'residentCount' => Resident::count(), 'requestCount' => ServiceRequest::count(),
            'productCount' => BumdesProduct::count(), 'pbbPotential' => PropertyTaxAssessment::where('tax_year', now()->year)->whereHas('taxObject', fn ($q) => $q->where('village_id', $this->taxVillage($request)->id))->sum('assessed_amount'),
            'recentRequests' => ServiceRequest::with('resident')->latest()->limit(6)->get(),
        ]);
    }

    public function data(Request $request): View
    {
        $section = $request->string('bagian', 'penduduk')->toString();
        abort_unless(in_array($section, ['penduduk', 'bumdes', 'pbb', 'potensi'], true), 404);
        if ($section === 'pbb') {
            abort_unless(AdminAccess::allows($request->user(), 'tax.manage'), 403);
        }
        $records = match ($section) {
            'penduduk' => Resident::latest()->paginate(12),
            'bumdes' => BumdesProduct::latest()->paginate(12),
            'pbb' => PropertyTaxAssessment::whereHas('taxObject', fn ($q) => $q->where('village_id', $this->taxVillage($request)->id))->with('taxObject.resident')->orderByDesc('tax_year')->paginate(12),
            'potensi' => VillagePotential::latest('year')->paginate(12),
        };

        $taxResidents = $section === 'pbb' ? Resident::where('village_id', $this->taxVillage($request)->id)->whereIn('resident_status', ['tetap', 'sementara'])->orderBy('full_name')->get(['id', 'full_name', 'hamlet']) : collect();

        return view('admin.data', compact('section', 'records', 'taxResidents'));
    }

    public function resident(Request $request): RedirectResponse
    {
        $data = $request->validate(['nik' => ['required', 'digits:16'], 'full_name' => ['required', 'max:120'], 'phone' => ['nullable', 'max:20'], 'birth_date' => ['nullable', 'date'], 'hamlet' => ['nullable', 'max:80']]);
        $village = $this->village();
        Resident::updateOrCreate(['village_id' => $village->id, 'nik_hash' => hash_hmac('sha256', $data['nik'], config('app.key'))], [
            'nik_encrypted' => Crypt::encryptString($data['nik']), 'full_name' => $data['full_name'], 'phone' => $data['phone'] ?? null,
            'birth_date' => $data['birth_date'] ?? null, 'hamlet' => $data['hamlet'] ?? null,
        ]);
        $this->audit('resident.saved');

        return back()->with('success', 'Data penduduk berhasil disimpan.');
    }

    public function product(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'max:150'], 'category' => ['required', 'max:60'], 'price' => ['required', 'numeric', 'min:0'], 'stock' => ['required', 'integer', 'min:0'], 'published' => ['nullable', 'boolean']]);
        BumdesProduct::create([...$data, 'village_id' => $this->village()->id, 'sku' => 'SMR-'.strtoupper(Str::random(8)), 'published' => $request->boolean('published')]);
        $this->audit('bumdes_product.created');

        return back()->with('success', 'Produk BUMDes berhasil ditambahkan.');
    }

    public function tax(Request $request): RedirectResponse
    {
        abort_unless(AdminAccess::allows($request->user(), 'tax.manage'), 403);
        $data = $request->validate([
            'resident_id' => ['nullable', 'required_without:nik', 'integer', 'min:1'], 'nik' => ['nullable', 'required_without:resident_id', 'digits:16'],
            'nop' => ['required', 'string', 'max:30', 'regex:/^[0-9.\- ]+$/'],
            'land_area' => ['required', 'numeric', 'min:0', 'max:9999999999'], 'building_area' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'tax_year' => ['required', 'integer', 'min:2000', 'max:'.(now()->year + 1)],
            'assessed_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999'], 'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'status' => ['nullable', Rule::in(['estimated', 'verified'])], 'due_date' => ['nullable', 'date'],
        ]);
        $village = $this->taxVillage($request);
        $resident = Resident::where('village_id', $village->id)->when(! empty($data['resident_id']), fn ($q) => $q->whereKey($data['resident_id']), fn ($q) => $q->where('nik_hash', hash_hmac('sha256', $data['nik'], config('app.key'))))->firstOrFail();
        $nop = preg_replace('/\D/', '', $data['nop']);
        if (strlen($nop) !== 18) {
            return back()->withInput()->withErrors(['nop' => 'NOP harus terdiri dari 18 angka; tanda titik dan tanda hubung diperbolehkan.']);
        }
        DB::transaction(function () use ($data, $village, $resident, $nop, $request) {
            Village::whereKey($village->id)->lockForUpdate()->firstOrFail();
            $hash = hash_hmac('sha256', $nop, config('app.key'));
            $formatted = substr($nop, 0, 2).'.'.substr($nop, 2, 2).'.'.substr($nop, 4, 3).'.'.substr($nop, 7, 3).'.'.substr($nop, 10, 3).'-'.substr($nop, 13, 4).'.'.substr($nop, 17, 1);
            $objects = TaxObject::where('village_id', $village->id)->whereIn('nop_hash', array_unique([$hash, hash_hmac('sha256', $data['nop'], config('app.key')), hash_hmac('sha256', $formatted, config('app.key'))]))->get();
            abort_if($objects->count() > 1, 422, 'NOP memiliki data ganda. Rekonsiliasi objek pajak terlebih dahulu.');
            $object = $objects->first() ?? new TaxObject(['village_id' => $village->id, 'nop_hash' => $hash]);
            abort_if($object->resident_id && $object->resident_id !== $resident->id, 422, 'NOP sudah terhubung dengan warga lain. Verifikasi perubahan pemilik terlebih dahulu.');
            $object->fill(['resident_id' => $resident->id, 'nop_encrypted' => Crypt::encryptString($nop), 'land_area' => $data['land_area'], 'building_area' => $data['building_area']])->save();
            $status = $data['status'] ?? 'estimated';
            PropertyTaxAssessment::updateOrCreate(['tax_object_id' => $object->id, 'tax_year' => $data['tax_year']], [
                'assessed_amount' => $data['assessed_amount'], 'paid_amount' => $data['paid_amount'] ?? 0,
                'status' => $status, 'verified_at' => $status === 'verified' ? now() : null, 'due_date' => $data['due_date'] ?? null,
            ]);
            DB::table('audit_logs')->insert(['village_id' => $village->id, 'user_id' => $request->user()->id, 'action' => 'property_tax.saved', 'subject_type' => TaxObject::class, 'subject_id' => $object->id, 'ip_address' => $request->ip(), 'created_at' => now()]);
        });

        return back()->with('success', 'Potensi PBB tahunan berhasil disimpan.');
    }

    public function potential(Request $request): RedirectResponse
    {
        $data = $request->validate(['year' => ['required', 'integer', 'min:2000', 'max:'.(now()->year + 5)], 'category' => ['required', 'max:80'], 'name' => ['required', 'max:150'], 'value' => ['required', 'numeric', 'min:0'], 'unit' => ['required', 'max:40'], 'notes' => ['nullable', 'max:1000']]);
        VillagePotential::create([...$data, 'village_id' => $this->village()->id]);
        $this->audit('village_potential.created');

        return back()->with('success', 'Data potensi desa berhasil ditambahkan.');
    }

    private function village(): Village
    {
        return Village::firstOrCreate(['code' => 'SIMARE'], ['name' => 'Desa Simare', 'district' => 'Borbor', 'regency' => 'Toba', 'province' => 'Sumatera Utara']);
    }

    private function taxVillage(Request $request): Village
    {
        return $request->user()->village_id ? Village::findOrFail($request->user()->village_id) : $this->village();
    }

    private function audit(string $action): void
    {
        DB::table('audit_logs')->insert(['village_id' => $this->village()->id, 'user_id' => auth()->id(), 'action' => $action, 'ip_address' => request()->ip(), 'created_at' => now()]);
    }
}
