<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Resident;
use App\Models\TbCase;
use App\Models\Village;
use App\Support\AdminAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TbProgramController extends Controller
{
    public function summary(): View
    {
        abort_unless(
            AdminAccess::allows(auth()->user(), 'health.summary') || AdminAccess::allows(auth()->user(), 'health.view'),
            403
        );

        $villageId = auth()->user()->village_id;
        $base = TbCase::where('village_id', $villageId);
        $byHamlet = DB::table('tb_cases')
            ->join('residents', 'residents.id', '=', 'tb_cases.resident_id')
            ->where('tb_cases.village_id', $villageId)
            ->selectRaw("COALESCE(residents.hamlet, 'Belum diisi') as hamlet")
            ->selectRaw('COUNT(*) as screened')
            ->selectRaw("SUM(CASE WHEN tb_cases.status = 'treatment' THEN 1 ELSE 0 END) as treatment")
            ->selectRaw("SUM(CASE WHEN tb_cases.status IN ('completed','recovered') THEN 1 ELSE 0 END) as completed")
            ->groupBy('residents.hamlet')
            ->orderBy('hamlet')
            ->get();

        return view('admin.health.summary', [
            'metrics' => [
                'screened' => (clone $base)->count(),
                'follow_up' => (clone $base)->whereIn('status', ['suspected', 'referred', 'examined', 'confirmed'])->count(),
                'treatment' => (clone $base)->where('status', 'treatment')->count(),
                'completed' => (clone $base)->whereIn('status', ['completed', 'recovered'])->count(),
            ],
            'byHamlet' => $byHamlet,
        ]);
    }

    public function index(Request $request): View
    {
        $this->authorizeAccess('health.view');
        $villageId = auth()->user()->village_id;

        $cases = TbCase::with(['resident', 'contacts', 'treatmentLogs' => fn ($query) => $query->latest('recorded_at')])
            ->where('village_id', $villageId)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('screened_at')
            ->paginate(12)
            ->withQueryString();
        $base = TbCase::where('village_id', $villageId);

        return view('admin.health.tb', [
            'cases' => $cases,
            'residents' => Resident::where('village_id', $villageId)->orderBy('full_name')->get(['id', 'full_name', 'hamlet', 'birth_date']),
            'metrics' => [
                'screened' => (clone $base)->count(),
                'suspected' => (clone $base)->whereIn('status', ['suspected', 'referred', 'examined'])->count(),
                'treatment' => (clone $base)->where('status', 'treatment')->count(),
                'completed' => (clone $base)->whereIn('status', ['completed', 'recovered'])->count(),
                'pending_contacts' => DB::table('tb_contacts')->where('screening_status', 'pending')->count(),
                'overdue' => (clone $base)->where('status', 'treatment')->whereDate('next_visit_at', '<', today())->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess('health.manage');
        $data = $request->validate([
            'resident_id' => [
                'required',
                Rule::exists('residents', 'id')->where('village_id', auth()->user()->village_id),
                Rule::unique('tb_cases', 'resident_id')->where('village_id', auth()->user()->village_id),
            ],
            'screened_at' => ['required', 'date', 'before_or_equal:today'],
            'symptoms' => ['nullable', 'array'],
            'symptoms.*' => ['string', Rule::in(['cough', 'fever', 'night_sweats', 'weight_loss', 'close_contact'])],
            'risk_group' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $resident = Resident::findOrFail($data['resident_id']);

        $case = TbCase::create([
            'village_id' => $resident->village_id,
            'resident_id' => $resident->id,
            'case_code' => 'TB-SMR-'.now()->format('ym').'-'.strtoupper(Str::random(5)),
            'status' => 'suspected',
            'screened_at' => $data['screened_at'],
            'clinical_data_encrypted' => [
                'symptoms' => $data['symptoms'] ?? [],
                'risk_group' => $data['risk_group'] ?? null,
                'notes' => $data['notes'] ?? null,
            ],
            'created_by' => auth()->id(),
        ]);
        $this->audit('tb.screening.created', $case->id);

        return back()->with('success', 'Skrining tersimpan dengan kode '.$case->case_code.'.');
    }

    public function update(Request $request, TbCase $tbCase): RedirectResponse
    {
        $this->authorizeAccess('health.manage');
        $this->authorizeCase($tbCase);
        $data = $request->validate([
            'status' => ['required', Rule::in(['suspected', 'referred', 'examined', 'confirmed', 'treatment', 'completed', 'recovered', 'lost_follow_up', 'not_tb'])],
            'treatment_started_at' => ['nullable', 'date'],
            'treatment_due_at' => ['nullable', 'date', 'after_or_equal:treatment_started_at'],
            'next_visit_at' => ['nullable', 'date'],
            'referral_facility' => ['required', 'string', 'max:120'],
        ]);
        $tbCase->update($data);
        $this->audit('tb.case.updated', $tbCase->id);

        return back()->with('success', 'Status kasus '.$tbCase->case_code.' diperbarui.');
    }

    public function contact(Request $request, TbCase $tbCase): RedirectResponse
    {
        $this->authorizeAccess('health.manage');
        $this->authorizeCase($tbCase);
        $data = $request->validate([
            'resident_id' => [
                'required',
                Rule::exists('residents', 'id')->where('village_id', auth()->user()->village_id),
                Rule::notIn([$tbCase->resident_id]),
            ],
            'relationship' => ['nullable', 'string', 'max:60'],
            'priority' => ['nullable', 'boolean'],
            'screening_status' => ['required', Rule::in(['pending', 'screened', 'referred', 'clear'])],
            'tpt_status' => ['required', Rule::in(['not_assessed', 'eligible', 'started', 'completed', 'not_eligible'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $contact = $tbCase->contacts()->updateOrCreate(
            ['resident_id' => $data['resident_id']],
            [
                'relationship' => $data['relationship'] ?? null,
                'priority' => $request->boolean('priority'),
                'screening_status' => $data['screening_status'],
                'screened_at' => $data['screening_status'] === 'screened' ? today() : null,
                'tpt_status' => $data['tpt_status'],
                'notes_encrypted' => $data['notes'] ?? null,
            ]
        );
        $this->audit('tb.contact.saved', $contact->id);

        return back()->with('success', 'Kontak serumah berhasil dicatat.');
    }

    public function treatment(Request $request, TbCase $tbCase): RedirectResponse
    {
        $this->authorizeAccess('health.manage');
        $this->authorizeCase($tbCase);
        $data = $request->validate([
            'recorded_at' => ['required', 'date', 'before_or_equal:today'],
            'adherence' => ['required', Rule::in(['taken', 'missed', 'unknown'])],
            'visit_type' => ['required', Rule::in(['medicine', 'control', 'home_visit', 'sputum_test'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'next_visit_at' => ['nullable', 'date', 'after_or_equal:recorded_at'],
        ]);
        $log = $tbCase->treatmentLogs()->create([
            ...collect($data)->except(['notes', 'next_visit_at'])->all(),
            'notes_encrypted' => $data['notes'] ?? null,
            'recorded_by' => auth()->id(),
        ]);
        $tbCase->update(['next_visit_at' => $data['next_visit_at'] ?? $tbCase->next_visit_at]);
        $this->audit('tb.treatment.recorded', $log->id);

        return back()->with('success', 'Pemantauan pengobatan berhasil dicatat.');
    }

    private function authorizeAccess(string $permission): void
    {
        abort_unless(AdminAccess::allows(auth()->user(), $permission), 403);
    }

    private function authorizeCase(TbCase $tbCase): void
    {
        abort_unless($tbCase->village_id === auth()->user()->village_id, 404);
    }

    private function audit(string $action, int $subjectId): void
    {
        DB::table('audit_logs')->insert([
            'village_id' => auth()->user()->village_id ?? Village::where('code', 'SIMARE')->value('id'),
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => 'tb_program',
            'subject_id' => $subjectId,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
