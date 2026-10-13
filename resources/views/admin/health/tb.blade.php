@extends('admin.layout')
@section('title','Kampung TB')
@section('heading','Kampung TB Simare')
@section('subheading','Skrining, investigasi kontak, dan pendampingan pengobatan dalam satu ruang kerja terlindungi.')
@section('content')
<section class="tb-alert">
    <div><span>✚</span><div><small>PRIORITAS HARI INI</small><b>{{ $metrics['pending_contacts'] }} kontak menunggu skrining · {{ $metrics['overdue'] }} kunjungan terlambat</b><p>Gunakan kode kasus saat koordinasi. Identitas pasien tidak boleh disebarkan di luar petugas berwenang.</p></div></div>
    <a href="#skrining-baru">+ Skrining warga</a>
</section>

<section class="tb-metrics">
    <article><small>WARGA DISKRINING</small><b>{{ $metrics['screened'] }}</b><span>Register Kampung TB</span></article>
    <article class="amber"><small>PERLU TINDAK LANJUT</small><b>{{ $metrics['suspected'] }}</b><span>Terduga / dirujuk</span></article>
    <article><small>SEDANG BEROBAT</small><b>{{ $metrics['treatment'] }}</b><span>Perlu pendampingan</span></article>
    <article class="blue"><small>SELESAI / SEMBUH</small><b>{{ $metrics['completed'] }}</b><span>Hasil terverifikasi</span></article>
</section>

