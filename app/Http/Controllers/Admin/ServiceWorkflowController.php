<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ServiceRequestController;
use App\Models\ServiceRequest;
use App\Support\AdminAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceWorkflowController extends Controller
{
    private const STATUSES = ['submitted','verified','revision','approved','signed','ready','completed','rejected'];
    public function index(Request $request): View
    {
        abort_unless(AdminAccess::allows($request->user(),'services.manage') || $request->user()->role === 'admin_desa',403);
        $items=ServiceRequest::with('resident')->when($request->filled('status'),fn($q)=>$q->where('status',$request->string('status')))->latest('submitted_at')->paginate(15)->withQueryString();
        return view('admin.services.index',['items'=>$items,'statuses'=>self::STATUSES]);
    }
    public function show(Request $request, ServiceRequest $serviceRequest): View
    {
        abort_unless(AdminAccess::allows($request->user(),'services.manage') || $request->user()->role === 'admin_desa',403);
        $serviceRequest->load(['resident','documents','histories']);
        return view('admin.services.show',['item'=>$serviceRequest,'service'=>ServiceRequestController::SERVICES[$serviceRequest->service_type]??null,'statuses'=>self::STATUSES]);
    }
    public function update(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        abort_unless(AdminAccess::allows($request->user(),'services.manage') || $request->user()->role === 'admin_desa',403);
        $data=$request->validate(['status'=>['required',Rule::in(self::STATUSES)],'notes'=>['nullable','max:2000']]);
        DB::transaction(function()use($request,$serviceRequest,$data){
            $serviceRequest->update(['status'=>$data['status'],'assigned_to'=>$request->user()->id,'officer_notes'=>$data['notes']??null,'completed_at'=>$data['status']==='completed'?now():null]);
            $serviceRequest->histories()->create(['status'=>$data['status'],'title'=>$this->title($data['status']),'description'=>$data['notes']?:'Status diperbarui oleh petugas Desa Simare.','changed_by'=>$request->user()->id]);
            DB::table('audit_logs')->insert(['village_id'=>$serviceRequest->village_id,'user_id'=>$request->user()->id,'action'=>'service_request.status_updated','subject_type'=>ServiceRequest::class,'subject_id'=>$serviceRequest->id,'metadata'=>json_encode(['status'=>$data['status']]),'ip_address'=>$request->ip(),'created_at'=>now()]);
        });
        return back()->with('success','Status permohonan berhasil diperbarui.');
    }
    private function title(string $status): string { return match($status){'verified'=>'Berkas telah diverifikasi','revision'=>'Perlu perbaikan berkas','approved'=>'Permohonan disetujui','signed'=>'Dokumen telah ditandatangani','ready'=>'Dokumen siap diambil','completed'=>'Pelayanan selesai','rejected'=>'Permohonan ditolak',default=>'Permohonan diterima'}; }
}
