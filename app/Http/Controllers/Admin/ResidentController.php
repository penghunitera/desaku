<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FamilyCard;
use App\Models\Resident;
use App\Models\Village;
use App\Support\AdminAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResidentController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(AdminAccess::allows($request->user(), 'residents.view'), 403);
        $query = Resident::query()->where('village_id', $this->village($request)->id)->with('familyCard')->withCount(['serviceRequests', 'taxObjects']);

        $query->when($request->filled('q'), function ($builder) use ($request) {
            $term = trim($request->string('q')->toString());
            $builder->where(fn ($q) => $q->where('full_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('address', 'like', "%{$term}%"));
        })->when($request->filled('hamlet'), fn ($q) => $q->where('hamlet', $request->string('hamlet')))
            ->when($request->filled('status'), fn ($q) => $q->where('resident_status', $request->string('status')))
            ->when($request->filled('gender'), fn ($q) => $q->where('gender', $request->string('gender')));

        return view('admin.residents.index', [
            'residents' => $query->orderBy('full_name')->paginate(15)->withQueryString(),
            'hamlets' => Resident::where('village_id', $this->village($request)->id)->whereNotNull('hamlet')->distinct()->orderBy('hamlet')->pluck('hamlet'),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless(AdminAccess::allows($request->user(), 'residents.manage'), 403);

        return view('admin.residents.form', ['resident' => new Resident, 'familyCards' => FamilyCard::where('village_id', $this->village($request)->id)->withCount('residents')->latest()->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(AdminAccess::allows($request->user(), 'residents.manage'), 403);
        $resident = DB::transaction(fn () => $this->persist($request));
        $this->audit($request, 'resident.created', $resident);

        return redirect()->route('admin.residents.show', $resident)->with('success', 'Data penduduk berhasil disimpan.');
    }

    public function show(Request $request, Resident $resident): View
    {
        abort_unless(AdminAccess::allows($request->user(), 'residents.view'), 403);
        abort_unless($resident->village_id === $this->village($request)->id, 404);
        $resident->load(['familyCard.residents', 'serviceRequests.histories', 'taxObjects.assessments']);
        if (AdminAccess::allows($request->user(), 'tax.manage') || AdminAccess::allows($request->user(), 'reports.view')) {
            $resident->load('propertyTaxSnapshot');
        }

        return view('admin.residents.show', compact('resident'));
    }

    public function edit(Request $request, Resident $resident): View
    {
        abort_unless(AdminAccess::allows($request->user(), 'residents.manage'), 403);
        abort_unless($resident->village_id === $this->village($request)->id, 404);

        return view('admin.residents.form', ['resident' => $resident, 'familyCards' => FamilyCard::where('village_id', $this->village($request)->id)->withCount('residents')->latest()->get()]);
    }

    public function update(Request $request, Resident $resident): RedirectResponse
    {
        abort_unless(AdminAccess::allows($request->user(), 'residents.manage'), 403);
        abort_unless($resident->village_id === $this->village($request)->id, 404);
        DB::transaction(fn () => $this->persist($request, $resident));
        $this->audit($request, 'resident.updated', $resident);

        return redirect()->route('admin.residents.show', $resident)->with('success', 'Perubahan data penduduk berhasil disimpan.');
    }

    private function persist(Request $request, ?Resident $resident = null): Resident
    {
        $data = $request->validate([
            'nik' => [$resident ? 'nullable' : 'required', 'nullable', 'digits:16'],
            'full_name' => ['required', 'string', 'max:150'],
            'gender' => ['nullable', Rule::in(['laki-laki', 'perempuan'])],
            'birth_place' => ['nullable', 'max:100'], 'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'religion' => ['nullable', 'max:30'], 'marital_status' => ['nullable', 'max:30'],
            'education' => ['nullable', 'max:100'], 'occupation' => ['nullable', 'max:100'],
            'resident_status' => ['required', Rule::in(['tetap', 'sementara', 'pindah', 'meninggal'])],
            'blood_type' => ['nullable', 'max:5'], 'phone' => ['nullable', 'max:25'], 'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'max:255'], 'hamlet' => ['nullable', 'max:80'], 'rw' => ['nullable', 'max:5'], 'rt' => ['nullable', 'max:5'],
            'family_card_id' => ['nullable', 'exists:family_cards,id'], 'family_relationship' => ['nullable', 'max:60'], 'notes' => ['nullable', 'max:2000'],
            'citizen_pin' => ['nullable', 'digits:6'],
        ]);

        $resident ??= new Resident(['village_id' => $this->village($request)->id]);
        if (! empty($data['nik'])) {
            $hash = hash_hmac('sha256', $data['nik'], config('app.key'));
            $duplicate = Resident::where('village_id', $resident->village_id)->where('nik_hash', $hash)->when($resident->exists, fn ($q) => $q->whereKeyNot($resident->id))->exists();
            abort_if($duplicate, 422, 'NIK telah terdaftar.');
            $data['nik_encrypted'] = Crypt::encryptString($data['nik']);
            $data['nik_hash'] = $hash;
        }
        unset($data['nik']);
        if (! empty($data['citizen_pin'])) {
            $data['citizen_pin_hash'] = Hash::make($data['citizen_pin']);
        }
        unset($data['citizen_pin']);
        $resident->fill($data)->save();

        return $resident;
    }

    private function village(Request $request): Village
    {
        return Village::find($request->user()->village_id) ?? Village::firstOrCreate(['code' => 'SIMARE'], ['name' => 'Desa Simare', 'district' => 'Borbor', 'regency' => 'Toba', 'province' => 'Sumatera Utara']);
    }

    private function audit(Request $request, string $action, Resident $resident): void
    {
        DB::table('audit_logs')->insert(['village_id' => $resident->village_id, 'user_id' => $request->user()->id, 'action' => $action, 'subject_type' => Resident::class, 'subject_id' => $resident->id, 'ip_address' => $request->ip(), 'created_at' => now()]);
    }
}
