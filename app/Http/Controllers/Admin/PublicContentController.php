<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PublicDocument;
use App\Models\Village;
use App\Models\VillageArticle;
use App\Models\VillageBudget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicContentController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(in_array($request->user()->role,['admin_desa','operator','admin_publikasi'],true),403);
        $section=$request->string('bagian','dokumen')->toString(); abort_unless(in_array($section,['dokumen','anggaran','artikel'],true),404);
        $village=$this->village($request);
        $records=match($section){
            'anggaran'=>VillageBudget::where('village_id',$village->id)->latest('year')->paginate(12),
            'artikel'=>VillageArticle::where('village_id',$village->id)->latest()->paginate(12),
            default=>PublicDocument::where('village_id',$village->id)->latest('year')->paginate(12),
        };
        return view('admin.public-content',compact('section','records'));
    }
    public function document(Request $request): RedirectResponse
    {
        $this->authorizeRole($request); $data=$request->validate(['kind'=>['required',Rule::in(['ppid','regulation'])],'category'=>['required','max:80'],'title'=>['required','max:200'],'year'=>['nullable','integer','min:1945','max:'.(now()->year+1)],'document_number'=>['nullable','max:100'],'summary'=>['nullable','max:1500'],'published'=>['nullable','boolean']]);
        $record=PublicDocument::create($data+['village_id'=>$this->village($request)->id,'status'=>$request->boolean('published')?'published':'draft','published'=>$request->boolean('published')]); $this->audit($request,'public_document.created',$record->id);
        return back()->with('success','Dokumen publik berhasil disimpan.');
    }
    public function budget(Request $request): RedirectResponse
    {
        $this->authorizeRole($request); $data=$request->validate(['year'=>['required','integer','min:2000','max:'.(now()->year+2)],'type'=>['required',Rule::in(['pendapatan','belanja','pembiayaan'])],'sector'=>['required','max:120'],'name'=>['required','max:180'],'budget_amount'=>['required','numeric','min:0'],'realized_amount'=>['required','numeric','min:0'],'published'=>['nullable','boolean']]);
        $record=VillageBudget::create($data+['village_id'=>$this->village($request)->id,'published'=>$request->boolean('published')]); $this->audit($request,'village_budget.created',$record->id);
        return back()->with('success','Rincian APBDes berhasil disimpan.');
    }
    public function article(Request $request): RedirectResponse
    {
        $this->authorizeRole($request); $data=$request->validate(['title'=>['required','max:200'],'category'=>['required','max:80'],'excerpt'=>['nullable','max:500'],'content'=>['required','max:30000']]);
        $record=VillageArticle::create($data+['village_id'=>$this->village($request)->id,'author_id'=>$request->user()->id,'slug'=>Str::slug($data['title']).'-'.Str::lower(Str::random(5)),'status'=>'draft']); $this->audit($request,'article.drafted',$record->id);
        return back()->with('success','Artikel disimpan sebagai draf dan menunggu peninjauan.');
    }
    public function publish(Request $request,VillageArticle $article): RedirectResponse
    {
        abort_unless($request->user()->role==='admin_desa',403); abort_unless($article->village_id===$this->village($request)->id,404);
        $article->update(['status'=>'published','reviewed_by'=>$request->user()->id,'published_at'=>now()]); $this->audit($request,'article.published',$article->id);
        return back()->with('success','Artikel telah ditinjau dan diterbitkan.');
    }
    private function authorizeRole(Request $request): void { abort_unless(in_array($request->user()->role,['admin_desa','operator','admin_publikasi'],true),403); }
    private function village(Request $request): Village { return Village::find($request->user()->village_id)??Village::firstOrFail(); }
    private function audit(Request $request,string $action,int $id): void { DB::table('audit_logs')->insert(['village_id'=>$this->village($request)->id,'user_id'=>$request->user()->id,'action'=>$action,'subject_id'=>$id,'ip_address'=>$request->ip(),'created_at'=>now()]); }
}
