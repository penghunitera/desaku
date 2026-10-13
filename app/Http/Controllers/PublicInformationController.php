<?php

namespace App\Http\Controllers;

use App\Models\BumdesProduct;
use App\Models\PublicDocument;
use App\Models\Resident;
use App\Models\VillageBudget;
use App\Models\VillageArticle;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicInformationController extends Controller
{
    public function statistics(): View
    {
        $active = Resident::where('resident_status','tetap');
        return view('public.statistics', [
            'total'=>(clone $active)->count(),
            'gender'=>(clone $active)->selectRaw("coalesce(gender, 'Belum diisi') label, count(*) total")->groupBy('gender')->pluck('total','label'),
            'hamlets'=>(clone $active)->selectRaw("coalesce(hamlet, 'Belum diisi') label, count(*) total")->groupBy('hamlet')->orderByDesc('total')->pluck('total','label'),
            'occupations'=>(clone $active)->selectRaw("coalesce(occupation, 'Belum diisi') label, count(*) total")->groupBy('occupation')->orderByDesc('total')->limit(8)->pluck('total','label'),
            'education'=>(clone $active)->selectRaw("coalesce(education, 'Belum diisi') label, count(*) total")->groupBy('education')->orderByDesc('total')->limit(8)->pluck('total','label'),
        ]);
    }
    public function budget(Request $request): View
    {
        $year = (int)($request->integer('tahun') ?: VillageBudget::where('published',true)->max('year') ?: now()->year);
        $rows = VillageBudget::where('published',true)->where('year',$year)->orderBy('type')->orderBy('sector')->get();
        return view('public.budget', compact('year','rows'));
    }
    public function ppid(Request $request): View
    {
        $documents = PublicDocument::where('published',true)->where('kind','ppid')->when($request->filled('q'),fn($q)=>$q->where('title','like','%'.$request->string('q').'%'))->latest('year')->paginate(12);
        return view('public.documents', ['kind'=>'PPID','title'=>'Informasi Publik','documents'=>$documents]);
    }
    public function regulations(Request $request): View
    {
        $documents = PublicDocument::where('published',true)->where('kind','regulation')->when($request->filled('q'),fn($q)=>$q->where('title','like','%'.$request->string('q').'%'))->latest('issued_at')->paginate(12);
        return view('public.documents', ['kind'=>'JDIH','title'=>'Peraturan Desa','documents'=>$documents]);
    }
    public function marketplace(): View
    {
        return view('public.marketplace',['products'=>BumdesProduct::where('published',true)->orderBy('name')->paginate(12)]);
    }
    public function articles(): View
    {
        return view('public.articles', ['articles'=>VillageArticle::where('status','published')->whereNotNull('published_at')->latest('published_at')->paginate(12)]);
    }
}
