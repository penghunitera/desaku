<?php

use App\Http\Controllers\Admin\AdminWorkspaceController;
use App\Http\Controllers\Admin\AuthenticatedSessionController;
use App\Http\Controllers\Admin\PropertyTaxWorkspaceController;
use App\Http\Controllers\Admin\PublicContentController;
use App\Http\Controllers\Admin\ResidentController;
use App\Http\Controllers\Admin\ServiceWorkflowController;
use App\Http\Controllers\Admin\TbProgramController;
use App\Http\Controllers\CitizenPortalController;
use App\Http\Controllers\CommunityServiceController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PropertyTaxController;
use App\Http\Controllers\PropertyTaxPotentialController;
use App\Http\Controllers\PublicInformationController;
use App\Http\Controllers\ServiceRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::middleware('guest')->group(function () {
    Route::get('/pengelola/masuk', [AuthenticatedSessionController::class, 'create'])->name('admin.login');
    Route::post('/pengelola/masuk', [AuthenticatedSessionController::class, 'store'])->name('admin.login.store');
});
Route::prefix('pengelola')->name('admin.')->middleware('auth')->group(function () {
    Route::get('/', [AdminWorkspaceController::class, 'dashboard'])->name('dashboard');
    Route::get('/data', [AdminWorkspaceController::class, 'data'])->name('data');
    Route::post('/penduduk', [AdminWorkspaceController::class, 'resident'])->name('resident.store');
    Route::resource('warga', ResidentController::class)->parameters(['warga' => 'resident'])->names('residents')->except('destroy');
    Route::get('/pelayanan', [ServiceWorkflowController::class, 'index'])->name('services.index');
    Route::get('/pelayanan/{serviceRequest}', [ServiceWorkflowController::class, 'show'])->name('services.show');
    Route::patch('/pelayanan/{serviceRequest}', [ServiceWorkflowController::class, 'update'])->name('services.update');
    Route::get('/publikasi', [PublicContentController::class, 'index'])->name('content.index');
    Route::post('/publikasi/dokumen', [PublicContentController::class, 'document'])->name('content.document');
    Route::post('/publikasi/anggaran', [PublicContentController::class, 'budget'])->name('content.budget');
    Route::post('/publikasi/artikel', [PublicContentController::class, 'article'])->name('content.article');
    Route::patch('/publikasi/artikel/{article}/terbitkan', [PublicContentController::class, 'publish'])->name('content.publish');
    Route::post('/produk', [AdminWorkspaceController::class, 'product'])->name('product.store');
    Route::post('/pbb', [AdminWorkspaceController::class, 'tax'])->name('tax.store');
    Route::get('/pbb', [PropertyTaxWorkspaceController::class, 'index'])->name('tax.index');
    Route::get('/pbb/rekap.csv', [PropertyTaxWorkspaceController::class, 'export'])->name('tax.export');
    Route::post('/pbb/sinkron', [PropertyTaxWorkspaceController::class, 'syncVillage'])->name('tax.sync-village');
    Route::post('/pbb/warga/{resident}/sinkron', [PropertyTaxWorkspaceController::class, 'syncResident'])->name('tax.sync-resident');
    Route::post('/potensi', [AdminWorkspaceController::class, 'potential'])->name('potential.store');
    Route::get('/kesehatan/kampung-tb/ringkasan', [TbProgramController::class, 'summary'])->name('health.tb.summary');
    Route::get('/kesehatan/kampung-tb', [TbProgramController::class, 'index'])->name('health.tb.index');
    Route::post('/kesehatan/kampung-tb', [TbProgramController::class, 'store'])->name('health.tb.store');
    Route::patch('/kesehatan/kampung-tb/{tbCase}', [TbProgramController::class, 'update'])->name('health.tb.update');
    Route::post('/kesehatan/kampung-tb/{tbCase}/kontak', [TbProgramController::class, 'contact'])->name('health.tb.contacts.store');
    Route::post('/kesehatan/kampung-tb/{tbCase}/pengobatan', [TbProgramController::class, 'treatment'])->name('health.tb.treatments.store');
    Route::post('/keluar', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
Route::get('/pbb/cek-tunggakan', [PropertyTaxController::class, 'index'])->name('property-tax.index');
Route::post('/pbb/cek-tunggakan', [PropertyTaxController::class, 'check'])->name('property-tax.check');
Route::get('/pbb/potensi', [PropertyTaxPotentialController::class, 'index'])->name('property-tax.potential');
Route::post('/pbb/potensi/warga', [PropertyTaxPotentialController::class, 'resident'])->name('property-tax.potential.resident');
Route::get('/portal/{category}', [CommunityServiceController::class, 'show'])
    ->whereIn('category', ['kesehatan', 'bantuan-sosial', 'bphtb', 'bumdes', 'program-pemerintah'])
    ->name('community-services.show');
Route::get('/layanan', [ServiceRequestController::class, 'index'])->name('services.index');
Route::get('/layanan/ajukan', [ServiceRequestController::class, 'create'])->name('services.create');
Route::post('/layanan/ajukan', [ServiceRequestController::class, 'store'])->name('services.store');
Route::post('/layanan/lacak', [ServiceRequestController::class, 'track'])->name('services.track');
Route::get('/layanan/permohonan/{serviceRequest:public_id}', [ServiceRequestController::class, 'show'])->name('services.show');
Route::prefix('warga')->name('citizen.')->group(function () {
    Route::get('/masuk', [CitizenPortalController::class, 'login'])->name('login');
    Route::post('/masuk', [CitizenPortalController::class, 'authenticate'])->name('authenticate');
    Route::get('/', [CitizenPortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/permohonan/{serviceRequest}', [CitizenPortalController::class, 'request'])->name('request');
    Route::post('/keluar', [CitizenPortalController::class, 'logout'])->name('logout');
});
Route::get('/data/statistik', [PublicInformationController::class, 'statistics'])->name('public.statistics');
Route::get('/transparansi/apbdes', [PublicInformationController::class, 'budget'])->name('public.budget');
Route::get('/ppid', [PublicInformationController::class, 'ppid'])->name('public.ppid');
Route::get('/jdih', [PublicInformationController::class, 'regulations'])->name('public.regulations');
Route::get('/lapak', [PublicInformationController::class, 'marketplace'])->name('public.marketplace');
Route::get('/informasi', [PublicInformationController::class, 'articles'])->name('public.articles');