<section class="tb-layout">
    <div class="tb-main">
        <section class="admin-panel">
            <div class="panel-head">
                <div><small>REGISTER TERLINDUNGI</small><h2>Daftar tindak lanjut</h2></div>
                <form class="tb-filter" method="get">
                    <select name="status" onchange="this.form.submit()" aria-label="Saring berdasarkan status">
                        <option value="">Semua status</option>
                        @foreach(['suspected'=>'Terduga','referred'=>'Dirujuk','examined'=>'Diperiksa','confirmed'=>'Terkonfirmasi','treatment'=>'Berobat','completed'=>'Selesai','recovered'=>'Sembuh','lost_follow_up'=>'Putus berobat','not_tb'=>'Bukan TB'] as $value=>$label)
                            <option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="tb-cases">
                @forelse($cases as $case)
                @php
                    $labels=['suspected'=>'Terduga','referred'=>'Dirujuk','examined'=>'Diperiksa','confirmed'=>'Terkonfirmasi','treatment'=>'Berobat','completed'=>'Selesai','recovered'=>'Sembuh','lost_follow_up'=>'Putus berobat','not_tb'=>'Bukan TB'];
                    $clinical=$case->clinical_data_encrypted ?? [];
                @endphp
                <details class="tb-case" @if($loop->first) open @endif>
                    <summary>
                        <div class="case-person"><i>{{ str($case->resident->full_name)->substr(0,1) }}</i><span><b>{{ $case->resident->full_name }}</b><small>{{ $case->case_code }} · {{ $case->resident->hamlet ?: 'Dusun belum diisi' }}</small></span></div>
                        <span class="tb-status {{ $case->status }}">{{ $labels[$case->status] ?? $case->status }}</span>
                        <div class="case-task"><b>{{ $case->contacts->where('screening_status','pending')->count() }}</b><small>kontak tertunda</small></div>
                        <span class="case-chevron">⌄</span>
                    </summary>
                    <div class="case-body">
                        <div class="case-facts">
                            <div><small>SKRINING</small><b>{{ $case->screened_at->format('d M Y') }}</b></div>
                            <div><small>RUJUKAN</small><b>{{ $case->referral_facility }}</b></div>
                            <div><small>KUNJUNGAN BERIKUT</small><b>{{ $case->next_visit_at?->format('d M Y') ?? 'Belum dijadwalkan' }}</b></div>
                            <div><small>GEJALA TERCATAT</small><b>{{ count($clinical['symptoms'] ?? []) }} indikator</b></div>
                        </div>
                        <div class="case-forms">
                            <form method="post" action="{{ route('admin.health.tb.update',$case) }}">
                                @csrf @method('patch')
                                <h3>Perbarui status</h3>
                                <div class="mini-grid">
                                    <label>Status<select name="status">@foreach($labels as $value=>$label)<option value="{{ $value }}" @selected($case->status===$value)>{{ $label }}</option>@endforeach</select></label>
                                    <label>Fasilitas rujukan<input name="referral_facility" value="{{ $case->referral_facility }}" required></label>
                                    <label>Mulai obat<input type="date" name="treatment_started_at" value="{{ $case->treatment_started_at?->format('Y-m-d') }}"></label>
                                    <label>Target selesai<input type="date" name="treatment_due_at" value="{{ $case->treatment_due_at?->format('Y-m-d') }}"></label>
                                    <label>Kunjungan berikut<input type="date" name="next_visit_at" value="{{ $case->next_visit_at?->format('Y-m-d') }}"></label>
                                </div>
                                <button>Simpan status</button>
                            </form>
                            <form method="post" action="{{ route('admin.health.tb.contacts.store',$case) }}">
                                @csrf
                                <h3>Tambah kontak serumah</h3>
                                <div class="mini-grid">
                                    <label class="wide">Warga<select name="resident_id" required><option value="">Pilih warga</option>@foreach($residents->where('id','!=',$case->resident_id) as $resident)<option value="{{ $resident->id }}">{{ $resident->full_name }} — {{ $resident->hamlet }}</option>@endforeach</select></label>
                                    <label>Hubungan<input name="relationship" placeholder="Anak, pasangan..."></label>
                                    <label>Status skrining<select name="screening_status"><option value="pending">Belum diskrining</option><option value="screened">Sudah diskrining</option><option value="referred">Dirujuk</option><option value="clear">Tidak bergejala</option></select></label>
                                    <label>Status TPT<select name="tpt_status"><option value="not_assessed">Belum dinilai</option><option value="eligible">Layak TPT</option><option value="started">Mulai TPT</option><option value="completed">Selesai TPT</option><option value="not_eligible">Tidak layak</option></select></label>
                                    <label class="checkline"><input type="checkbox" name="priority" value="1"> Prioritas rentan</label>
                                </div>
                                <button>Simpan kontak</button>
                            </form>
                            <form method="post" action="{{ route('admin.health.tb.treatments.store',$case) }}">
                                @csrf
                                <h3>Catat pendampingan</h3>
                                <div class="mini-grid">
                                    <label>Tanggal<input type="date" name="recorded_at" value="{{ today()->format('Y-m-d') }}" required></label>
                                    <label>Kepatuhan<select name="adherence"><option value="taken">Obat diminum</option><option value="missed">Terlewat</option><option value="unknown">Belum diketahui</option></select></label>
                                    <label>Jenis kegiatan<select name="visit_type"><option value="medicine">Pemantauan obat</option><option value="control">Kontrol puskesmas</option><option value="home_visit">Kunjungan rumah</option><option value="sputum_test">Pemeriksaan dahak</option></select></label>
                                    <label>Jadwal berikut<input type="date" name="next_visit_at"></label>
                                    <label class="wide">Catatan rahasia<textarea name="notes" rows="2"></textarea></label>
                                </div>
                                <button>Simpan pemantauan</button>
                            </form>
                        </div>
                    </div>
                </details>
                @empty
                <div class="table-empty">Belum ada skrining. Mulai dari formulir di sebelah kanan.</div>
                @endforelse
            </div>
            <div class="pagination-wrap">{{ $cases->links() }}</div>
        </section>
    </div>

    <aside class="tb-side">
        <section class="admin-panel" id="skrining-baru">
            <div class="panel-head"><div><small>TEMU CEPAT</small><h2>Skrining baru</h2></div></div>
            <form class="tb-screening" method="post" action="{{ route('admin.health.tb.store') }}">
                @csrf
                <label>Warga terdaftar<select name="resident_id" required><option value="">Pilih nama warga</option>@foreach($residents as $resident)<option value="{{ $resident->id }}" @selected(old('resident_id')==$resident->id)>{{ $resident->full_name }} — {{ $resident->hamlet }}</option>@endforeach</select></label>
                <label>Tanggal skrining<input type="date" name="screened_at" value="{{ old('screened_at',today()->format('Y-m-d')) }}" required></label>
                <fieldset><legend>Gejala / faktor risiko</legend>
                    @foreach(['cough'=>'Batuk berkepanjangan','fever'=>'Demam','night_sweats'=>'Keringat malam','weight_loss'=>'Berat badan turun','close_contact'=>'Kontak erat pasien TB'] as $value=>$label)
                    <label class="symptom"><input type="checkbox" name="symptoms[]" value="{{ $value }}"><span>{{ $label }}</span></label>
                    @endforeach
                </fieldset>
                <label>Kelompok risiko<select name="risk_group"><option value="">Tidak ditandai</option><option>Anak di bawah 5 tahun</option><option>Lansia</option><option>Ibu hamil</option><option>Diabetes</option><option>Imunitas rendah</option></select></label>
                <label>Catatan rahasia<textarea name="notes" rows="3" placeholder="Hanya terlihat petugas berwenang"></textarea></label>
                <button>Catat dan buat rujukan</button>
            </form>
        </section>
        <section class="tb-privacy">
            <b>Data kesehatan dilindungi</b>
            <p>Gunakan dashboard publik hanya untuk angka agregat. Nama, alamat, gejala, dan status pengobatan tidak boleh dicetak atau dibagikan tanpa kewenangan.</p>
        </section>
    </aside>
</section>
@endsection
