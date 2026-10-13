<?php

namespace App\Http\Controllers;

use App\Models\Resident;
use App\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class CitizenPortalController extends Controller
{
    public function login(): View { return view('citizen.login'); }

    public function authenticate(Request $request): RedirectResponse
    {
        $data = $request->validate(['nik'=>['required','digits:16'],'pin'=>['required','digits:6']]);
        $resident = Resident::where('nik_hash', hash_hmac('sha256', $data['nik'], config('app.key')))->first();
        if (! $resident || ! $resident->citizen_pin_hash || ! Hash::check($data['pin'], $resident->citizen_pin_hash)) {
            return back()->withErrors(['nik'=>'NIK atau PIN tidak sesuai. Hubungi operator desa bila belum memiliki PIN.'])->onlyInput('nik');
        }
        $request->session()->regenerate(); $request->session()->put('citizen_resident_id', $resident->id);
        $resident->update(['citizen_last_login_at'=>now()]);
        return redirect()->route('citizen.dashboard');
    }

    public function dashboard(Request $request): View
    {
        $resident = $this->resident($request)->load(['familyCard.residents','serviceRequests.histories','taxObjects.assessments']);
        return view('citizen.dashboard', compact('resident'));
    }

    public function request(Request $request, ServiceRequest $serviceRequest): View
    {
        $resident = $this->resident($request); abort_unless($serviceRequest->resident_id === $resident->id, 404);
        $serviceRequest->load(['documents','histories']);
        return view('citizen.request', ['resident'=>$resident,'submission'=>$serviceRequest,'service'=>ServiceRequestController::SERVICES[$serviceRequest->service_type] ?? null]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('citizen_resident_id'); $request->session()->regenerateToken();
        return redirect()->route('citizen.login');
    }

    private function resident(Request $request): Resident
    {
        $resident = Resident::find($request->session()->get('citizen_resident_id'));
        if (! $resident) abort(403);
        return $resident;
    }
}
